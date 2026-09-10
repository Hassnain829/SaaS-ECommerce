<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaxRulebookWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rulebook_renders_workspace_and_bound_actions(): void
    {
        [$owner, $store] = $this->ownerStore();

        $html = $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSee('Tax rulebook', false)
            ->assertSee('Jurisdictions', false)
            ->assertSee('Save tax settings', false)
            ->assertSee('+ Add tax rate', false)
            ->assertSee(route('settings.taxes.update'), false)
            ->assertSee(route('settings.taxes.rates.store'), false)
            ->assertDontSee('Reset preview data', false)
            ->assertDontSee('Browser storage is unavailable', false)
            ->getContent();

        $this->assertStringContainsString('data-trb-root', $html);
        $this->assertStringContainsString('id="tax-rate-create-dialog"', $html);
        $this->assertStringContainsString('id="tax-behavior-dialog"', $html);
    }

    public function test_selected_rate_query_opens_inspector_without_hiding_other_rates(): void
    {
        [$owner, $store] = $this->ownerStore();
        $us = $this->createRate($store, [
            'name' => 'US Country Rate',
            'country_code' => 'US',
            'region_code' => '',
            'rate_percent' => '5.0000',
        ]);
        $this->createRate($store, [
            'name' => 'California Regional Rate',
            'country_code' => 'US',
            'region_code' => 'CA',
            'rate_percent' => '7.2500',
        ]);

        $this->actingAsStore($owner, $store)
            ->get(route('settings.taxes.index', ['rate' => $us->id]))
            ->assertOk()
            ->assertSee('data-trb-selected="'.$us->id.'"', false)
            ->assertSee('US Country Rate', false)
            ->assertSee('California Regional Rate', false)
            ->assertSee('United States', false);
    }

    public function test_staff_sees_rulebook_without_mutation_controls(): void
    {
        [$owner, $store, $staff] = $this->ownerStore(withStaff: true);

        $html = $this->actingAsStore($staff, $store)
            ->get(route('settings.taxes.index'))
            ->assertOk()
            ->assertSeeText('Tax rulebook')
            ->assertSeeText('Only the store owner can change tax settings.')
            ->assertDontSeeText('Save tax settings')
            ->assertDontSeeText('Add tax rate')
            ->getContent();

        $this->assertStringNotContainsString('id="tax-rate-create-dialog"', $html);
        $this->assertStringNotContainsString('id="tax-behavior-dialog"', $html);
    }

    private function ownerStore(bool $withStaff = false): array
    {
        $role = Role::query()->firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Tax Rulebook Store '.Str::random(5),
            'slug' => 'tax-rb-'.Str::random(8),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $staff = null;
        if ($withStaff) {
            $staff = User::factory()->create([
                'role_id' => $role->id,
                'is_active' => true,
            ]);
            $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);
        }

        return [$owner, $store, $staff];
    }

    private function actingAsStore(User $user, Store $store)
    {
        return $this->actingAs($user)->withSession(['current_store_id' => $store->id]);
    }

    private function createRate(Store $store, array $overrides = []): TaxRate
    {
        return TaxRate::query()->create(array_merge([
            'store_id' => $store->id,
            'name' => 'Workspace Rate',
            'country_code' => 'US',
            'region_code' => 'NY',
            'rate_percent' => '8.8750',
            'priority' => 100,
            'is_active' => true,
        ], $overrides));
    }
}
