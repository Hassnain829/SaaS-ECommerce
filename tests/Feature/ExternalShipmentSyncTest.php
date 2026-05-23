<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalShipmentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_shipment_sync_requires_storefront_token(): void
    {
        $this->postJson('/api/v1/external/shipments', [])
            ->assertUnauthorized();
    }

    public function test_external_shipment_sync_is_idempotent_by_external_shipment_id(): void
    {
        [$store, $token] = $this->tokenedStore('Shipment Idempotent Store');
        [, $variant] = $this->product($store, ['sku' => 'TEE-01', 'variant_sku' => 'TEE-01-M']);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->orderPayload($variant, ['external_order_number' => 'WEB-SHIP-1']))
            ->assertCreated();

        $payload = [
            'external_order_number' => 'WEB-SHIP-1',
            'external_shipment_id' => 'SHIP-123',
            'status' => 'shipped',
            'carrier_name' => 'DHL',
            'tracking_number' => 'TRK-123',
            'tracking_url' => 'https://tracking.example.com/TRK-123',
            'items' => [
                ['sku' => 'TEE-01-M', 'quantity' => 1],
            ],
            'shipped_at' => now()->toISOString(),
        ];

        $this->withToken($token)
            ->postJson('/api/v1/external/shipments', $payload)
            ->assertCreated()
            ->assertJsonPath('shipment.external_shipment_id', 'SHIP-123');

        $this->assertSame(1, Shipment::query()->where('store_id', $store->id)->count());

        $this->withToken($token)
            ->postJson('/api/v1/external/shipments', array_merge($payload, [
                'tracking_number' => 'TRK-456',
                'status' => 'in_transit',
            ]))
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('shipment.tracking_number', 'TRK-456');

        $this->assertSame(1, Shipment::query()->where('store_id', $store->id)->count());
        $this->assertDatabaseHas('shipments', [
            'store_id' => $store->id,
            'tracking_number' => 'TRK-456',
            'status' => OrderLifecycle::SHIPMENT_IN_TRANSIT,
        ]);
    }

    public function test_external_shipment_sync_creates_order_event(): void
    {
        [$store, $token] = $this->tokenedStore('Shipment Event Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->orderPayload($variant, ['external_order_number' => 'WEB-SHIP-EVENT']))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/external/shipments', [
                'external_order_number' => 'WEB-SHIP-EVENT',
                'external_shipment_id' => 'SHIP-EVENT-1',
                'status' => 'shipped',
                'carrier_name' => 'FedEx',
                'tracking_number' => 'FX-1',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_EXTERNAL_SHIPMENT_UPDATED,
        ]);
    }

    public function test_external_shipment_sync_rejects_cross_store_order(): void
    {
        [$storeA, $tokenA] = $this->tokenedStore('Shipment Store A');
        [$storeB, $tokenB] = $this->tokenedStore('Shipment Store B');
        [, $variantA] = $this->product($storeA);

        $this->withToken($tokenA)
            ->postJson('/api/v1/external/orders', $this->orderPayload($variantA, ['external_order_number' => 'WEB-CROSS-A']))
            ->assertCreated();

        $this->withToken($tokenB)
            ->postJson('/api/v1/external/shipments', [
                'external_order_number' => 'WEB-CROSS-A',
                'external_shipment_id' => 'SHIP-CROSS',
                'status' => 'shipped',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_order_number']);
    }

    /**
     * @return array{0: Store, 1: string}
     */
    private function tokenedStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
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
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $token];
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'External Product',
            'slug' => 'external-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'EXT-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $overrides['variant_sku'] ?? $product->sku.'-D',
            'price' => $overrides['price'] ?? 12,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function orderPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'external_order_number' => 'WEB-'.Str::upper(Str::random(8)),
            'payment_status' => 'paid',
            'payment_gateway' => 'external_test',
            'payment_method' => 'card',
            'payment_reference' => 'pay-'.Str::random(8),
            'currency_code' => 'USD',
            'shipping_total' => 0,
            'customer' => [
                'full_name' => 'External Buyer',
                'email' => 'external.buyer@example.test',
            ],
            'shipping_address' => [
                'address_line1' => '45 External Road',
                'city' => 'Austin',
                'country' => 'US',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => '12.00',
                ],
            ],
        ], $overrides);
    }
}
