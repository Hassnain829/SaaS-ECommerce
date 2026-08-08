<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExCheckoutPackageBuilder;
use App\Services\Carriers\FedEx\Operations\FedExCheckoutRateResolver;
use App\Services\Carriers\FedEx\Operations\FedExComprehensiveRateResponseParser;
use App\Services\Carriers\FedEx\Operations\FedExNegotiatedRateService;
use App\Services\Carriers\FedEx\Operations\FedExOperationGuard;
use App\Services\Carriers\FedEx\Support\FedExHttpClient;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase6FedExStep4DefectCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        Cache::flush();
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.integrator_production_enabled' => false,
            'carriers.fedex.ops_negotiated_rates_enabled' => true,
            'carriers.fedex.checkout_rates_enabled' => false,
            'carriers.fedex.sandbox.client_id' => 'parent-client-id-1234567890',
            'carriers.fedex.sandbox.client_secret' => 'parent-client-secret',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.comprehensive_rate_quote_path' => '/rate/v1/comprehensiverates/quotes',
        ]);
    }

    public function test_guard_blocks_failed_account_even_when_active_key_present(): void
    {
        [$store, $account] = $this->modelAAccount('Failed Key Store');
        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_FAILED,
            'fedex_active_store_key' => CarrierAccount::fedExActiveStoreKeyFor(
                (int) $store->id,
                CarrierAccount::ENVIRONMENT_SANDBOX,
            ),
        ])->save();

        $this->expectException(HttpException::class);
        app(FedExOperationGuard::class)->assertAccountForOperation(
            $store,
            $account->fresh(),
            FedExOperationGuard::CAPABILITY_NEGOTIATED_RATES,
        );
    }

    public function test_guard_blocks_malformed_active_key(): void
    {
        [$store, $account] = $this->modelAAccount('Bad Key Store');
        $account->forceFill([
            'fedex_active_store_key' => 'store:999:fedex:sandbox',
        ])->save();

        $this->expectException(HttpException::class);
        app(FedExOperationGuard::class)->assertAccountForOperation(
            $store,
            $account->fresh(),
            FedExOperationGuard::CAPABILITY_NEGOTIATED_RATES,
        );
    }

    public function test_guard_blocks_disconnected_and_replaced_accounts(): void
    {
        [$store, $account] = $this->modelAAccount('Disconnected Store');
        $account->forceFill(['disconnected_at' => now()])->save();

        try {
            app(FedExOperationGuard::class)->assertAccountForOperation(
                $store,
                $account->fresh(),
                FedExOperationGuard::CAPABILITY_NEGOTIATED_RATES,
            );
            $this->fail('Expected disconnected account to be blocked');
        } catch (HttpException) {
            // expected
        }

        $account->forceFill([
            'disconnected_at' => null,
            'replaced_at' => now(),
        ])->save();

        $this->expectException(HttpException::class);
        app(FedExOperationGuard::class)->assertAccountForOperation(
            $store,
            $account->fresh(),
            FedExOperationGuard::CAPABILITY_NEGOTIATED_RATES,
        );
    }

    public function test_resolve_active_account_requires_exact_key(): void
    {
        [$store, $account] = $this->modelAAccount('Exact Key Store');
        $account->forceFill([
            'fedex_active_store_key' => 'not-the-expected-key',
        ])->save();

        $this->assertNull(app(FedExOperationGuard::class)->resolveActiveModelAAccount($store));

        $account->forceFill([
            'fedex_active_store_key' => CarrierAccount::fedExActiveStoreKeyFor(
                (int) $store->id,
                CarrierAccount::ENVIRONMENT_SANDBOX,
            ),
        ])->save();

        $resolved = app(FedExOperationGuard::class)->resolveActiveModelAAccount($store);
        $this->assertNotNull($resolved);
        $this->assertSame($account->id, $resolved->id);
    }

    public function test_parser_does_not_fallback_to_list_when_account_required(): void
    {
        $parser = app(FedExComprehensiveRateResponseParser::class);
        $parsed = $parser->parse([
            'output' => [
                'rateReplyDetails' => [[
                    'serviceType' => 'FEDEX_GROUND',
                    'serviceName' => 'FedEx Ground',
                    'ratedShipmentDetails' => [[
                        'rateType' => 'LIST',
                        'totalNetCharge' => 22.00,
                        'shipmentRateDetail' => ['currency' => 'USD'],
                    ]],
                ]],
            ],
        ], expectedRateType: 'ACCOUNT', allowFallbackToAnyRate: false);

        $this->assertNull($parsed['amount']);
        $this->assertNull($parsed['rate_type']);
        $this->assertCount(1, $parsed['available_rates']);
        $this->assertSame('LIST', $parsed['available_rates'][0]['rate_type']);
    }

    public function test_negotiated_service_rejects_list_only_response(): void
    {
        [$store, $account, $location] = $this->modelAAccountWithLocation('List Only Store');

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'token-list-only',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/rate/v1/comprehensiverates/quotes' => Http::response([
                'transactionId' => 'list-only',
                'output' => [
                    'rateReplyDetails' => [[
                        'serviceType' => 'FEDEX_GROUND',
                        'serviceName' => 'FedEx Ground',
                        'ratedShipmentDetails' => [[
                            'rateType' => 'LIST',
                            'totalNetCharge' => 19.99,
                            'shipmentRateDetail' => ['currency' => 'USD'],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        $outcome = app(FedExNegotiatedRateService::class)->quoteForOriginDestination(
            store: $store,
            account: $account,
            originLocation: $location,
            destinationInput: [
                'country_code' => 'US',
                'postal_code' => '38116',
                'state' => 'TN',
                'city' => 'Memphis',
            ],
            packageInput: ['weight' => 2, 'length' => 9, 'width' => 6, 'height' => 2],
        );

        $this->assertFalse($outcome['result']->successful);
        $this->assertSame('account_rate_unavailable', $outcome['result']->fedexErrorCode);
        $this->assertNull($outcome['result']->amount);
    }

    public function test_checkout_resolver_rejects_currency_mismatch(): void
    {
        [$store, $account, $location] = $this->modelAAccountWithLocation('Currency Store');
        $account->forceFill([
            'enabled_for_checkout' => true,
            'capabilities' => array_merge((array) $account->capabilities, ['checkout_rates' => true]),
        ])->save();
        config(['carriers.fedex.checkout_rates_enabled' => true]);

        $method = new \App\Models\ShippingMethod([
            'store_id' => $store->id,
            'name' => 'FedEx Ground',
            'rate_type' => \App\Models\ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_account_id' => $account->id,
        ]);
        $method->setRelation('carrierAccount', $account->fresh());

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'token-cad',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/rate/v1/comprehensiverates/quotes' => Http::response([
                'transactionId' => 'cad-rate',
                'output' => [
                    'rateReplyDetails' => [[
                        'serviceType' => 'FEDEX_GROUND',
                        'serviceName' => 'FedEx Ground',
                        'ratedShipmentDetails' => [[
                            'rateType' => 'ACCOUNT',
                            'totalNetCharge' => 18.00,
                            'shipmentRateDetail' => ['currency' => 'CAD'],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        $rejected = app(FedExCheckoutRateResolver::class)->resolve(
            store: $store,
            method: $method,
            destination: ['country_code' => 'US', 'postal_code' => '38116', 'state' => 'TN', 'city' => 'Memphis'],
            origin: $location,
            packages: [['weight' => 5, 'weight_unit' => 'LB', 'length' => 12, 'width' => 10, 'height' => 8, 'dimension_unit' => 'IN']],
            checkoutCurrency: 'USD',
            cartFingerprint: 'cart-a',
        );
        $this->assertNull($rejected);
    }

    public function test_checkout_resolver_accepts_matching_currency_and_packages(): void
    {
        [$store, $account, $location] = $this->modelAAccountWithLocation('Currency Match Store');
        $account->forceFill([
            'enabled_for_checkout' => true,
            'capabilities' => array_merge((array) $account->capabilities, ['checkout_rates' => true]),
        ])->save();
        config(['carriers.fedex.checkout_rates_enabled' => true]);

        $method = new \App\Models\ShippingMethod([
            'store_id' => $store->id,
            'name' => 'FedEx Ground',
            'rate_type' => \App\Models\ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_account_id' => $account->id,
        ]);
        $method->setRelation('carrierAccount', $account->fresh());

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'token-usd',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/rate/v1/comprehensiverates/quotes' => Http::response([
                'transactionId' => 'usd-rate',
                'output' => [
                    'rateReplyDetails' => [[
                        'serviceType' => 'FEDEX_GROUND',
                        'serviceName' => 'FedEx Ground',
                        'ratedShipmentDetails' => [[
                            'rateType' => 'ACCOUNT',
                            'totalNetCharge' => 14.25,
                            'shipmentRateDetail' => ['currency' => 'USD'],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        $accepted = app(FedExCheckoutRateResolver::class)->resolve(
            store: $store,
            method: $method,
            destination: ['country_code' => 'US', 'postal_code' => '38116', 'state' => 'TN', 'city' => 'Memphis'],
            origin: $location,
            packages: [['weight' => 5, 'weight_unit' => 'LB', 'length' => 12, 'width' => 10, 'height' => 8, 'dimension_unit' => 'IN']],
            checkoutCurrency: 'USD',
            cartFingerprint: 'cart-b',
        );

        $this->assertNotNull($accepted);
        $this->assertSame('14.25', $accepted['amount']);
        $this->assertSame($location->id, $accepted['origin_location_id']);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/rate/v1/comprehensiverates/quotes')) {
                return false;
            }
            $body = $request->data();

            return (float) data_get($body, 'requestedShipment.requestedPackageLineItems.0.weight.value') === 5.0
                && (int) data_get($body, 'requestedShipment.requestedPackageLineItems.0.dimensions.length') === 12;
        });
    }

    public function test_checkout_cache_separates_different_cart_fingerprints(): void
    {
        [$store, $account, $location] = $this->modelAAccountWithLocation('Cache Store');
        $account->forceFill([
            'enabled_for_checkout' => true,
            'capabilities' => array_merge((array) $account->capabilities, ['checkout_rates' => true]),
        ])->save();
        config(['carriers.fedex.checkout_rates_enabled' => true]);

        $method = new \App\Models\ShippingMethod([
            'store_id' => $store->id,
            'name' => 'FedEx Ground',
            'rate_type' => \App\Models\ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_account_id' => $account->id,
        ]);
        $method->setRelation('carrierAccount', $account->fresh());

        $calls = 0;
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'token-cache',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/rate/v1/comprehensiverates/quotes' => function () use (&$calls) {
                $calls++;

                return Http::response([
                    'transactionId' => 'cache-'.$calls,
                    'output' => [
                        'rateReplyDetails' => [[
                            'serviceType' => 'FEDEX_GROUND',
                            'ratedShipmentDetails' => [[
                                'rateType' => 'ACCOUNT',
                                'totalNetCharge' => 10 + $calls,
                                'shipmentRateDetail' => ['currency' => 'USD'],
                            ]],
                        ]],
                    ],
                ], 200);
            },
        ]);

        $resolver = app(FedExCheckoutRateResolver::class);
        $a = $resolver->resolve(
            store: $store,
            method: $method,
            destination: ['country_code' => 'US', 'postal_code' => '38116', 'state' => 'TN', 'city' => 'Memphis'],
            origin: $location,
            packages: [['weight' => 1, 'weight_unit' => 'LB', 'length' => 9, 'width' => 6, 'height' => 2, 'dimension_unit' => 'IN']],
            checkoutCurrency: 'USD',
            cartFingerprint: 'cart-1',
        );
        $b = $resolver->resolve(
            store: $store,
            method: $method,
            destination: ['country_code' => 'US', 'postal_code' => '38116', 'state' => 'TN', 'city' => 'Memphis'],
            origin: $location,
            packages: [['weight' => 1, 'weight_unit' => 'LB', 'length' => 9, 'width' => 6, 'height' => 2, 'dimension_unit' => 'IN']],
            checkoutCurrency: 'USD',
            cartFingerprint: 'cart-2',
        );

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNotSame($a['amount'], $b['amount']);
        $this->assertSame(2, $calls);
    }

    public function test_ship_paths_do_not_auto_retry_on_502(): void
    {
        $client = app(FedExHttpClient::class);
        $method = new ReflectionMethod($client, 'transientShipRetryAttempts');
        $method->setAccessible(true);

        $this->assertSame(1, $method->invoke($client, '/ship/v1/shipments'));
        $this->assertSame(1, $method->invoke($client, '/ship/v1/shipments/cancel'));
        $this->assertGreaterThan(1, $method->invoke($client, '/rate/v1/comprehensiverates/quotes'));
    }

    public function test_package_builder_fingerprint_changes_with_quantity(): void
    {
        $store = Store::query()->create([
            'user_id' => User::factory()->create([
                'email' => 'pkg-builder-owner@example.test',
                'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
            ])->id,
            'name' => 'Package Builder Store',
            'slug' => 'package-builder-store-'.Str::lower(Str::random(6)),
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        $checkoutOne = new \App\Models\Checkout(['store_id' => $store->id]);
        $checkoutOne->setRelation('store', $store);
        $itemOne = new \App\Models\CheckoutItem([
            'id' => 1,
            'quantity' => 1,
            'product_variant_id' => 10,
            'product_name' => 'Sample product',
            'product_type_snapshot' => 'physical',
        ]);
        $checkoutOne->setRelation('items', collect([$itemOne]));
        $one = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkoutOne);

        $checkoutThree = new \App\Models\Checkout(['store_id' => $store->id]);
        $checkoutThree->setRelation('store', $store);
        $itemThree = new \App\Models\CheckoutItem([
            'id' => 1,
            'quantity' => 3,
            'product_variant_id' => 10,
            'product_name' => 'Sample product',
            'product_type_snapshot' => 'physical',
        ]);
        $checkoutThree->setRelation('items', collect([$itemThree]));
        $three = app(FedExCheckoutPackageBuilder::class)->buildFromCheckout($checkoutThree);

        $this->assertNotSame($one['fingerprint'], $three['fingerprint']);
        $this->assertSame(1, $one['total_quantity']);
        $this->assertSame(3, $three['total_quantity']);
        $this->assertFalse($one['ready']);
        $this->assertFalse($three['ready']);
        $this->assertSame('missing_weights', $one['reason']);
        $this->assertSame([], $three['packages']);
    }

    /**
     * @return array{0: Store, 1: CarrierAccount}
     */
    private function modelAAccount(string $name): array
    {
        [$store, $account] = $this->modelAAccountWithLocation($name);

        return [$store, $account];
    }

    /**
     * @return array{0: Store, 1: CarrierAccount, 2: Location}
     */
    private function modelAAccountWithLocation(string $name): array
    {
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '90 FedEx Pkwy',
            'city' => 'Collierville',
            'state' => 'TN',
            'postal_code' => '38017',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'Model A FedEx',
            'provider_account_number' => '700257037',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'default_origin_location_id' => $location->id,
            'fedex_active_store_key' => CarrierAccount::fedExActiveStoreKeyFor(
                (int) $store->id,
                CarrierAccount::ENVIRONMENT_SANDBOX,
            ),
            'enabled_for_checkout' => false,
            'settings' => ['default_origin_location_id' => $location->id],
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        $account->setCredentials([
            'customer_key' => 'child-key-a',
            'customer_password' => 'child-secret-a',
        ]);
        $account->setFedExAccountNumber('700257037');
        $account->save();

        return [$store, $account->fresh(), $location];
    }
}
