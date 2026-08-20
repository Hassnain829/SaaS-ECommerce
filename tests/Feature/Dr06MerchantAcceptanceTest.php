<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Services\ConnectedSiteService;
use App\Services\Payments\StripeConfig;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use App\Support\OrderLifecycle;
use App\Support\ProductTypeBehavior;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Dr06MerchantAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_and_recover_account(): void
    {
        Role::firstOrCreate(['name' => 'user']);

        $this->post(route('register.store'), [
            'name' => 'DR06 Merchant',
            'email' => 'dr06-register@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'dr06-register@example.test',
            'name' => 'DR06 Merchant',
        ]);
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'dr06-register@example.test')->firstOrFail();

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();

        Notification::fake();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, QueuedResetPassword::class);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('signin'));
    }

    public function test_owner_can_create_and_switch_between_two_stores(): void
    {
        $owner = $this->merchant('dr06-store-owner@example.test');

        $this->actingAs($owner)
            ->post(route('onboarding-StoreDetails-1.store'), [
                'mode' => 'create',
                'name' => 'DR06 First Store',
                'primary_market' => 'United States',
                'address' => '10 First Street',
                'currency' => 'USD',
                'timezone' => 'America/Chicago',
                'category' => 'physical',
                'business_models' => ['Physical Goods'],
            ])
            ->assertRedirect();

        $storeA = Store::query()->where('name', 'DR06 First Store')->firstOrFail();
        $this->assertTrue($owner->stores()->whereKey($storeA->id)->exists());

        $storeB = $this->store($owner, 'DR06 Second Store');
        $this->attach($storeB, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('current-store.update'), ['store_id' => $storeB->id])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeB->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSee('DR06 Second Store');
    }

    public function test_owner_can_add_simple_and_variant_products_with_initial_stock_movements(): void
    {
        $owner = $this->merchant('dr06-catalog-owner@example.test');
        $store = $this->store($owner, 'DR06 Catalog Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'DR06 Simple Mug',
                'description' => 'A simple mug.',
                'bulk_price' => 15.00,
                'sku' => 'DR06-MUG',
                'product_type' => 'physical',
                'bulk_stock' => 8,
                'stock_alert' => 2,
            ])
            ->assertRedirect(route('products'));

        $simple = Product::query()->where('store_id', $store->id)->where('sku', 'DR06-MUG')->firstOrFail();
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $simple->id,
            'movement_type' => StockMovement::TYPE_INITIAL,
            'quantity_change' => 8,
            'performed_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $this->twoVariantPayload('DR06 Tee', 4, 6))
            ->assertRedirect(route('products'));

        $variantProduct = Product::query()->where('store_id', $store->id)->where('name', 'DR06 Tee')->firstOrFail();
        $this->assertSame(2, $variantProduct->variants()->count());
        $this->assertSame(2, StockMovement::query()
            ->where('store_id', $store->id)
            ->where('product_id', $variantProduct->id)
            ->where('movement_type', StockMovement::TYPE_INITIAL)
            ->count());
    }

    public function test_owner_can_import_mixed_catalog_and_staff_cannot(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->merchant('dr06-import-owner@example.test');
        $staff = $this->merchant('dr06-import-staff@example.test');
        $storeA = $this->store($owner, 'DR06 Import A');
        $storeB = $this->store($owner, 'DR06 Import B');
        $this->attach($storeA, $owner, Store::ROLE_OWNER);
        $this->attach($storeA, $staff, Store::ROLE_STAFF);
        $this->attach($storeB, $owner, Store::ROLE_OWNER);

        $file = UploadedFile::fake()->createWithContent(
            'dr06-catalog.csv',
            "Title,SKU,Price,Stock,ExtraCol\nImported Widget,DR06-IMP-1,11.50,3,keep-me\n"
        );

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->where('store_id', $storeA->id)->firstOrFail();
        $response->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]))
            ->assertRedirect(route('products.import.result', ['productImportId' => $import->id]));

        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->fresh()->status);
        $this->assertDatabaseHas('products', [
            'store_id' => $storeA->id,
            'sku' => 'DR06-IMP-1',
            'name' => 'Imported Widget',
        ]);
        $this->assertDatabaseMissing('products', [
            'store_id' => $storeB->id,
            'sku' => 'DR06-IMP-1',
        ]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('products.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('blocked.csv', "Title,SKU\nX,Y\n"),
            ])
            ->assertForbidden();
    }

    public function test_owner_can_adjust_stock_and_verify_store_scoped_movements(): void
    {
        $owner = $this->merchant('dr06-stock-owner@example.test');
        $storeA = $this->store($owner, 'DR06 Stock A');
        $storeB = $this->store($owner, 'DR06 Stock B');
        $this->attach($storeA, $owner, Store::ROLE_OWNER);
        $this->attach($storeB, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('product.store'), [
                'name' => 'DR06 Stock Item',
                'description' => 'Stocked item.',
                'bulk_price' => 9.00,
                'sku' => 'DR06-STK',
                'product_type' => 'physical',
                'bulk_stock' => 10,
                'stock_alert' => 2,
            ])
            ->assertRedirect(route('products'));

        $productA = Product::query()->where('store_id', $storeA->id)->where('sku', 'DR06-STK')->firstOrFail();
        [$productB] = $this->product($storeB);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->patchJson(route('products.inline.stock', $productA), ['stock' => 17])
            ->assertOk()
            ->assertJsonPath('stock', 17);

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $storeA->id,
            'product_id' => $productA->id,
            'movement_type' => StockMovement::TYPE_EDIT_UPDATE,
            'new_stock' => 17,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeB->id])
            ->patchJson(route('products.inline.stock', $productA), ['stock' => 99])
            ->assertNotFound();

        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $productA->id,
            'new_stock' => 99,
        ]);
        $this->assertSame(5, (int) $productB->variants()->firstOrFail()->fresh()->stock);
    }

    public function test_owner_can_connect_website_and_complete_platform_checkout_smoke(): void
    {
        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_dr06_checkout',
            'payments.stripe.secret' => 'sk_test_dr06_checkout',
            'payments.stripe.webhook_secret' => 'whsec_dr06_checkout',
            'payments.stripe.modes' => [
                'test' => [
                    'key' => 'pk_test_dr06_checkout',
                    'secret' => 'sk_test_dr06_checkout',
                    'webhook_secret' => 'whsec_dr06_checkout',
                ],
                'live' => [
                    'key' => null,
                    'secret' => null,
                    'webhook_secret' => null,
                ],
            ],
        ]);

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: 'pi_dr06_'.$checkout->id,
                    clientSecret: 'pi_dr06_'.$checkout->id.'_secret_test',
                    status: 'requires_payment_method',
                    amount: (string) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: ['id' => 'pi_dr06_'.$checkout->id, 'status' => 'requires_payment_method'],
                );
            }
        });

        $owner = $this->merchant('dr06-checkout-owner@example.test');
        $store = $this->store($owner, 'DR06 Checkout Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $store->forceFill([
            'settings' => array_merge((array) $store->settings, ['checkout_mode' => CheckoutMode::PLATFORM]),
        ])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'http://127.0.0.1:8080',
            ])
            ->assertRedirect();

        $issued = app(ConnectedSiteService::class)->issuePrimaryCredential($store->fresh());
        $token = $issued['plain'];
        $this->connectReadyStripeForCheckout($store);

        [, $variant] = $this->product($store, stock: 5, price: 12);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('Connect your website')
            ->assertSee('Go live checklist');

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'dr06-checkout-'.Str::uuid())
            ->postJson('/api/v1/checkout', [
                'source_channel' => 'wordpress',
                'currency_code' => 'USD',
                'shipping_total' => 0,
                'customer' => [
                    'full_name' => 'DR06 Buyer',
                    'email' => 'dr06.buyer@example.test',
                    'phone' => '+15550199',
                ],
                'shipping_address' => [
                    'name' => 'DR06 Buyer',
                    'address_line1' => '88 Checkout Lane',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '73301',
                    'country' => 'US',
                    'phone' => '+15550199',
                ],
                'billing_address' => ['same_as_shipping' => true],
                'items' => [[
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('payment.provider', 'stripe');

        $this->assertDatabaseHas('checkouts', [
            'store_id' => $store->id,
            'status' => Checkout::STATUS_PAYMENT_PENDING,
            'payment_provider' => 'stripe',
        ]);
        $this->assertSame(4, (int) $variant->fresh()->stock);
    }

    public function test_owner_can_create_convert_draft_and_update_order_and_customer_data(): void
    {
        $owner = $this->merchant('dr06-ops-owner@example.test');
        $storeA = $this->store($owner, 'DR06 Ops A');
        $storeB = $this->store($owner, 'DR06 Ops B');
        $this->attach($storeA, $owner, Store::ROLE_OWNER);
        $this->attach($storeB, $owner, Store::ROLE_OWNER);
        [, $variant] = $this->product($storeA, stock: 5, price: 12);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('draft-orders.store'), [
                'customer_name' => 'Draft Buyer',
                'customer_email' => 'draft.buyer@example.test',
                'shipping_name' => 'Draft Buyer',
                'shipping_address_line1' => '12 Draft Road',
                'shipping_city' => 'Austin',
                'shipping_state' => 'TX',
                'shipping_postal_code' => '73301',
                'shipping_country' => 'US',
                'billing_same_as_shipping' => '1',
                'shipping_total' => '5.00',
                'tax_total' => '0.00',
                'discount_total' => '0.00',
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => '12.00',
                ]],
            ])
            ->assertRedirect();

        $draft = DraftOrder::query()->where('store_id', $storeA->id)->firstOrFail();
        $customer = Customer::query()->where('store_id', $storeA->id)->where('email', 'draft.buyer@example.test')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $storeA->id)->where('order_source', 'manual')->firstOrFail();
        $this->assertSame(DraftOrder::STATUS_CONVERTED, $draft->fresh()->status);
        $this->assertSame(4, (int) $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $storeA->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_ORDER_DEDUCTED,
            'source' => 'manual_order',
        ]);
        $this->assertDatabaseMissing('draft_orders', [
            'store_id' => $storeB->id,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('orders.notes.store', $order), ['body' => 'Leave at side door.'])
            ->assertRedirect();

        $this->assertDatabaseHas('order_events', [
            'store_id' => $storeA->id,
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_ORDER_NOTE_ADDED,
            'description' => 'Leave at side door.',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('customers.notes.store', $customer), ['body' => 'Prefers afternoon delivery.'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('customers.addresses.store', $customer), [
                'type' => 'shipping',
                'name' => 'Draft Buyer',
                'address_line1' => '99 Ops Street',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'is_default' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'address_line1' => '99 Ops Street',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeB->id])
            ->post(route('orders.notes.store', $order), ['body' => 'Cross-store note'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeB->id])
            ->post(route('customers.addresses.store', $customer), [
                'type' => 'shipping',
                'name' => 'Hijack',
                'address_line1' => 'Bad Street',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
            ])
            ->assertNotFound();

        $this->assertSame(0, CustomerAddress::query()->where('address_line1', 'Bad Street')->count());
    }

    public function test_owner_can_configure_manual_delivery_area_and_option(): void
    {
        $this->seed(CarrierSeeder::class);

        $owner = $this->merchant('dr06-delivery-owner@example.test');
        $store = $this->store($owner, 'DR06 Delivery Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.ship-from'), [
                'name' => 'DR06 Warehouse',
                'type' => 'warehouse',
                'address_line1' => '200 Lake St',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
                'country_code' => 'US',
                'fulfills_online_orders' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.deliver-to'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.deliver-to'), [
                'name' => 'Illinois delivery',
                'zone_editor_mode' => 'simple',
                'country_code' => 'US',
                'region_codes' => ['IL'],
                'postal_rules_json' => json_encode([['type' => 'prefix', 'value' => '606']]),
                'is_active' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.delivery-option'));

        $zone = ShippingZone::query()->where('store_id', $store->id)->where('name', 'Illinois delivery')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.delivery-option'), [
                'shipping_zone_id' => $zone->id,
                'name' => 'Standard delivery',
                'delivery_speed_label' => '3-5 business days',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '6.50',
                'available_to_customers' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.review'));

        $this->assertDatabaseHas('shipping_methods', [
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Standard delivery',
            'enabled_for_checkout' => 1,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup.review'))
            ->assertOk()
            ->assertSeeText('DR06 Warehouse')
            ->assertSeeText('Illinois delivery')
            ->assertSeeText('Standard delivery');
    }

    public function test_owner_manager_and_staff_keep_role_boundaries_across_two_stores(): void
    {
        $owner = $this->merchant('dr06-owner@example.test');
        $manager = $this->merchant('dr06-manager@example.test');
        $staff = $this->merchant('dr06-staff@example.test');
        $storeA = $this->store($owner, 'DR06 Store A');
        $storeB = $this->store($owner, 'DR06 Store B');
        $this->attach($storeA, $owner, Store::ROLE_OWNER);
        $this->attach($storeA, $manager, Store::ROLE_MANAGER);
        $this->attach($storeA, $staff, Store::ROLE_STAFF);
        $this->attach($storeB, $owner, Store::ROLE_OWNER);

        $productA = $this->product($storeA)[0];
        $productB = $this->product($storeB)[0];
        $customerA = Customer::query()->create([
            'store_id' => $storeA->id,
            'email' => 'buyer-a@example.test',
            'full_name' => 'Store A Buyer',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('products.create'))
            ->assertOk();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeB->id])
            ->get(route('products.edit', $productA))
            ->assertNotFound();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('products.edit', $productB))
            ->assertNotFound();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('products.import.create'))
            ->assertOk();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('products.import.create'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('customers.notes.store', $customerA), ['body' => 'Prefers afternoon delivery.'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeB->id])
            ->post(route('customers.notes.store', $customerA), ['body' => 'Cross store note'])
            ->assertNotFound();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('developer-storefront.settings'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('team-members.store'), [
                'name' => 'Second Staff',
                'email' => 'dr06-second-staff@example.test',
                'role' => Store::ROLE_STAFF,
            ])
            ->assertRedirect(route('team-members.index'));

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('team-members.store'), [
                'name' => 'Blocked Invite',
                'email' => 'dr06-blocked@example.test',
                'role' => Store::ROLE_STAFF,
            ])
            ->assertForbidden();
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store, int $stock = 5, float $price = 12): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'DR06 Product',
            'slug' => 'dr06-product-'.Str::random(6),
            'base_price' => $price,
            'sku' => 'DR06-'.Str::random(4),
            'product_type' => 'physical',
            ...ProductTypeBehavior::defaultColumnsFor('physical'),
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => $price,
            'stock' => $stock,
        ]);

        return [$product, $variant];
    }

    /**
     * @return array<string, mixed>
     */
    private function twoVariantPayload(string $name, int $stockS, int $stockM): array
    {
        return [
            'name' => $name,
            'description' => 'Two sizes.',
            'bulk_price' => 24.00,
            'sku' => 'DR06-'.strtoupper(Str::random(5)),
            'product_type' => 'physical',
            'bulk_stock' => 1,
            'stock_alert' => 1,
            'variation_types' => [
                [
                    'name' => 'Size',
                    'type' => 'select',
                    'options' => ['S', 'M'],
                ],
            ],
            'variants' => [
                [
                    'option_map' => ['0' => 0],
                    'stock' => $stockS,
                    'price' => 24.00,
                    'stock_alert' => 1,
                ],
                [
                    'option_map' => ['0' => 1],
                    'stock' => $stockM,
                    'price' => 24.00,
                    'stock_alert' => 1,
                ],
            ],
        ];
    }
}
