<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\ConnectedSiteService;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\StoreMemberAccess;
use App\Support\StorePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantWebsiteConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_page_shows_three_setup_steps_and_hides_developer_scaffolding(): void
    {
        [$owner, $store] = $this->ownerStore('PKR Store', 'PKR');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSeeInOrder([
                'Connect your website',
                'Website address',
                'Connection key',
                'Connect your site',
                'Settings → Eco Portal',
            ])
            ->assertSee('Download plugin')
            ->assertSee('Custom website')
            ->assertDontSee('Local React test app')
            ->assertDontSee('VITE_API_BASE=')
            ->assertDontSee('VITE_CHECKOUT_API_BASE=')
            ->assertDontSee('VITE_STOREFRONT_TOKEN=')
            ->assertDontSee('Platform checkout sandbox')
            ->assertDontSee('Go live checklist')
            ->assertDontSee('Connect a React dev app')
            ->assertDontSee('Developer test storefront')
            ->assertDontSee('VITE_EXTERNAL_API_BASE=')
            ->assertDontSee('/api/v1/external/orders')
            ->content();

        $stepsPos = strpos($html, 'Website address');
        $wordpressPos = strpos($html, 'Settings → Eco Portal');

        $this->assertNotFalse($stepsPos);
        $this->assertNotFalse($wordpressPos);
        $this->assertLessThan($wordpressPos, $stepsPos);
    }

    public function test_staff_cannot_generate_or_revoke_the_connection_key(): void
    {
        $owner = $this->merchant('website-owner@example.com');
        $staff = $this->merchant('website-staff@example.com');
        $store = $this->store($owner, 'Staff Website Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('developer-storefront.token.revoke'))
            ->assertForbidden();
    }

    public function test_manager_can_view_website_page_but_cannot_generate_or_save_url(): void
    {
        $owner = $this->merchant('website-manager-owner@example.com');
        $manager = $this->merchant('website-manager@example.com');
        $store = $this->store($owner, 'Manager Website Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('You can view this step, but creating or replacing the connection key needs Website Edit access.');

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'http://127.0.0.1:8080',
            ])
            ->assertForbidden();
    }

    public function test_website_edit_access_can_complete_the_registration_flow(): void
    {
        $owner = $this->merchant('website-edit-owner@example.com');
        $member = $this->merchant('website-edit-member@example.com');
        $store = $this->store($owner, 'Edit Website Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, [
            'website.view',
            'website.manage',
            'website.plugin',
            'website.token',
        ]);

        $this->assertTrue($member->hasStorePermission($store, StorePermission::DEVELOPER_API_MANAGE));

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('Save address')
            ->assertDontSee('needs Website Edit access');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'http://127.0.0.1:8080',
            ])
            ->assertRedirect(route('developer-storefront.settings', ['step' => 2]));

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertRedirect();

        $this->assertTrue($store->fresh()->hasDeveloperStorefrontToken());

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.plugin.download'))
            ->assertOk()
            ->assertDownload('eco-portal-connector.zip');
    }

    public function test_website_view_only_cannot_run_registration_actions(): void
    {
        $owner = $this->merchant('website-view-owner@example.com');
        $member = $this->merchant('website-view-member@example.com');
        $store = $this->store($owner, 'View Website Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['website.view']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('needs Website Edit access')
            ->assertDontSee('Save address');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'http://127.0.0.1:8080',
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertForbidden();
    }

    public function test_owner_can_save_website_url_and_download_plugin(): void
    {
        [$owner, $store] = $this->ownerStore('Download Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'http://127.0.0.1:8080',
            ])
            ->assertRedirect(route('developer-storefront.settings', ['step' => 2]));

        $this->assertSame('http://127.0.0.1:8080', $store->fresh()->connectedWebsiteUrl());

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings', ['step' => 2]))
            ->assertOk()
            ->assertSee('Your connection key')
            ->assertDontSee('id="website_url"', false)
            ->assertSee('data-wc-locked="1"', false)
            ->getContent();

        $this->assertStringContainsString('data-wc-panel="2"', $html);
        $this->assertStringContainsString('<section class="wc-panel is-active" data-wc-panel="2">', $html);
        $this->assertStringNotContainsString('<section class="wc-panel is-active" data-wc-panel="1">', $html);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings', ['step' => 1]))
            ->assertOk()
            ->assertSee('http://127.0.0.1:8080')
            ->assertSee('Change address')
            ->assertDontSee('id="website_url"', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings', ['step' => 1, 'edit' => 1]))
            ->assertOk()
            ->assertSee('id="website_url"', false)
            ->assertSee('Save address');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.plugin.download'))
            ->assertOk()
            ->assertDownload('eco-portal-connector.zip');
    }

    public function test_creating_a_key_stays_on_step_two_so_the_merchant_can_copy_it(): void
    {
        [$owner, $store] = $this->ownerStore('Copy Key Store');
        app(ConnectedSiteService::class)
            ->bindWebsiteUrl($store, 'http://localhost:8080/copy-key');

        $generate = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertRedirect(route('developer-storefront.settings', ['step' => 2]));

        $token = (string) $generate->getSession()->get('developer_storefront_plain_token');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee($token)
            ->assertSee('Copy this key now')
            ->assertSee('Continue to connect your site')
            ->assertSee('Replace this connection key?')
            ->assertSee('Remove this connection key?')
            ->assertDontSee('onsubmit="return confirm', false)
            ->getContent();

        $this->assertStringContainsString('<section class="wc-panel is-active" data-wc-panel="2">', $html);
        $this->assertStringNotContainsString('<section class="wc-panel is-active" data-wc-panel="3">', $html);
        $this->assertStringContainsString('data-wc-locked="1"', $html);
    }

    public function test_connected_website_shows_manageable_settings_instead_of_the_setup_form(): void
    {
        [$owner, $store] = $this->ownerStore('Connected Manage Store');
        app(ConnectedSiteService::class)
            ->bindWebsiteUrl($store, 'http://localhost:8080/connected-manage');
        $token = app(ConnectedSiteService::class)->issuePrimaryCredential($store)['plain'];

        $this->withToken($token)
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSeeText('Your website is connected')
            ->assertSeeText('Change address')
            ->assertSeeText('Replace or remove key')
            ->assertSeeText('Need to reconnect WordPress or a custom site?')
            ->assertDontSee('id="website_url"', false);
    }

    public function test_owner_must_save_the_store_wordpress_address_before_creating_a_key(): void
    {
        [$owner, $store] = $this->ownerStore('Address First Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertSessionHasErrors([
                'website_url' => 'Save this store\'s exact WordPress website address before creating a connection key.',
            ]);

        $this->assertDatabaseMissing('connected_sites', [
            'store_id' => $store->id,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'http://localhost:8080/address-first',
            ])
            ->assertRedirect(route('developer-storefront.settings', ['step' => 2]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertRedirect(route('developer-storefront.settings', ['step' => 2]))
            ->assertSessionHas('developer_storefront_plain_token');

        $this->assertDatabaseHas('connected_sites', [
            'store_id' => $store->id,
            'site_url_normalized' => 'http://localhost:8080/address-first',
            'status' => 'active',
        ]);
    }

    public function test_one_owner_can_connect_different_wordpress_sites_to_different_selected_stores(): void
    {
        $owner = $this->merchant('multi-store-websites@example.com');
        $alpha = $this->store($owner, 'Website Alpha');
        $beta = $this->store($owner, 'Website Beta');
        $this->attach($alpha, $owner, Store::ROLE_OWNER);
        $this->attach($beta, $owner, Store::ROLE_OWNER);

        $tokens = [];
        foreach ([
            $alpha->id => 'http://localhost:8080/alpha',
            $beta->id => 'http://localhost:8080/beta',
        ] as $storeId => $websiteUrl) {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $storeId])
                ->patch(route('developer-storefront.website.update'), [
                    'website_url' => $websiteUrl,
                ])
                ->assertRedirect(route('developer-storefront.settings', ['step' => 2]));

            $response = $this->actingAs($owner)
                ->withSession(['current_store_id' => $storeId])
                ->post(route('developer-storefront.token.generate'))
                ->assertRedirect(route('developer-storefront.settings', ['step' => 2]));

            $tokens[$storeId] = (string) $response->getSession()->get('developer_storefront_plain_token');
        }

        $service = app(ConnectedSiteService::class);
        $this->assertSame($alpha->id, $service->resolveActiveByPlainToken($tokens[$alpha->id])?->store_id);
        $this->assertSame($beta->id, $service->resolveActiveByPlainToken($tokens[$beta->id])?->store_id);
        $this->assertNotSame($tokens[$alpha->id], $tokens[$beta->id]);
    }

    public function test_owner_can_move_one_wordpress_site_to_another_store_after_removing_the_old_key(): void
    {
        $owner = $this->merchant('move-website@example.com');
        $oldStore = $this->store($owner, 'Old Website Store');
        $newStore = $this->store($owner, 'New Website Store');
        $this->attach($oldStore, $owner, Store::ROLE_OWNER);
        $this->attach($newStore, $owner, Store::ROLE_OWNER);
        $websiteUrl = 'http://localhost:8080/movable-site';

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $oldStore->id])
            ->patch(route('developer-storefront.website.update'), ['website_url' => $websiteUrl]);
        $oldResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $oldStore->id])
            ->post(route('developer-storefront.token.generate'));
        $oldToken = (string) $oldResponse->getSession()->get('developer_storefront_plain_token');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $oldStore->id])
            ->delete(route('developer-storefront.token.revoke'))
            ->assertRedirect(route('developer-storefront.settings', ['step' => 2]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $newStore->id])
            ->patch(route('developer-storefront.website.update'), ['website_url' => $websiteUrl])
            ->assertRedirect(route('developer-storefront.settings', ['step' => 2]));
        $newResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $newStore->id])
            ->post(route('developer-storefront.token.generate'));
        $newToken = (string) $newResponse->getSession()->get('developer_storefront_plain_token');

        $service = app(ConnectedSiteService::class);
        $this->assertNull($service->resolveActiveByPlainToken($oldToken));
        $this->assertSame($newStore->id, $service->resolveActiveByPlainToken($newToken)?->store_id);
    }

    public function test_catalog_request_stamps_last_seen_and_page_shows_connected_state(): void
    {
        [$owner, $store, $token] = $this->tokenedStore('Last Seen Store');

        $this->assertNull($store->fresh()->developer_storefront_last_seen_at);
        $this->assertSame(Store::WEBSITE_WAITING, $store->fresh()->websiteConnectionState());

        $this->withToken($token)
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk();

        $store->refresh();
        $this->assertNotNull($store->developer_storefront_last_seen_at);
        $this->assertSame(Store::WEBSITE_CONNECTED, $store->websiteConnectionState());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('Connected')
            ->assertSee('Last contact');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->getJson(route('developer-storefront.status'))
            ->assertOk()
            ->assertJsonPath('state', Store::WEBSITE_CONNECTED)
            ->assertJsonPath('label', 'Connected')
            ->assertJsonPath('steps_done.3', true);
    }

    public function test_external_order_endpoint_is_not_available(): void
    {
        [, $store, $token] = $this->tokenedStore('Currency Store', 'PKR');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->externalPayload($variant, [
                'currency_code' => 'USD',
            ]))
            ->assertNotFound();
    }

    public function test_wordpress_checkout_template_uses_store_currency_instead_of_hardcoded_usd(): void
    {
        $checkout = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/templates/checkout.php'));

        $this->assertIsString($checkout);
        $this->assertStringNotContainsString('value="USD"', $checkout);
        $this->assertStringContainsString('$currency', $checkout);
        $this->assertStringContainsString('eco_portal_start_checkout', $checkout);
        $this->assertStringContainsString('choose delivery', $checkout);
        $this->assertStringNotContainsString('Place order & sync to portal', $checkout);

        $client = file_get_contents(base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector/includes/class-api-client.php'));
        $this->assertIsString($client);
        $this->assertStringContainsString('/api/v1/checkout', $client);
        $this->assertStringContainsString('delivery-options', $client);
        $this->assertStringContainsString('X-Eco-Site-Url', $client);
        $this->assertStringContainsString('/api/v1/site/health', $client);
    }

    public function test_generating_a_key_creates_a_primary_connected_site(): void
    {
        [$owner, $store] = $this->ownerStore('Connected Site Store');

        app(ConnectedSiteService::class)
            ->bindWebsiteUrl($store, 'http://localhost:8080/connected-site');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertRedirect(route('developer-storefront.settings', ['step' => 2]))
            ->assertSessionHas('developer_storefront_plain_token');

        $this->assertDatabaseHas('connected_sites', [
            'store_id' => $store->id,
            'is_primary' => 1,
            'status' => 'active',
        ]);

        $this->assertNull($store->fresh()->developer_storefront_token_hash);
        $this->assertNull($store->fresh()->developer_storefront_token_created_at);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name, string $currency = 'USD'): array
    {
        $owner = $this->merchant(str($name)->slug().'@example.com');
        $store = $this->store($owner, $name, $currency);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        return [$owner, $store];
    }

    /**
     * @return array{0: User, 1: Store, 2: string}
     */
    private function tokenedStore(string $name, string $currency = 'USD'): array
    {
        [$owner, $store] = $this->ownerStore($name, $currency);
        $token = app(ConnectedSiteService::class)->issuePrimaryCredential($store)['plain'];

        return [$owner, $store->fresh(), $token];
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name, string $currency = 'USD'): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => $currency,
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
     * @param  list<string>  $permissions
     */
    private function grant(Store $store, User $user, array $permissions): void
    {
        $this->attach($store, $user, Store::ROLE_MEMBER);

        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $user,
            $permissions,
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Website Product',
            'slug' => 'website-product-'.Str::random(6),
            'base_price' => 12,
            'sku' => 'WEB-'.Str::random(4),
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

    private function externalPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'external_order_number' => 'WEB-'.Str::upper(Str::random(8)),
            'external_checkout_reference' => 'checkout-'.Str::random(8),
            'payment_status' => 'paid',
            'payment_gateway' => 'external_test',
            'payment_method' => 'card',
            'payment_reference' => 'pay-'.Str::random(8),
            'placed_at' => now()->toISOString(),
            'currency_code' => 'USD',
            'shipping_total' => 4.50,
            'tax_total' => 1.50,
            'discount_total' => 0,
            'customer' => [
                'full_name' => 'Website Buyer',
                'email' => 'website.buyer@example.test',
                'phone' => '+15550199',
            ],
            'shipping_address' => [
                'name' => 'Website Buyer',
                'address_line1' => '45 Website Road',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
            ],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 12,
                ],
            ],
        ], $overrides);
    }
}
