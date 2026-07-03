<?php

namespace Tests\Feature;

use App\Http\Controllers\Settings\TaxSettingsController;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\Tax\TaxConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TaxSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const BASE_MIDDLEWARE = [
        'auth',
        'role:user',
        'current.store',
    ];

    /** @var list<array{name: string, uri: string, methods: list<string>, middleware: list<string>, controller: class-string, action: string}> */
    private const TAX_ROUTE_CONTRACTS = [
        [
            'name' => 'settings.taxes.index',
            'uri' => 'settings/taxes',
            'methods' => ['GET'],
            'middleware' => [...self::BASE_MIDDLEWARE, 'store.permission:settings.view'],
            'controller' => TaxSettingsController::class,
            'action' => 'index',
        ],
        [
            'name' => 'settings.taxes.update',
            'uri' => 'settings/taxes',
            'methods' => ['PUT'],
            'middleware' => [...self::BASE_MIDDLEWARE, 'store.permission:settings.manage'],
            'controller' => TaxSettingsController::class,
            'action' => 'update',
        ],
        [
            'name' => 'settings.taxes.rates.store',
            'uri' => 'settings/taxes/rates',
            'methods' => ['POST'],
            'middleware' => [...self::BASE_MIDDLEWARE, 'store.permission:settings.manage'],
            'controller' => TaxSettingsController::class,
            'action' => 'storeRate',
        ],
        [
            'name' => 'settings.taxes.rates.update',
            'uri' => 'settings/taxes/rates/{taxRate}',
            'methods' => ['PATCH'],
            'middleware' => [...self::BASE_MIDDLEWARE, 'store.permission:settings.manage'],
            'controller' => TaxSettingsController::class,
            'action' => 'updateRate',
        ],
        [
            'name' => 'settings.taxes.rates.destroy',
            'uri' => 'settings/taxes/rates/{taxRate}',
            'methods' => ['DELETE'],
            'middleware' => [...self::BASE_MIDDLEWARE, 'store.permission:settings.manage'],
            'controller' => TaxSettingsController::class,
            'action' => 'destroyRate',
        ],
    ];

    public function test_owner_can_view_tax_settings(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('Taxes', false);
    }

    public function test_manager_can_view_tax_settings(): void
    {
        [$owner, $store, $manager] = $this->teamStoreFixture();

        $this->actingAsStore($manager, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('Tax status', false);
    }

    public function test_staff_can_view_tax_settings(): void
    {
        [$owner, $store, $manager, $staff] = $this->teamStoreFixture(includeStaff: true);

        $this->actingAsStore($staff, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('Tax rates', false);
    }

    public function test_guest_is_redirected_when_viewing_tax_settings(): void
    {
        $this->get(route('settings.taxes.index'))
            ->assertRedirect(route('signin'));
    }

    public function test_non_member_receives_not_found_when_mutating_cross_store_tax_rate(): void
    {
        [$ownerA, $storeA] = $this->ownerStoreFixture('Owner A', 'Store Alpha Tax');
        [$ownerB, $storeB] = $this->ownerStoreFixture('Owner B', 'Store Beta Tax');

        $foreignRate = $this->createRate($storeB, ['name' => 'Foreign Rate']);

        $this->actingAsStore($ownerA, $storeA)
            ->patch(route('settings.taxes.rates.update', $foreignRate), $this->storeRatePayload())
            ->assertNotFound();

        $this->actingAsStore($ownerA, $storeA)
            ->delete(route('settings.taxes.rates.destroy', $foreignRate))
            ->assertNotFound();
    }

    public function test_manager_cannot_update_tax_settings(): void
    {
        [$owner, $store, $manager] = $this->teamStoreFixture();

        $this->actingAsStore($manager, $store)
            ->put(route('settings.taxes.update'), $this->settingsPayload(['enabled' => 1]))
            ->assertForbidden();
    }

    public function test_staff_cannot_update_tax_settings(): void
    {
        [$owner, $store, $manager, $staff] = $this->teamStoreFixture(includeStaff: true);

        $this->actingAsStore($staff, $store)
            ->put(route('settings.taxes.update'), $this->settingsPayload(['enabled' => 1]))
            ->assertForbidden();
    }

    public function test_manager_cannot_create_tax_rate(): void
    {
        [$owner, $store, $manager] = $this->teamStoreFixture();

        $this->actingAsStore($manager, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload())
            ->assertForbidden();
    }

    public function test_staff_cannot_create_tax_rate(): void
    {
        [$owner, $store, $manager, $staff] = $this->teamStoreFixture(includeStaff: true);

        $this->actingAsStore($staff, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload())
            ->assertForbidden();
    }

    public function test_manager_cannot_update_tax_rate(): void
    {
        [$owner, $store, $manager] = $this->teamStoreFixture();
        $rate = $this->createRate($store);

        $this->actingAsStore($manager, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload(['name' => 'Blocked Update']))
            ->assertForbidden();
    }

    public function test_staff_cannot_delete_tax_rate(): void
    {
        [$owner, $store, $manager, $staff] = $this->teamStoreFixture(includeStaff: true);
        $rate = $this->createRate($store);

        $this->actingAsStore($staff, $store)
            ->delete(route('settings.taxes.rates.destroy', $rate))
            ->assertForbidden();
    }

    public function test_cross_store_rates_are_not_visible_on_tax_settings_page(): void
    {
        [$ownerA, $storeA] = $this->ownerStoreFixture('Owner A', 'Visible Store A');
        [$ownerB, $storeB] = $this->ownerStoreFixture('Owner B', 'Hidden Store B');

        $hiddenName = 'ZZZ-Hidden-Store-B-Only-Rate-'.fake()->unique()->numberBetween(1000, 9999);
        $this->createRate($storeB, ['name' => $hiddenName]);

        $this->actingAsStore($ownerA, $storeA)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertDontSee($hiddenName, false);
    }

    public function test_new_store_receives_tax_setting_with_defaults(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->assertDatabaseCount('tax_settings', 1);

        $settings = $store->fresh()->taxSetting;
        $this->assertNotNull($settings);
        $this->assertSame($store->id, $settings->store_id);
        $this->assertFalse($settings->enabled);
        $this->assertFalse($settings->prices_include_tax);
        $this->assertTrue($settings->default_product_taxable);
        $this->assertFalse($settings->shipping_taxable);
        $this->assertSame(TaxSetting::CALCULATION_ADDRESS_SHIPPING, $settings->calculation_address);
        $this->assertSame(1, $settings->settings_version);
    }

    public function test_ensure_settings_for_store_is_idempotent(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $service = app(TaxConfigurationService::class);

        $first = $service->ensureSettingsForStore($store);
        $second = $service->ensureSettingsForStore($store);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TaxSetting::query()->where('store_id', $store->id)->count());
    }

    public function test_ensure_settings_for_store_does_not_change_existing_settings(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $settings = $store->taxSetting;
        $settings->update([
            'enabled' => true,
            'prices_include_tax' => true,
            'default_product_taxable' => false,
            'shipping_taxable' => true,
            'settings_version' => 7,
        ]);

        app(TaxConfigurationService::class)->ensureSettingsForStore($store->fresh());

        $settings->refresh();
        $this->assertTrue($settings->enabled);
        $this->assertTrue($settings->prices_include_tax);
        $this->assertFalse($settings->default_product_taxable);
        $this->assertTrue($settings->shipping_taxable);
        $this->assertSame(7, $settings->settings_version);
    }

    public function test_owner_can_update_tax_settings(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->put(route('settings.taxes.update'), $this->settingsPayload(['enabled' => 1]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Tax settings updated.');

        $settings = $store->fresh()->taxSetting;
        $this->assertTrue($settings->enabled);
        $this->assertFalse($settings->prices_include_tax);
        $this->assertTrue($settings->default_product_taxable);
        $this->assertFalse($settings->shipping_taxable);
        $this->assertSame(TaxSetting::CALCULATION_ADDRESS_SHIPPING, $settings->calculation_address);
    }

    public function test_unsupported_calculation_address_rejected_via_http_without_side_effects(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $settings = $store->taxSetting;

        $this->actingAsStore($owner, $store)
            ->put(route('settings.taxes.update'), $this->settingsPayload(['calculation_address' => 'billing']))
            ->assertSessionHasErrors('calculation_address');

        $settings->refresh();
        $this->assertFalse($settings->enabled);
        $this->assertFalse($settings->prices_include_tax);
        $this->assertTrue($settings->default_product_taxable);
        $this->assertFalse($settings->shipping_taxable);
        $this->assertSame(TaxSetting::CALCULATION_ADDRESS_SHIPPING, $settings->calculation_address);
        $this->assertSame(1, $settings->settings_version);

        $this->assertDatabaseMissing('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'tax.settings.updated',
        ]);
    }

    public function test_tax_settings_update_increments_settings_version(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $this->assertSame(1, $store->taxSetting->settings_version);

        $this->actingAsStore($owner, $store)
            ->put(route('settings.taxes.update'), $this->settingsPayload(['enabled' => 1]))
            ->assertRedirect();

        $this->assertSame(2, $store->fresh()->taxSetting->settings_version);
    }

    public function test_tax_settings_validation_failure_does_not_increment_settings_version(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload(['rate_percent' => '-1']))
            ->assertSessionHasErrors('rate_percent', null, 'createTaxRate');

        $this->assertSame(1, $store->fresh()->taxSetting->settings_version);
    }

    public function test_tax_settings_no_op_update_does_not_increment_settings_version(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->put(route('settings.taxes.update'), $this->settingsPayload())
            ->assertRedirect();

        $this->assertSame(1, $store->fresh()->taxSetting->settings_version);
    }

    public function test_owner_can_create_country_wide_tax_rate(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload([
                'name' => 'US Country Wide',
                'region_code' => '',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Tax rate added.');

        $rate = TaxRate::query()->forStore($store->id)->first();
        $this->assertNotNull($rate);
        $this->assertSame('US Country Wide', $rate->name);
        $this->assertSame('US', $rate->country_code);
        $this->assertSame('', $rate->region_code);
    }

    public function test_tax_rate_normalizes_blank_region_to_country_wide(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload([
                'name' => 'Blank Region Rate',
                'region_code' => '   ',
            ]))
            ->assertRedirect();

        $rate = TaxRate::query()->forStore($store->id)->firstOrFail();
        $this->assertSame('', $rate->region_code);
    }

    public function test_tax_rate_normalizes_country_and_region_to_uppercase(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload([
                'country_code' => 'us',
                'region_code' => ' ca ',
            ]))
            ->assertRedirect();

        $rate = TaxRate::query()->forStore($store->id)->firstOrFail();
        $this->assertSame('US', $rate->country_code);
        $this->assertSame('CA', $rate->region_code);
    }

    public function test_tax_rate_create_increments_settings_version(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload())
            ->assertRedirect();

        $this->assertSame(2, $store->fresh()->taxSetting->settings_version);
    }

    public function test_tax_rate_rejects_duplicate_country_and_region(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $this->createRate($store, $this->storeRatePayload());

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload(['name' => 'Duplicate CA Sales']))
            ->assertSessionHasErrors('country_code', null, 'createTaxRate');
    }

    public function test_same_jurisdiction_allowed_across_stores(): void
    {
        [$ownerA, $storeA] = $this->ownerStoreFixture('Owner A', 'Jurisdiction Store A');
        [$ownerB, $storeB] = $this->ownerStoreFixture('Owner B', 'Jurisdiction Store B');

        $this->actingAsStore($ownerA, $storeA)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload(['name' => 'Store A CA Sales']))
            ->assertRedirect();

        $this->actingAsStore($ownerB, $storeB)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload(['name' => 'Store B CA Sales']))
            ->assertRedirect();

        $this->assertSame(1, TaxRate::query()->forStore($storeA->id)->count());
        $this->assertSame(1, TaxRate::query()->forStore($storeB->id)->count());
    }

    public function test_tax_rate_rejects_rate_below_zero(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload(['rate_percent' => '-0.01']))
            ->assertSessionHasErrors('rate_percent', null, 'createTaxRate');
    }

    public function test_tax_rate_rejects_rate_above_one_hundred(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload(['rate_percent' => '100.0001']))
            ->assertSessionHasErrors('rate_percent', null, 'createTaxRate');
    }

    public function test_tax_rate_rejects_invalid_country_code(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload(['country_code' => 'USA']))
            ->assertSessionHasErrors('country_code', null, 'createTaxRate');
    }

    public function test_tax_rate_update_increments_settings_version(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload(['name' => 'Updated CA Sales']))
            ->assertRedirect();

        $this->assertSame(2, $store->fresh()->taxSetting->settings_version);
        $this->assertSame('Updated CA Sales', $rate->fresh()->name);
    }

    public function test_tax_rate_toggle_increments_settings_version(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, ['is_active' => true]);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload(['is_active' => 0]))
            ->assertRedirect();

        $this->assertSame(2, $store->fresh()->taxSetting->settings_version);
        $this->assertFalse($rate->fresh()->is_active);
    }

    public function test_tax_rate_delete_increments_settings_version(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store);

        $this->actingAsStore($owner, $store)
            ->delete(route('settings.taxes.rates.destroy', $rate))
            ->assertRedirect()
            ->assertSessionHas('success', 'Tax rate removed.');

        $this->assertSame(2, $store->fresh()->taxSetting->settings_version);
        $this->assertDatabaseMissing('tax_rates', ['id' => $rate->id]);
    }

    public function test_owner_can_create_active_tax_rate_when_checkbox_checked(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload([
                'name' => 'Active GB Sales',
                'country_code' => 'GB',
                'region_code' => '',
                'is_active' => 1,
            ]))
            ->assertRedirect();

        $rate = TaxRate::query()->forStore($store->id)->where('name', 'Active GB Sales')->firstOrFail();
        $this->assertTrue($rate->is_active);
        $this->assertSame(2, $store->fresh()->taxSetting->settings_version);
    }

    public function test_owner_can_create_inactive_tax_rate_when_checkbox_unchecked(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload([
                'name' => 'Inactive DE Sales',
                'country_code' => 'DE',
                'region_code' => '',
                'is_active' => 0,
            ]))
            ->assertRedirect();

        $rate = TaxRate::query()->forStore($store->id)->where('name', 'Inactive DE Sales')->firstOrFail();
        $this->assertFalse($rate->is_active);
        $this->assertSame(2, $store->fresh()->taxSetting->settings_version);
    }

    public function test_tax_settings_index_performs_no_writes(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $settingsCountBefore = TaxSetting::query()->count();
        $ratesCountBefore = TaxRate::query()->count();

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk();

        $this->assertSame($settingsCountBefore, TaxSetting::query()->count());
        $this->assertSame($ratesCountBefore, TaxRate::query()->count());
    }

    public function test_tax_settings_index_does_not_recreate_missing_tax_setting(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $store->taxSetting()->delete();

        $response = $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'));

        $response->assertStatus(503);
        $this->assertDatabaseCount('tax_settings', 0);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('tax_settings', $response->getContent());
    }

    public function test_tax_rate_decimal_equivalent_update_is_no_op_without_security_log(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Decimal No-Op CA',
            'rate_percent' => '8.2500',
        ]);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'name' => 'Decimal No-Op CA',
                'rate_percent' => '8.25',
            ]))
            ->assertRedirect();

        $this->assertSame(1, $store->fresh()->taxSetting->settings_version);

        $this->assertDatabaseMissing('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'tax.rate.updated',
        ]);
    }

    public function test_tax_rate_no_op_update_does_not_increment_settings_version(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $payload = $this->storeRatePayload([
            'name' => 'Stable CA Sales',
            'rate_percent' => '8.2500',
        ]);
        $rate = $this->createRate($store, $payload);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $payload)
            ->assertRedirect();

        $this->assertSame(1, $store->fresh()->taxSetting->settings_version);
    }

    public function test_cross_store_tax_rate_update_returns_not_found(): void
    {
        [$ownerA, $storeA] = $this->ownerStoreFixture('Owner A', 'Update Scope A');
        [$ownerB, $storeB] = $this->ownerStoreFixture('Owner B', 'Update Scope B');
        $foreignRate = $this->createRate($storeB);

        $this->actingAsStore($ownerA, $storeA)
            ->patch(route('settings.taxes.rates.update', $foreignRate), $this->storeRatePayload(['name' => 'Illegal Update']))
            ->assertNotFound();
    }

    public function test_cross_store_tax_rate_delete_returns_not_found(): void
    {
        [$ownerA, $storeA] = $this->ownerStoreFixture('Owner A', 'Delete Scope A');
        [$ownerB, $storeB] = $this->ownerStoreFixture('Owner B', 'Delete Scope B');
        $foreignRate = $this->createRate($storeB);

        $this->actingAsStore($ownerA, $storeA)
            ->delete(route('settings.taxes.rates.destroy', $foreignRate))
            ->assertNotFound();

        $this->assertDatabaseHas('tax_rates', ['id' => $foreignRate->id]);
    }

    public function test_tax_settings_update_writes_security_log(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->put(route('settings.taxes.update'), $this->settingsPayload(['enabled' => 1]))
            ->assertRedirect();

        $log = SecurityLog::query()
            ->where('store_id', $store->id)
            ->where('user_id', $owner->id)
            ->where('event_type', 'tax.settings.updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(2, $log->metadata['settings_version']);
        $this->assertTrue($log->metadata['enabled']);
        $this->assertFalse($log->metadata['prices_include_tax']);
        $this->assertTrue($log->metadata['default_product_taxable']);
        $this->assertFalse($log->metadata['shipping_taxable']);
    }

    public function test_tax_rate_create_writes_security_log(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload())
            ->assertRedirect();

        $rate = TaxRate::query()->forStore($store->id)->firstOrFail();

        $log = SecurityLog::query()
            ->where('store_id', $store->id)
            ->where('user_id', $owner->id)
            ->where('event_type', 'tax.rate.created')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(2, $log->metadata['settings_version']);
        $this->assertSame($rate->id, $log->metadata['tax_rate_id']);
        $this->assertSame('US', $log->metadata['country_code']);
        $this->assertSame('CA', $log->metadata['region_code']);
        $this->assertTrue($log->metadata['is_active']);
    }

    public function test_tax_rate_update_writes_security_log(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload(['name' => 'Logged Update']))
            ->assertRedirect();

        $log = SecurityLog::query()
            ->where('store_id', $store->id)
            ->where('user_id', $owner->id)
            ->where('event_type', 'tax.rate.updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(2, $log->metadata['settings_version']);
        $this->assertSame($rate->id, $log->metadata['tax_rate_id']);
        $this->assertSame('US', $log->metadata['country_code']);
        $this->assertSame('CA', $log->metadata['region_code']);
        $this->assertTrue($log->metadata['is_active']);
    }

    public function test_tax_rate_delete_writes_security_log(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'country_code' => 'US',
            'region_code' => 'NY',
            'name' => 'NY Sales',
        ]);

        $this->actingAsStore($owner, $store)
            ->delete(route('settings.taxes.rates.destroy', $rate))
            ->assertRedirect();

        $log = SecurityLog::query()
            ->where('store_id', $store->id)
            ->where('user_id', $owner->id)
            ->where('event_type', 'tax.rate.deleted')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(2, $log->metadata['settings_version']);
        $this->assertSame($rate->id, $log->metadata['tax_rate_id']);
        $this->assertSame('US', $log->metadata['country_code']);
        $this->assertSame('NY', $log->metadata['region_code']);
        $this->assertTrue($log->metadata['is_active']);
    }

    public function test_owner_sees_tax_settings_and_rate_forms(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('Save tax settings', false)
            ->assertSee('Add tax rate', false)
            ->assertSee(route('settings.taxes.update'), false)
            ->assertSee(route('settings.taxes.rates.store'), false);
    }

    public function test_manager_and_staff_do_not_see_tax_mutation_forms(): void
    {
        [$owner, $store, $manager, $staff] = $this->teamStoreFixture(includeStaff: true);

        $this->actingAsStore($manager, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertDontSee('Save tax settings', false)
            ->assertDontSee('Add tax rate', false)
            ->assertSee('Only the store owner can change tax settings.', false);

        $this->actingAsStore($staff, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertDontSee('Save tax settings', false)
            ->assertDontSee('Add tax rate', false)
            ->assertSee('Only the store owner can change tax settings.', false);
    }

    public function test_tax_settings_page_shows_empty_state_message(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('No tax rates yet', false)
            ->assertSee('Add a country-wide rate or a region-specific rate', false);
    }

    public function test_tax_settings_page_shows_disclaimer_text(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('These are basic configurable tax rates and are not tax or legal advice.', false)
            ->assertSee('Confirm the correct rates and rules with your accountant or tax adviser.', false);
    }

    public function test_tax_settings_page_does_not_show_stale_future_tax_wording(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $response = $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringNotContainsString('Tax configuration is being prepared', $html);
        $this->assertStringNotContainsString('later release', strtolower($html));
        $this->assertStringNotContainsString('when checkout tax calculation is live', strtolower($html));
        $this->assertStringNotContainsString('when platform checkout tax goes live', strtolower($html));
        $this->assertStringNotContainsString('when checkout calculation is released', strtolower($html));
    }

    public function test_tax_settings_page_reflects_enabled_state(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $store->taxSetting->update(['enabled' => true]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('Platform checkout uses the customer', false);

        $store->taxSetting->update(['enabled' => false]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('Configured rates are saved, but platform checkout will not apply calculated tax', false);
    }

    public function test_enabled_without_active_rates_renders_warning(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $store->taxSetting->update(['enabled' => true]);
        TaxRate::query()->where('store_id', $store->id)->update(['is_active' => false]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSeeText('Tax is active, but no active rates are configured');
    }

    public function test_disabled_with_active_rates_renders_informational_notice(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $store->taxSetting->update(['enabled' => false]);
        $this->createRate($store, ['is_active' => true]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSeeText('Rates are saved but currently inactive because platform tax is disabled');
    }

    public function test_tax_settings_summary_reflects_configuration(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $store->taxSetting->update([
            'enabled' => true,
            'prices_include_tax' => true,
            'default_product_taxable' => false,
            'shipping_taxable' => true,
        ]);
        $this->createRate($store, ['region_code' => '', 'is_active' => true]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSeeText('Tax status')
            ->assertSeeText('Active')
            ->assertSeeText('Tax included')
            ->assertSeeText('Not taxable by default')
            ->assertSeeText('Country-wide');
    }

    public function test_rate_form_preserves_input_and_shows_field_errors_on_validation_failure(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $response = $this->actingAsStore($owner, $store)
            ->from(route('settings.taxes.index'))
            ->post(route('settings.taxes.rates.store'), [
                'name' => 'Bad Country Rate',
                'country_code' => 'United States',
                'region_code' => 'CA',
                'rate_percent' => '8.25',
                'priority' => 100,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('settings.taxes.index'))
            ->assertSessionHasErrors('country_code', null, 'createTaxRate');

        $this->actingAsStore($owner, $store)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Bad Country Rate', false)
            ->assertSee('United States', false);
    }

    public function test_tax_rate_country_code_contract_rejects_non_ascii_and_numeric_values(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        foreach (['U1', 'ÜS', 'United States'] as $invalidCountry) {
            $this->actingAsStore($owner, $store)
                ->post(route('settings.taxes.rates.store'), $this->storeRatePayload(['country_code' => $invalidCountry]))
                ->assertSessionHasErrors('country_code', null, 'createTaxRate');
        }

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload(['country_code' => 'us']))
            ->assertRedirect();

        $this->assertDatabaseHas('tax_rates', [
            'store_id' => $store->id,
            'country_code' => 'US',
            'region_code' => 'CA',
        ]);
    }

    public function test_tax_rate_update_accepts_lowercase_ascii_country_code(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, ['country_code' => 'US', 'region_code' => 'NY']);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'country_code' => 'ca',
                'region_code' => 'BC',
                'name' => 'BC Tax',
            ]))
            ->assertRedirect();

        $this->assertSame('CA', $rate->fresh()->country_code);
        $this->assertSame('BC', $rate->fresh()->region_code);
    }

    public function test_tax_routes_match_contracts(): void
    {
        foreach (self::TAX_ROUTE_CONTRACTS as $contract) {
            $this->assertRouteContract($contract);
        }
    }

    /**
     * @param  array{name: string, uri: string, methods: list<string>, middleware: list<string>, controller: class-string, action: string}  $contract
     */
    private function assertRouteContract(array $contract): void
    {
        $route = Route::getRoutes()->getByName($contract['name']);
        $this->assertNotNull($route, "Missing route: {$contract['name']}");

        $this->assertSame($contract['uri'], $route->uri(), "URI mismatch for {$contract['name']}");

        foreach ($contract['methods'] as $method) {
            $this->assertContains($method, $route->methods(), "HTTP method {$method} missing for {$contract['name']}");
        }

        $middleware = $route->gatherMiddleware();
        foreach ($contract['middleware'] as $expected) {
            $this->assertContains(
                $expected,
                $middleware,
                "Middleware {$expected} missing for {$contract['name']}. Actual: ".implode(', ', $middleware),
            );
        }

        $action = (string) $route->getAction('controller');
        [$controller, $method] = array_pad(explode('@', $action), 2, null);

        $this->assertSame($contract['controller'], $controller, "Controller mismatch for {$contract['name']}");
        $this->assertSame($contract['action'], $method, "Action mismatch for {$contract['name']}");
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStoreFixture(
        string $ownerEmail = 'owner@example.com',
        string $storeName = 'Tax Settings Store',
    ): array {
        $owner = $this->createUser('user', $ownerEmail);
        $store = $this->createStore($owner, $storeName);
        $this->attachMember($store, $owner, Store::ROLE_OWNER);

        return [$owner, $store];
    }

    /**
     * @return array{0: User, 1: Store, 2: User, 3?: User}
     */
    private function teamStoreFixture(bool $includeStaff = false): array
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $manager = $this->createUser('user', 'manager@example.com');
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        if (! $includeStaff) {
            return [$owner, $store, $manager];
        }

        $staff = $this->createUser('user', 'staff@example.com');
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        return [$owner, $store, $manager, $staff];
    }

    protected function createUser(string $roleName, string $email): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    protected function createStore(User $owner, string $name): Store
    {
        return Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    protected function attachMember(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    protected function actingAsStore(User $user, Store $store): self
    {
        return $this->actingAs($user)->withSession(['current_store_id' => $store->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => 0,
            'prices_include_tax' => 0,
            'default_product_taxable' => 1,
            'shipping_taxable' => 0,
            'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function storeRatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'CA Sales',
            'country_code' => 'US',
            'region_code' => 'CA',
            'rate_percent' => '8.25',
            'priority' => 100,
            'is_active' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createRate(Store $store, array $overrides = []): TaxRate
    {
        return TaxRate::query()->create(array_merge([
            'store_id' => $store->id,
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'Existing CA Sales',
            'rate_percent' => '8.2500',
            'priority' => 100,
            'is_active' => true,
        ], $overrides));
    }

    public function test_failed_create_preserves_only_create_form_values(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $existing = $this->createRate($store, [
            'name' => 'Stable Existing Rate',
            'country_code' => 'US',
            'region_code' => 'NY',
            'rate_percent' => '8.8750',
        ]);

        $response = $this->actingAsStore($owner, $store)
            ->from(route('settings.taxes.index'))
            ->post(route('settings.taxes.rates.store'), [
                'name' => 'Bad Create Rate',
                'country_code' => 'United States',
                'region_code' => 'CA',
                'rate_percent' => '7.25',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('settings.taxes.index'))
            ->assertSessionHasErrors('country_code', null, 'createTaxRate');

        $html = $this->actingAsStore($owner, $store)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Bad Create Rate', false)
            ->assertSee('Stable Existing Rate', false)
            ->assertDontSee('value="Stable Existing Rate"', false)
            ->getContent();

        $this->assertStringContainsString('id="tax-rate-create-dialog"', $html);
        $this->assertTaxDialogIsOpen($html, 'tax-rate-create-dialog');
    }

    public function test_failed_edit_preserves_only_selected_rate_values(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $target = $this->createRate($store, [
            'name' => 'Edit Target Rate',
            'country_code' => 'US',
            'region_code' => 'TX',
            'rate_percent' => '6.2500',
        ]);
        $other = $this->createRate($store, [
            'name' => 'Other Stable Rate',
            'country_code' => 'US',
            'region_code' => 'NY',
            'rate_percent' => '8.8750',
        ]);

        $response = $this->actingAsStore($owner, $store)
            ->from(route('settings.taxes.index'))
            ->patch(route('settings.taxes.rates.update', $target), [
                'name' => 'Broken Edit Rate',
                'country_code' => 'United States',
                'region_code' => 'TX',
                'rate_percent' => '6.25',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('settings.taxes.index'))
            ->assertSessionHasErrors('country_code', null, 'updateTaxRate_'.$target->id);

        $html = $this->actingAsStore($owner, $store)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Broken Edit Rate', false)
            ->assertSee('Other Stable Rate', false)
            ->assertSee('id="tax-rate-edit-dialog-'.$target->id.'"', false)
            ->getContent();

        $this->assertTaxDialogIsOpen($html, 'tax-rate-edit-dialog-'.$target->id);
    }

    public function test_hidden_priority_defaults_to_one_hundred_on_create(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $this->actingAsStore($owner, $store)
            ->post(route('settings.taxes.rates.store'), $this->storeRatePayload([
                'name' => 'Default Priority Rate',
                'country_code' => 'GB',
                'region_code' => '',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('tax_rates', [
            'store_id' => $store->id,
            'name' => 'Default Priority Rate',
            'country_code' => 'GB',
            'priority' => 100,
        ]);
    }

    public function test_taxes_page_renders_country_select_and_jurisdiction_labels(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $this->createRate($store, [
            'name' => 'California Sales Tax',
            'country_code' => 'US',
            'region_code' => 'CA',
            'rate_percent' => '7.2500',
        ]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('United States', false)
            ->assertSee('Region-specific', false)
            ->assertSee('California', false)
            ->assertSee('+ Add tax rate', false)
            ->assertDontSee('>Priority</th>', false)
            ->assertDontSee('multiple rates could match', false);
    }

    public function test_successful_rate_update_redirects_to_clean_index_without_edit_rate(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Redirect Edit Rate',
            'country_code' => 'US',
            'region_code' => 'TX',
            'rate_percent' => '6.2500',
        ]);

        $response = $this->actingAsStore($owner, $store)
            ->from(route('settings.taxes.index', ['edit_rate' => $rate->id]))
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'name' => 'Redirect Edit Rate Updated',
                'country_code' => 'US',
                'region_code' => 'TX',
                'rate_percent' => '6.25',
            ]));

        $response->assertRedirect(route('settings.taxes.index'))
            ->assertSessionHas('success', 'Tax rate updated.');

        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('edit_rate', $location);
    }

    public function test_successful_rate_update_page_does_not_render_edit_dialog_open(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Closed Edit Dialog Rate',
            'country_code' => 'US',
            'region_code' => 'FL',
            'rate_percent' => '6.0000',
        ]);

        $this->actingAsStore($owner, $store)
            ->from(route('settings.taxes.index', ['edit_rate' => $rate->id]))
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'name' => 'Closed Edit Dialog Rate',
                'country_code' => 'US',
                'region_code' => 'FL',
                'rate_percent' => '6.00',
            ]))
            ->assertRedirect(route('settings.taxes.index'));

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertDontSee('data-auto-open-tax-dialog="edit"', false);

        $html = $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->getContent();

        $this->assertTaxDialogIsClosed($html, 'tax-rate-create-dialog');
        $this->assertStringNotContainsString('tax-rate-edit-dialog-', $html);
    }

    public function test_successful_delete_redirects_to_clean_index(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Delete Redirect Rate',
            'country_code' => 'US',
            'region_code' => 'WA',
            'rate_percent' => '6.5000',
        ]);

        $response = $this->actingAsStore($owner, $store)
            ->from(route('settings.taxes.index', ['edit_rate' => $rate->id]))
            ->delete(route('settings.taxes.rates.destroy', $rate));

        $response->assertRedirect(route('settings.taxes.index'))
            ->assertSessionHas('success', 'Tax rate removed.');

        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('edit_rate', $location);
    }

    public function test_failed_create_shows_global_summary_and_create_field_error(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $response = $this->actingAsStore($owner, $store)
            ->from(route('settings.taxes.index'))
            ->post(route('settings.taxes.rates.store'), [
                'name' => 'Summary Create Rate',
                'country_code' => 'United States',
                'region_code' => 'CA',
                'rate_percent' => '7.25',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('settings.taxes.index'))
            ->assertSessionHasErrors('country_code', null, 'createTaxRate');

        $html = $this->actingAsStore($owner, $store)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('Summary Create Rate', false)
            ->getContent();

        $this->assertTaxDialogIsOpen($html, 'tax-rate-create-dialog');
    }

    public function test_failed_edit_shows_global_summary_and_selected_edit_field_error(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Summary Edit Rate',
            'country_code' => 'US',
            'region_code' => 'OR',
            'rate_percent' => '5.0000',
        ]);

        $response = $this->actingAsStore($owner, $store)
            ->from(route('settings.taxes.index', ['edit_rate' => $rate->id]))
            ->patch(route('settings.taxes.rates.update', $rate), [
                'name' => 'Summary Edit Broken',
                'country_code' => 'United States',
                'region_code' => 'OR',
                'rate_percent' => '5.00',
                'is_active' => 1,
            ]);

        $response->assertRedirect()
            ->assertSessionHasErrors('country_code', null, 'updateTaxRate_'.$rate->id);

        $html = $this->actingAsStore($owner, $store)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('Summary Edit Broken', false)
            ->getContent();

        $this->assertTaxDialogIsOpen($html, 'tax-rate-edit-dialog-'.$rate->id);
        $this->assertTaxDialogIsClosed($html, 'tax-rate-create-dialog');
    }

    public function test_create_rate_query_renders_create_form(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();

        $html = $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index', ['create_rate' => 1]))
            ->assertOk()
            ->assertSee('id="tax-rate-create-dialog"', false)
            ->getContent();

        $this->assertTaxDialogIsOpen($html, 'tax-rate-create-dialog');
    }

    public function test_edit_rate_query_renders_selected_edit_dialog_open(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Edit Query Rate',
            'country_code' => 'US',
            'region_code' => 'WA',
            'rate_percent' => '6.5000',
        ]);

        $html = $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index', ['edit_rate' => $rate->id]))
            ->assertOk()
            ->assertSee('Edit tax rate', false)
            ->getContent();

        $this->assertTaxDialogIsOpen($html, 'tax-rate-edit-dialog-'.$rate->id);
    }

    public function test_normal_index_does_not_render_open_tax_dialogs(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $this->createRate($store, [
            'name' => 'Closed Index Rate',
            'country_code' => 'US',
            'region_code' => 'OR',
            'rate_percent' => '5.0000',
        ]);

        $html = $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->getContent();

        $this->assertTaxDialogIsClosed($html, 'tax-rate-create-dialog');
        $this->assertStringNotContainsString('tax-rate-edit-dialog-', $html);
    }

    public function test_add_tax_rate_action_has_usable_href_fallback(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $createUrl = route('settings.taxes.index', ['create_rate' => 1]);

        $rate = $this->createRate($store, [
            'name' => 'Href Fallback Rate',
            'country_code' => 'US',
            'region_code' => 'CO',
            'rate_percent' => '2.9000',
        ]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('href="'.$createUrl.'"', false)
            ->assertSee('data-open-tax-rate-create', false)
            ->assertSee('href="'.route('settings.taxes.index', ['edit_rate' => $rate->id]).'"', false);
    }

    public function test_unauthorized_viewers_do_not_receive_open_mutation_dialog(): void
    {
        [, $store, , $staff] = $this->teamStoreFixture(true);
        $rate = $this->createRate($store, [
            'name' => 'Staff Hidden Rate',
            'country_code' => 'US',
            'region_code' => 'ME',
            'rate_percent' => '5.5000',
        ]);

        $html = $this->actingAsStore($staff, $store)
            ->get(route('settings.taxes.index', ['create_rate' => 1, 'edit_rate' => $rate->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="tax-rate-create-dialog"', $html);
        $this->assertStringNotContainsString('tax-rate-edit-dialog-', $html);
    }

    public function test_mobile_card_shows_region_name_for_us_ca(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $this->createRate($store, [
            'name' => 'California Mobile Card Rate',
            'country_code' => 'US',
            'region_code' => 'CA',
            'rate_percent' => '7.2500',
        ]);

        $html = $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('California Mobile Card Rate', $html);
        $this->assertStringContainsString('California', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'California'));
    }

    public function test_mobile_card_shows_region_code_fallback_for_unknown_region(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $this->createRate($store, [
            'name' => 'Unknown Region Mobile Rate',
            'country_code' => 'US',
            'region_code' => 'ZZ',
            'rate_percent' => '4.0000',
        ]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('Unknown Region Mobile Rate', false)
            ->assertSee('ZZ', false);
    }

    public function test_country_wide_mobile_card_does_not_show_fake_region_line(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $this->createRate($store, [
            'name' => 'Country Wide Mobile Rate',
            'country_code' => 'US',
            'region_code' => '',
            'rate_percent' => '5.0000',
        ]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSeeInOrder(['Country Wide Mobile Rate', 'United States', 'Country-wide'], false);
    }

    public function test_editing_unrelated_fields_preserves_non_default_priority(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Legacy Priority Rate',
            'country_code' => 'US',
            'region_code' => 'NV',
            'rate_percent' => '6.8500',
            'priority' => 50,
        ]);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'name' => 'Legacy Priority Rate Renamed',
                'country_code' => 'US',
                'region_code' => 'NV',
                'rate_percent' => '6.85',
                'priority' => 50,
            ]))
            ->assertRedirect(route('settings.taxes.index'));

        $this->assertDatabaseHas('tax_rates', [
            'id' => $rate->id,
            'name' => 'Legacy Priority Rate Renamed',
            'priority' => 50,
        ]);
    }

    public function test_existing_us_pr_rate_renders_custom_region_selected(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Puerto Rico Sales Tax',
            'country_code' => 'US',
            'region_code' => 'PR',
            'rate_percent' => '10.5000',
        ]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index', ['edit_rate' => $rate->id]))
            ->assertOk()
            ->assertSee('PR — Custom or legacy code', false)
            ->assertSee('region code not included in the suggested list', false);
    }

    public function test_existing_us_zz_custom_rate_renders_and_preserves_zz(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Custom ZZ Rate',
            'country_code' => 'US',
            'region_code' => 'ZZ',
            'rate_percent' => '4.0000',
        ]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index', ['edit_rate' => $rate->id]))
            ->assertOk()
            ->assertSee('ZZ — Custom or legacy code', false);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'name' => 'Custom ZZ Rate',
                'country_code' => 'US',
                'region_code' => 'ZZ',
                'rate_percent' => '4.00',
            ]))
            ->assertRedirect(route('settings.taxes.index'));

        $this->assertDatabaseHas('tax_rates', [
            'id' => $rate->id,
            'region_code' => 'ZZ',
        ]);
    }

    public function test_updating_only_rate_name_preserves_custom_region(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Rename Custom Region Rate',
            'country_code' => 'US',
            'region_code' => 'ZZ',
            'rate_percent' => '4.0000',
        ]);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'name' => 'Renamed Custom Region Rate',
                'country_code' => 'US',
                'region_code' => 'ZZ',
                'rate_percent' => '4.00',
            ]))
            ->assertRedirect(route('settings.taxes.index'));

        $this->assertDatabaseHas('tax_rates', [
            'id' => $rate->id,
            'name' => 'Renamed Custom Region Rate',
            'region_code' => 'ZZ',
        ]);
    }

    public function test_updating_only_active_status_preserves_custom_region(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Toggle Custom Region Rate',
            'country_code' => 'US',
            'region_code' => 'ZZ',
            'rate_percent' => '4.0000',
            'is_active' => true,
        ]);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'name' => 'Toggle Custom Region Rate',
                'country_code' => 'US',
                'region_code' => 'ZZ',
                'rate_percent' => '4.00',
                'is_active' => 0,
            ]))
            ->assertRedirect(route('settings.taxes.index'));

        $this->assertDatabaseHas('tax_rates', [
            'id' => $rate->id,
            'region_code' => 'ZZ',
            'is_active' => false,
        ]);
    }

    public function test_known_us_ca_edit_uses_california_selector(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'California Known Region Rate',
            'country_code' => 'US',
            'region_code' => 'CA',
            'rate_percent' => '7.2500',
        ]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index', ['edit_rate' => $rate->id]))
            ->assertOk()
            ->assertSee('California (CA)', false)
            ->assertDontSee('CA — Custom or legacy code', false);
    }

    public function test_country_wide_us_rate_edit_keeps_blank_region(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Country Wide US Rate',
            'country_code' => 'US',
            'region_code' => '',
            'rate_percent' => '5.0000',
        ]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index', ['edit_rate' => $rate->id]))
            ->assertOk()
            ->assertSee('Country-wide (all regions)', false);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'name' => 'Country Wide US Rate Updated',
                'country_code' => 'US',
                'region_code' => '',
                'rate_percent' => '5.00',
            ]))
            ->assertRedirect(route('settings.taxes.index'));

        $this->assertDatabaseHas('tax_rates', [
            'id' => $rate->id,
            'region_code' => '',
        ]);
    }

    public function test_unrelated_edit_does_not_turn_unknown_region_into_country_wide(): void
    {
        [$owner, $store] = $this->ownerStoreFixture();
        $rate = $this->createRate($store, [
            'name' => 'Preserve Unknown Region',
            'country_code' => 'US',
            'region_code' => 'ZZ',
            'rate_percent' => '4.0000',
        ]);

        $this->actingAsStore($owner, $store)
            ->patch(route('settings.taxes.rates.update', $rate), $this->storeRatePayload([
                'name' => 'Preserve Unknown Region Renamed',
                'country_code' => 'US',
                'region_code' => 'ZZ',
                'rate_percent' => '4.25',
            ]))
            ->assertRedirect(route('settings.taxes.index'));

        $this->assertDatabaseHas('tax_rates', [
            'id' => $rate->id,
            'region_code' => 'ZZ',
            'rate_percent' => '4.2500',
        ]);
        $this->assertDatabaseMissing('tax_rates', [
            'id' => $rate->id,
            'region_code' => '',
        ]);
    }

    private function assertTaxDialogIsOpen(string $html, string $dialogId): void
    {
        $this->assertTrue(
            (bool) preg_match('/<dialog\b[^>]*\bid="'.preg_quote($dialogId, '/').'"[^>]*>/i', $html, $matches),
            "Expected dialog {$dialogId} to be present."
        );
        $this->assertStringContainsString(
            'open',
            $matches[0],
            "Expected dialog {$dialogId} to include native open attribute."
        );
    }

    private function assertTaxDialogIsClosed(string $html, string $dialogId): void
    {
        if (! preg_match('/<dialog\b[^>]*\bid="'.preg_quote($dialogId, '/').'"[^>]*>/i', $html, $matches)) {
            return;
        }

        $this->assertStringNotContainsString(
            'open',
            $matches[0],
            "Expected dialog {$dialogId} to remain closed."
        );
    }
}
