<?php

namespace Tests\Feature;

use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnterpriseQaExternalOrderDedupHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const IDENTITY_REQUIRED_MESSAGE = 'External order sync requires external_order_id or external_order_number. Idempotency-Key is supported as replay protection but cannot be the only order identity.';

    public function test_rejects_idempotency_key_only_external_order_creation(): void
    {
        [$store, $token] = $this->tokenedStore('QA 3C Idempotency Only Store');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $payload = $this->payload($variant);
        unset($payload['external_order_number'], $payload['external_order_id']);

        $this->withHeaders(['Idempotency-Key' => 'qa-idempotency-only-key'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_order'])
            ->assertJsonPath('errors.external_order.0', self::IDENTITY_REQUIRED_MESSAGE);

        $this->assertSame(0, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, InventoryReservation::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, StockMovement::query()->where('store_id', $store->id)->count());
        $this->assertSame(5, (int) $variant->fresh()->stock);
    }

    public function test_rejects_external_order_without_any_identity(): void
    {
        [$store, $token] = $this->tokenedStore('QA 3C No Identity Store');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $payload = $this->payload($variant);
        unset($payload['external_order_number'], $payload['external_order_id']);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_order'])
            ->assertJsonPath('errors.external_order.0', self::IDENTITY_REQUIRED_MESSAGE);

        $this->assertSame(0, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, InventoryReservation::query()->where('store_id', $store->id)->count());
        $this->assertSame(5, (int) $variant->fresh()->stock);
    }

    public function test_idempotency_key_with_external_order_id_prevents_duplicate_order(): void
    {
        [$store, $token] = $this->tokenedStore('QA 3C Idempotency With ID Store');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $payload = $this->identityPayload($variant, [
            'external_order_id' => 'ext-id-with-key-001',
        ]);

        $this->withHeaders(['Idempotency-Key' => 'qa-sync-key-with-id'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated();

        $this->withHeaders(['Idempotency-Key' => 'qa-sync-key-with-id'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.order_number', '#1001');

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(3, (int) $variant->fresh()->stock);
        $this->assertSame(1, StockMovement::query()
            ->where('store_id', $store->id)
            ->where('movement_type', StockMovement::TYPE_ORDER_DEDUCTED)
            ->count());
    }

    public function test_idempotency_key_with_external_order_number_prevents_duplicate_order(): void
    {
        [$store, $token] = $this->tokenedStore('QA 3C Idempotency With Number Store');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $payload = $this->payload($variant, ['external_order_number' => 'WEB-IDEMP-NUM-001']);

        $this->withHeaders(['Idempotency-Key' => 'qa-sync-key-with-number'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated();

        $this->withHeaders(['Idempotency-Key' => 'qa-sync-key-with-number'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.order_number', '#1001');

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(3, (int) $variant->fresh()->stock);
    }

    public function test_external_order_id_without_idempotency_key_is_accepted_and_deduped(): void
    {
        [$store, $token] = $this->tokenedStore('QA 3C External ID Dedup Store');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $payload = $this->identityPayload($variant, [
            'external_order_id' => 'ext-id-no-key-001',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('created', true);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('order.external_order_id', 'ext-id-no-key-001');

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(3, (int) $variant->fresh()->stock);
    }

    public function test_external_order_number_without_idempotency_key_is_accepted_and_deduped(): void
    {
        [$store, $token] = $this->tokenedStore('QA 3C External Number Dedup Store');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $payload = $this->payload($variant, ['external_order_number' => 'WEB-DEDUP-NO-KEY-001']);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertOk()
            ->assertJsonPath('created', false);

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(3, (int) $variant->fresh()->stock);
    }

    public function test_same_external_order_id_allowed_across_different_stores(): void
    {
        [$storeA, $tokenA] = $this->tokenedStore('QA 3C Store A ID');
        [$storeB, $tokenB] = $this->tokenedStore('QA 3C Store B ID');
        [, $variantA] = $this->product($storeA, ['stock' => 4]);
        [, $variantB] = $this->product($storeB, ['stock' => 4]);

        $sharedId = 'shared-ext-id-9001';

        $this->withToken($tokenA)
            ->postJson('/api/v1/external/orders', $this->identityPayload($variantA, [
                'external_order_id' => $sharedId,
            ]))
            ->assertCreated();

        $this->withToken($tokenB)
            ->postJson('/api/v1/external/orders', $this->identityPayload($variantB, [
                'external_order_id' => $sharedId,
            ]))
            ->assertCreated();

        $this->assertSame(1, Order::query()->where('store_id', $storeA->id)->count());
        $this->assertSame(1, Order::query()->where('store_id', $storeB->id)->count());
    }

    public function test_same_external_order_number_allowed_across_different_stores(): void
    {
        [$storeA, $tokenA] = $this->tokenedStore('QA 3C Store A Number');
        [$storeB, $tokenB] = $this->tokenedStore('QA 3C Store B Number');
        [, $variantA] = $this->product($storeA, ['stock' => 4]);
        [, $variantB] = $this->product($storeB, ['stock' => 4]);

        $sharedNumber = 'WEB-SHARED-NUM-9001';

        $this->withToken($tokenA)
            ->postJson('/api/v1/external/orders', $this->payload($variantA, [
                'external_order_number' => $sharedNumber,
            ]))
            ->assertCreated();

        $this->withToken($tokenB)
            ->postJson('/api/v1/external/orders', $this->payload($variantB, [
                'external_order_number' => $sharedNumber,
            ]))
            ->assertCreated();

        $this->assertSame(1, Order::query()->where('store_id', $storeA->id)->count());
        $this->assertSame(1, Order::query()->where('store_id', $storeB->id)->count());
    }

    public function test_external_owned_inventory_does_not_touch_platform_stock(): void
    {
        [$store, $token] = $this->tokenedStore('QA External Inventory Store', inventoryOwner: 'external');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_id' => 'ext-inv-001',
                'external_order_number' => 'WEB-EXT-INV-001',
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame(5, (int) $variant->fresh()->stock);
        $this->assertSame(0, InventoryReservation::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, StockMovement::query()->where('store_id', $store->id)->count());
        $this->assertNull(data_get($order->meta, 'fulfillment_routing'));
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_INVENTORY_EXTERNAL_MANAGED,
        ]);
    }

    public function test_platform_owned_inventory_deducts_and_routes_once_on_retry(): void
    {
        [$store, $token] = $this->tokenedStore('QA Platform Inventory Retry Store', inventoryOwner: 'platform');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $payload = $this->payload($variant, [
            'external_order_id' => 'ext-platform-retry-001',
            'external_order_number' => 'WEB-PLAT-RETRY-001',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertOk()
            ->assertJsonPath('created', false);

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(3, (int) $variant->fresh()->stock);
        $this->assertNotNull(data_get($order->meta, 'fulfillment_routing'));
        $this->assertSame(1, OrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderLifecycle::EVENT_ORDER_CREATED)
            ->count());
        $this->assertSame(1, OrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderLifecycle::EVENT_INVENTORY_DEDUCTED)
            ->count());
    }

    /**
     * @return array{0: Store, 1: string}
     */
    private function tokenedStore(string $name, ?string $inventoryOwner = null): array
    {
        $settings = [];
        if ($inventoryOwner !== null) {
            $settings['channels'] = [
                'external_checkout' => [
                    'inventory_owner' => $inventoryOwner,
                    'inventory_owner_configured' => true,
                ],
            ];
        }

        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => $settings,
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

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'QA Dedup Product',
            'slug' => 'qa-dedup-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'QA-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $overrides['variant_sku'] ?? $product->sku.'-D',
            'price' => $overrides['price'] ?? 12,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }

    /**
     * Payload with external_order_id only (no external_order_number).
     *
     * @return array<string, mixed>
     */
    private function identityPayload(ProductVariant $variant, array $overrides = []): array
    {
        $payload = $this->payload($variant, $overrides);
        unset($payload['external_order_number']);

        return $payload;
    }

    private function payload(ProductVariant $variant, array $overrides = []): array
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
                'full_name' => 'QA Dedup Buyer',
                'email' => 'qa.dedup.'.Str::random(6).'@example.test',
                'phone' => '+15550101',
            ],
            'shipping_address' => [
                'name' => 'QA Dedup Buyer',
                'address_line1' => '100 Dedup Lane',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'phone' => '+15550101',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => '12.00',
                ],
            ],
        ], $overrides);
    }
}
