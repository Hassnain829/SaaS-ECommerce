<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnterpriseQaExternalOrderDedupHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_order_dedup_endpoint_is_gone(): void
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Gone Dedup Store',
            'slug' => 'gone-dedup-'.Str::random(6),
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

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', [
                'external_order_number' => 'WEB-DEDUP',
                'payment_status' => 'paid',
            ])
            ->assertNotFound();
    }
}
