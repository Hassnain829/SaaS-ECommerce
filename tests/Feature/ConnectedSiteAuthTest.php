<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\ConnectedSite;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use App\Services\ConnectedSiteService;
use App\Support\ConnectedSiteScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConnectedSiteAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_a_token_cannot_read_store_b_catalog_or_checkout(): void
    {
        [, $storeA, $tokenA] = $this->connectedStore('Auth Store A');
        [, $storeB, $tokenB] = $this->connectedStore('Auth Store B');
        [$productA] = $this->product($storeA, 'Store A Shirt');
        [$productB] = $this->product($storeB, 'Store B Shirt');

        $this->withToken($tokenA)
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk()
            ->assertJsonPath('store.id', $storeA->id)
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $productA->id);

        $this->withToken($tokenA)
            ->getJson('/api/v1/catalog/products/'.$productB->id)
            ->assertNotFound();

        $this->withToken($tokenB)
            ->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $productB->id);

        $checkoutB = Checkout::query()->create([
            'store_id' => $storeB->id,
            'checkout_number' => 'CHK-B-'.Str::upper(Str::random(6)),
            'source_channel' => 'wordpress',
            'status' => Checkout::STATUS_PAYMENT_PENDING,
            'currency_code' => 'USD',
            'subtotal' => 12,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 12,
        ]);

        $this->withToken($tokenA)
            ->getJson('/api/v1/checkout/'.$checkoutB->id)
            ->assertNotFound();
    }

    public function test_revoked_credential_is_rejected_immediately(): void
    {
        [, $store, $token] = $this->connectedStore('Revoke Store');

        $this->withToken($token)
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk();

        app(ConnectedSiteService::class)->revokePrimary($store);

        $this->withToken($token)
            ->getJson('/api/developer-storefront/catalog')
            ->assertUnauthorized();

        $this->withToken($token)
            ->getJson('/api/v1/site/health')
            ->assertUnauthorized();
    }

    public function test_rotation_invalidates_the_previous_key(): void
    {
        [, $store, $oldToken] = $this->connectedStore('Rotate Store');

        $rotated = app(ConnectedSiteService::class)->issuePrimaryCredential($store);

        $this->withToken($oldToken)
            ->getJson('/api/developer-storefront/catalog')
            ->assertUnauthorized();

        $this->withToken($rotated['plain'])
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk();

        $this->assertTrue($rotated['rotated']);
        $this->assertNotNull($rotated['site']->credential_rotated_at);
    }

    public function test_health_endpoint_reports_store_url_match_and_readiness(): void
    {
        [, $store, $token, $site] = $this->connectedStore('Health Store');
        app(ConnectedSiteService::class)->bindWebsiteUrl($store, 'http://127.0.0.1:8080');
        $this->product($store, 'Health Product');

        $this->withToken($token)
            ->withHeaders([
                'X-Eco-Site-Url' => 'http://127.0.0.1:8080',
                'X-Eco-Plugin-Version' => '1.3.0',
            ])
            ->getJson('/api/v1/site/health')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('store.id', $store->id)
            ->assertJsonPath('store.name', 'Health Store')
            ->assertJsonPath('site.public_id', $site->public_id)
            ->assertJsonPath('site.url_match', true)
            ->assertJsonPath('plugin.reported_version', '1.3.0')
            ->assertJsonPath('plugin.compatible', true)
            ->assertJsonPath('readiness.catalog', true)
            ->assertJsonPath('credential.valid', true);

        $site->refresh();
        $this->assertSame('1.3.0', $site->plugin_version);
        $this->assertNotNull($site->last_seen_at);
        $this->assertNotNull($site->last_health_at);
        $this->assertTrue((bool) data_get($site->last_health, 'url_match'));
    }

    public function test_missing_scope_is_forbidden(): void
    {
        [, , $token, $site] = $this->connectedStore('Scope Store');

        $site->forceFill([
            'scopes' => [ConnectedSiteScope::CATALOG_READ, ConnectedSiteScope::SITE_HEALTH],
        ])->save();

        $this->withToken($token)
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/checkout', ['items' => []])
            ->assertForbidden()
            ->assertJsonPath('message', 'This connection is not allowed to perform that action.');
    }

    public function test_duplicate_wordpress_url_is_rejected_across_stores(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Url Store A');
        [$ownerB, $storeB] = $this->ownerStore('Url Store B');

        app(ConnectedSiteService::class)->issuePrimaryCredential($storeA);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'https://shop.example.test',
            ])
            ->assertRedirect(route('developer-storefront.settings'));

        $this->actingAs($ownerB)
            ->withSession(['current_store_id' => $storeB->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'https://shop.example.test',
            ])
            ->assertSessionHasErrors(['website_url']);
    }

    public function test_binding_and_clearing_a_url_updates_store_and_primary_site_together(): void
    {
        [, $store] = $this->ownerStore('Atomic Url Store');
        $service = app(ConnectedSiteService::class);
        $site = $service->issuePrimaryCredential($store)['site'];
        $url = 'https://atomic-url.example.com/shop';

        $service->bindWebsiteUrl($store, $url);

        $this->assertSame($url, $store->fresh()->connectedWebsiteUrl());
        $this->assertDatabaseHas('connected_sites', [
            'id' => $site->id,
            'site_url' => $url,
            'site_url_normalized' => $url,
            'active_site_url_key' => $url,
        ]);

        $service->bindWebsiteUrl($store, '');

        $settings = $store->fresh()->settings;
        $this->assertIsArray($settings);
        $this->assertArrayNotHasKey('connected_website_url', $settings);
        $this->assertDatabaseHas('connected_sites', [
            'id' => $site->id,
            'site_url' => null,
            'site_url_normalized' => null,
            'active_site_url_key' => null,
        ]);
    }

    public function test_expected_unique_race_returns_validation_and_rolls_back_store_and_site(): void
    {
        [$owner, $store] = $this->ownerStore('Atomic Race Store');
        $service = app(ConnectedSiteService::class);
        $site = $service->issuePrimaryCredential($store)['site'];
        $originalUrl = 'https://original-race.example.com';
        $racedUrl = 'https://raced-url.example.com';
        $service->bindWebsiteUrl($store, $originalUrl);

        DB::unprepared(
            'CREATE TRIGGER connected_site_unique_race BEFORE UPDATE ON connected_sites '
            ."WHEN NEW.active_site_url_key = '{$racedUrl}' "
            ."BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: connected_sites.active_site_url_key'); END"
        );

        try {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->patch(route('developer-storefront.website.update'), [
                    'website_url' => $racedUrl,
                ])
                ->assertSessionHasErrors([
                    'website_url' => 'That WordPress site is already connected to another store.',
                ]);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS connected_site_unique_race');
        }

        $this->assertSame($originalUrl, $store->fresh()->connectedWebsiteUrl());
        $this->assertDatabaseHas('connected_sites', [
            'id' => $site->id,
            'site_url' => $originalUrl,
            'site_url_normalized' => $originalUrl,
            'active_site_url_key' => $originalUrl,
        ]);
    }

    public function test_unrelated_database_failure_is_not_mislabeled_and_rolls_back_both_records(): void
    {
        [, $store] = $this->ownerStore('Atomic Failure Store');
        $service = app(ConnectedSiteService::class);
        $site = $service->issuePrimaryCredential($store)['site'];
        $originalUrl = 'https://original-failure.example.com';
        $failingUrl = 'https://unrelated-failure.example.com';
        $service->bindWebsiteUrl($store, $originalUrl);

        DB::unprepared(
            'CREATE TRIGGER connected_site_unrelated_failure BEFORE UPDATE ON connected_sites '
            ."WHEN NEW.active_site_url_key = '{$failingUrl}' "
            ."BEGIN SELECT RAISE(ABORT, 'simulated unrelated database failure'); END"
        );

        try {
            $service->bindWebsiteUrl($store, $failingUrl);
            $this->fail('An unrelated database failure was swallowed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated unrelated database failure', $exception->getMessage());
        } catch (ValidationException) {
            $this->fail('An unrelated database failure was mislabeled as a duplicate website URL.');
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS connected_site_unrelated_failure');
        }

        $this->assertSame($originalUrl, $store->fresh()->connectedWebsiteUrl());
        $this->assertDatabaseHas('connected_sites', [
            'id' => $site->id,
            'site_url' => $originalUrl,
            'site_url_normalized' => $originalUrl,
            'active_site_url_key' => $originalUrl,
        ]);
    }

    public function test_reactivating_a_credential_cannot_reclaim_a_url_now_owned_by_another_store(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Reactivation Store A');
        [, $storeB] = $this->ownerStore('Reactivation Store B');
        $service = app(ConnectedSiteService::class);
        $url = 'https://reactivation-owner.example.com';

        $siteA = $service->issuePrimaryCredential($storeA)['site'];
        $service->bindWebsiteUrl($storeA, $url);
        $service->revokePrimary($storeA);

        $service->bindWebsiteUrl($storeB, $url);
        $siteB = $service->issuePrimaryCredential($storeB)['site'];

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertSessionHasErrors([
                'website_url' => 'That WordPress site is already connected to another store.',
            ]);

        $this->assertDatabaseHas('connected_sites', [
            'id' => $siteA->id,
            'status' => ConnectedSite::STATUS_REVOKED,
            'active_site_url_key' => null,
        ]);
        $this->assertDatabaseHas('connected_sites', [
            'id' => $siteB->id,
            'status' => ConnectedSite::STATUS_ACTIVE,
            'active_site_url_key' => $url,
        ]);
    }

    public function test_reactivation_unique_race_returns_validation_and_keeps_the_site_revoked(): void
    {
        [$owner, $store] = $this->ownerStore('Reactivation Race Store');
        $service = app(ConnectedSiteService::class);
        $site = $service->issuePrimaryCredential($store)['site'];
        $url = 'https://reactivation-race.example.com';
        $service->bindWebsiteUrl($store, $url);
        $service->revokePrimary($store);
        $credentialHash = (string) $site->fresh()->getRawOriginal('credential_hash');

        DB::unprepared(
            'CREATE TRIGGER connected_site_reactivation_race BEFORE UPDATE ON connected_sites '
            ."WHEN NEW.status = 'active' AND NEW.active_site_url_key = '{$url}' "
            ."BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: connected_sites.active_site_url_key'); END"
        );

        try {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->post(route('developer-storefront.token.generate'))
                ->assertSessionHasErrors([
                    'website_url' => 'That WordPress site is already connected to another store.',
                ]);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS connected_site_reactivation_race');
        }

        $this->assertDatabaseHas('connected_sites', [
            'id' => $site->id,
            'credential_hash' => $credentialHash,
            'status' => ConnectedSite::STATUS_REVOKED,
            'active_site_url_key' => null,
        ]);
    }

    public function test_database_enforces_active_url_uniqueness_and_revocation_releases_the_url(): void
    {
        [, $storeA] = $this->ownerStore('Database Url Store A');
        [, $storeB] = $this->ownerStore('Database Url Store B');
        $service = app(ConnectedSiteService::class);
        $siteA = $service->issuePrimaryCredential($storeA)['site'];
        $siteB = $service->issuePrimaryCredential($storeB)['site'];
        $url = 'https://database-unique.example.com';

        $service->bindWebsiteUrl($storeA, $url);

        try {
            $siteB->forceFill([
                'site_url' => $url,
                'site_url_normalized' => $url,
            ])->save();
            $this->fail('The active connected-site URL database key accepted a duplicate.');
        } catch (QueryException) {
            $this->assertDatabaseHas('connected_sites', [
                'id' => $siteA->id,
                'active_site_url_key' => $url,
            ]);
        }

        $service->revokePrimary($storeA);
        $service->bindWebsiteUrl($storeB, $url);

        $this->assertDatabaseHas('connected_sites', [
            'id' => $siteA->id,
            'active_site_url_key' => null,
        ]);
        $this->assertDatabaseHas('connected_sites', [
            'id' => $siteB->id,
            'active_site_url_key' => $url,
        ]);
    }

    public function test_production_requires_https_for_every_connected_site_address(): void
    {
        [$owner, $store] = $this->ownerStore('Https Store');
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->patch(route('developer-storefront.website.update'), [
                    'website_url' => 'http://shop.example.com',
                ])
                ->assertSessionHasErrors(['website_url']);

            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->patch(route('developer-storefront.website.update'), [
                    'website_url' => 'https://shop.example.com',
                ])
                ->assertRedirect(route('developer-storefront.settings'));

            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->patch(route('developer-storefront.website.update'), [
                    'website_url' => 'http://localhost:8080',
                ])
                ->assertSessionHasErrors(['website_url']);
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_production_rejects_an_unbound_connected_site(): void
    {
        [, , $token] = $this->connectedStore('Unbound Production Site');
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->withToken($token)
                ->withHeaders(['X-Eco-Site-Url' => 'https://shop.example.com'])
                ->getJson('/api/developer-storefront/catalog')
                ->assertForbidden();
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_production_rejects_a_mismatched_site_url_header(): void
    {
        [, $store, $token] = $this->connectedStore('Bound Url Store');
        app(ConnectedSiteService::class)->bindWebsiteUrl($store, 'https://shop-a.example.com');

        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->withToken($token)
                ->withHeaders(['X-Eco-Site-Url' => 'https://shop-b.example.com'])
                ->getJson('/api/developer-storefront/catalog')
                ->assertForbidden();

            $this->withToken($token)
                ->withHeaders(['X-Eco-Site-Url' => 'https://shop-a.example.com'])
                ->getJson('/api/developer-storefront/catalog')
                ->assertOk();
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_failed_authentication_is_logged(): void
    {
        $this->withToken('baa_dev_not_a_real_key')
            ->getJson('/api/developer-storefront/catalog')
            ->assertUnauthorized();

        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'connected_site.auth_failed',
            'severity' => SecurityLog::SEVERITY_WARNING,
        ]);
    }

    public function test_connection_key_is_shown_once_then_removed_from_the_website_page(): void
    {
        [$owner, $store] = $this->ownerStore('Secret Flash Store');

        app(ConnectedSiteService::class)
            ->bindWebsiteUrl($store, 'http://localhost:8080/secret-flash');

        $generate = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'));

        $generate->assertRedirect(route('developer-storefront.settings'));
        $token = (string) $generate->getSession()->get('developer_storefront_plain_token');
        $this->assertNotSame('', $token);
        $this->assertStringStartsWith('baa_dev_', $token);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee($token);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertDontSee($token)
            ->assertDontSee(hash('sha256', $token));
    }

    public function test_wordpress_plugin_keeps_the_key_server_side(): void
    {
        $admin = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-admin.php'));
        $client = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-api-client.php'));
        $storefront = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-storefront.php'));
        $checkout = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/checkout.php'));
        $catalog = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/catalog.php'));
        $cart = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/cart.php'));

        $this->assertIsString($admin);
        $this->assertIsString($client);
        $this->assertIsString($storefront);
        $this->assertStringNotContainsString('esc_attr($token)', $admin);
        $this->assertStringContainsString('A key is already saved on this server', $admin);
        $this->assertStringContainsString('Eco_Portal_Conflicts::report', $admin);
        $this->assertStringNotContainsString('deactivate_plugins', $admin);
        $conflicts = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-conflicts.php'));
        $this->assertIsString($conflicts);
        $this->assertStringContainsString('woocommerce/woocommerce.php', $conflicts);
        $this->assertStringNotContainsString('deactivate_plugins', $conflicts);
        $this->assertStringNotContainsString('WC()', $conflicts);
        $this->assertStringContainsString('get_health', $client);
        $this->assertStringContainsString('X-Eco-Site-Url', $client);
        $this->assertStringContainsString('X-Eco-Plugin-Version', $client);
        $this->assertStringContainsString('/api/v1/site/health', $client);
        $this->assertStringNotContainsString('eco_portal_token', $storefront);
        $this->assertStringNotContainsString('eco_portal_token', $checkout);
        $this->assertStringNotContainsString('eco_portal_token', $catalog);
        $this->assertStringNotContainsString('eco_portal_token', $cart);
    }

    /**
     * @return array{0: User, 1: Store, 2: string, 3: ConnectedSite}
     */
    private function connectedStore(string $name): array
    {
        [$owner, $store] = $this->ownerStore($name);
        $issued = app(ConnectedSiteService::class)->issuePrimaryCredential($store);

        return [$owner, $store->fresh(), $issued['plain'], $issued['site']];
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'email' => str($name)->slug().'@example.com',
            'role_id' => $role->id,
        ]);
        $store = Store::query()->create([
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
        $store->members()->syncWithoutDetaching([
            $owner->id => ['role' => Store::ROLE_OWNER],
        ]);

        return [$owner, $store];
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store, string $name): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'base_price' => 12,
            'sku' => 'CS-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 12,
            'stock' => 5,
        ]);

        return [$product, $variant];
    }
}
