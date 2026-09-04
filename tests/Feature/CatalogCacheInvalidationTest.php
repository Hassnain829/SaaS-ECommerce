<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ConnectedSite;
use App\Models\ConnectedSiteEventDelivery;
use App\Models\ConnectedSiteOutboxEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\ConnectedSiteService;
use App\Support\CatalogRevision;
use App\Support\ConnectedSiteCatalogEvent;
use App\Support\ConnectedSiteEventSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_publish_update_unpublish_and_delete_write_store_scoped_outbox_events(): void
    {
        [, $store] = $this->connectedStore('Outbox Store');

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Event Shirt',
            'slug' => 'event-shirt-'.Str::random(6),
            'base_price' => 20,
            'sku' => 'EVT-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $this->assertSame(
            ConnectedSiteCatalogEvent::PRODUCT_PUBLISHED,
            ConnectedSiteOutboxEvent::query()->where('store_id', $store->id)->latest('id')->value('type')
        );

        $product->forceFill(['name' => 'Event Shirt Updated'])->save();
        $types = ConnectedSiteOutboxEvent::query()
            ->where('store_id', $store->id)
            ->pluck('type');
        $this->assertTrue($types->contains(ConnectedSiteCatalogEvent::PRODUCT_UPDATED));

        $product->forceFill(['status' => false])->save();
        $this->assertTrue(
            ConnectedSiteOutboxEvent::query()
                ->where('store_id', $store->id)
                ->where('type', ConnectedSiteCatalogEvent::PRODUCT_UNPUBLISHED)
                ->exists()
        );

        $product->delete();
        $this->assertTrue(
            ConnectedSiteOutboxEvent::query()
                ->where('store_id', $store->id)
                ->where('type', ConnectedSiteCatalogEvent::PRODUCT_DELETED)
                ->exists()
        );

        $other = Store::query()->create([
            'user_id' => $store->user_id,
            'name' => 'Other Outbox Store',
            'slug' => 'other-outbox-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $foreign = Product::query()->create([
            'store_id' => $other->id,
            'name' => 'Other Shirt',
            'slug' => 'other-shirt-'.Str::random(6),
            'base_price' => 10,
            'sku' => 'OTH-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $this->assertTrue(
            ConnectedSiteOutboxEvent::query()
                ->where('store_id', $other->id)
                ->where('type', ConnectedSiteCatalogEvent::PRODUCT_PUBLISHED)
                ->exists()
        );
        $this->assertFalse(
            ConnectedSiteOutboxEvent::query()
                ->where('store_id', $store->id)
                ->get()
                ->contains(fn (ConnectedSiteOutboxEvent $event): bool => (int) ($event->payload['product_id'] ?? 0) === (int) $foreign->id)
        );
    }

    public function test_variant_category_and_inventory_changes_emit_catalog_events(): void
    {
        [, $store] = $this->connectedStore('Variant Event Store');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Variant Event Shirt',
            'slug' => 'variant-event-'.Str::random(6),
            'base_price' => 18,
            'sku' => 'VES-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 18,
            'stock' => 4,
        ]);
        $this->assertTrue(
            ConnectedSiteOutboxEvent::query()
                ->where('store_id', $store->id)
                ->where('type', ConnectedSiteCatalogEvent::INVENTORY_AVAILABILITY_CHANGED)
                ->get()
                ->contains(fn (ConnectedSiteOutboxEvent $event): bool => (int) ($event->payload['variant_id'] ?? 0) === (int) $variant->id)
        );

        $variant->forceFill(['price' => 22])->save();
        $this->assertTrue(
            ConnectedSiteOutboxEvent::query()
                ->where('store_id', $store->id)
                ->where('type', ConnectedSiteCatalogEvent::VARIANT_UPDATED)
                ->get()
                ->contains(fn (ConnectedSiteOutboxEvent $event): bool => (int) ($event->payload['variant_id'] ?? 0) === (int) $variant->id)
        );

        $variant->forceFill(['stock' => 1])->save();
        $this->assertGreaterThan(
            1,
            ConnectedSiteOutboxEvent::query()
                ->where('store_id', $store->id)
                ->where('type', ConnectedSiteCatalogEvent::INVENTORY_AVAILABILITY_CHANGED)
                ->count()
        );

        Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Shirts',
            'slug' => 'shirts-'.Str::random(4),
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $this->assertTrue(
            ConnectedSiteOutboxEvent::query()
                ->where('store_id', $store->id)
                ->where('type', ConnectedSiteCatalogEvent::CATEGORY_UPDATED)
                ->exists()
        );
    }

    public function test_signed_delivery_posts_to_the_bound_site_and_failed_delivery_does_not_change_the_product(): void
    {
        config([
            'connected_sites.deliver_in_tests' => true,
            'connected_sites.allow_private_networks_non_production' => true,
        ]);
        Http::fake([
            'http://127.0.0.1:8080/wp-json/eco-portal/v1/events' => Http::sequence()
                ->push(['ok' => true], 200)
                ->push('WordPress is down', 503),
        ]);

        [, $store, $token, $site] = $this->connectedStore('Delivery Store');
        $this->assertNotSame('', (string) $site->event_signing_secret);

        $live = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Delivered Shirt',
            'slug' => 'delivered-'.Str::random(6),
            'base_price' => 15,
            'sku' => 'DEL-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $event = ConnectedSiteOutboxEvent::query()
            ->where('store_id', $store->id)
            ->where('type', ConnectedSiteCatalogEvent::PRODUCT_PUBLISHED)
            ->first();
        $this->assertNotNull($event);

        Http::assertSent(function ($request) use ($event, $site): bool {
            if ($request->url() !== 'http://127.0.0.1:8080/wp-json/eco-portal/v1/events') {
                return false;
            }

            $timestamp = (string) $request->header('X-Eco-Timestamp')[0];
            $signature = (string) $request->header('X-Eco-Signature')[0];
            $eventId = (string) $request->header('X-Eco-Event-Id')[0];

            return $eventId === $event->public_id
                && ConnectedSiteEventSignature::verify(
                    (string) $site->event_signing_secret,
                    $timestamp,
                    $eventId,
                    $request->body(),
                    $signature
                )
                && str_contains($request->body(), '"type":"product.published"');
        });

        $this->assertDatabaseHas('connected_site_event_deliveries', [
            'connected_site_id' => $site->id,
            'outbox_event_id' => $event->id,
            'status' => ConnectedSiteEventDelivery::STATUS_DELIVERED,
        ]);

        $live->forceFill(['name' => 'Still Authoritative'])->save();
        $this->assertSame('Still Authoritative', $live->fresh()->name);
        $this->assertTrue(
            ConnectedSiteEventDelivery::query()
                ->where('connected_site_id', $site->id)
                ->where('status', ConnectedSiteEventDelivery::STATUS_PENDING)
                ->exists()
        );

        $this->withToken($token)
            ->getJson('/api/v1/catalog/events')
            ->assertOk()
            ->assertJsonPath('data.0.type', ConnectedSiteCatalogEvent::PRODUCT_PUBLISHED)
            ->assertJsonPath('meta.catalog_version', CatalogRevision::forStore($store->fresh()));
    }

    public function test_event_poll_and_config_are_store_scoped_and_health_binds_catalog_sync(): void
    {
        [$owner, $storeA, $tokenA] = $this->connectedStore('Sync Store A', 'http://127.0.0.1:8080');
        [, $storeB, $tokenB] = $this->connectedStore('Sync Store B', 'http://127.0.0.1:8081');

        Product::query()->create([
            'store_id' => $storeA->id,
            'name' => 'Store A Event Product',
            'slug' => 'store-a-event-'.Str::random(6),
            'base_price' => 11,
            'sku' => 'SAE-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $config = $this->withToken($tokenA)
            ->getJson('/api/v1/site/events/config')
            ->assertOk()
            ->assertJsonPath('poll_path', '/api/v1/catalog/events');
        $secret = (string) $config->json('signing_secret');
        $this->assertStringStartsWith('csevtsec_', $secret);

        $eventsA = $this->withToken($tokenA)
            ->getJson('/api/v1/catalog/events')
            ->assertOk();
        $this->assertNotEmpty($eventsA->json('data'));

        $this->withToken($tokenB)
            ->getJson('/api/v1/catalog/events')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($tokenA)
            ->withHeaders([
                'X-Eco-Site-Url' => 'http://127.0.0.1:8080',
                'X-Eco-Plugin-Version' => '1.6.0',
            ])
            ->postJson('/api/v1/site/health', [
                'production_ready' => true,
                'conflicts' => [],
                'catalog_cache' => [
                    'version' => 'stale-version',
                    'last_event_id' => 'csevt_test',
                    'last_rebuild_at' => '2026-08-15T00:00:00+00:00',
                    'last_reconcile_at' => '2026-08-15T00:05:00+00:00',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('catalog_sync.site_cache_version', 'stale-version')
            ->assertJsonPath('catalog_sync.website_matches_portal', false)
            ->assertJsonPath('plugin.catalog_events', true);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSeeText('Product list is refreshing');
    }

    public function test_catalog_revision_changes_when_variant_stock_changes(): void
    {
        [, $store] = $this->connectedStore('Revision Store');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Revision Shirt',
            'slug' => 'revision-'.Str::random(6),
            'base_price' => 9,
            'sku' => 'REV-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 9,
            'stock' => 5,
        ]);

        $before = CatalogRevision::forStore($store->fresh());
        $variant->forceFill(['stock' => 2])->save();
        $after = CatalogRevision::forStore($store->fresh());

        $this->assertNotSame($before, $after);
    }

    public function test_wordpress_plugin_caches_public_catalog_only_and_verifies_signed_events(): void
    {
        $pluginDir = base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector');
        $plugin = file_get_contents($pluginDir.'/eco-portal-connector.php');
        $cache = file_get_contents($pluginDir.'/includes/class-catalog-cache.php');
        $events = file_get_contents($pluginDir.'/includes/class-events.php');
        $client = file_get_contents($pluginDir.'/includes/class-api-client.php');
        $admin = file_get_contents($pluginDir.'/includes/class-admin.php');
        $storefront = file_get_contents($pluginDir.'/includes/class-storefront.php');

        $this->assertStringContainsString("define('ECO_PORTAL_CONNECTOR_VERSION', '1.7.1')", $plugin);
        $this->assertStringContainsString('eco-portal/v1', $events);
        $this->assertStringContainsString('hash_hmac', $events);
        $this->assertStringContainsString('already_seen', $events);
        $this->assertStringContainsString('eco_portal_reconcile_catalog', $events);
        $this->assertStringContainsString("in_array(\$bucket, ['catalog', 'product', 'categories']", $cache);
        $this->assertStringNotContainsString('checkout', $cache);
        $this->assertStringNotContainsString('/api/v1/checkout', $cache);
        $this->assertStringContainsString('get_event_config', $client);
        $this->assertStringContainsString('get_catalog_events', $client);
        $this->assertStringContainsString('Eco_Portal_Catalog_Cache::get', $client);
        $this->assertStringContainsString('Rebuild catalog cache', $admin);
        $this->assertStringContainsString('Product list cache', $admin);
        $this->assertStringContainsString("update_option('eco_portal_event_secret'", $admin);
        $this->assertStringNotContainsString('esc_html((string) get_option(\'eco_portal_event_secret\'', $admin);
        $this->assertStringNotContainsString('Eco_Portal_Catalog_Cache', $storefront);
        $this->assertStringContainsString('create_checkout', $storefront);
    }

    /**
     * @return array{0: User, 1: Store, 2: string, 3: ConnectedSite}
     */
    private function connectedStore(string $name, string $url = 'http://127.0.0.1:8080'): array
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
        $issued = app(ConnectedSiteService::class)->issuePrimaryCredential($store);
        app(ConnectedSiteService::class)->bindWebsiteUrl($store, $url);

        return [$owner, $store->fresh(), $issued['plain'], $issued['site']->fresh()];
    }
}
