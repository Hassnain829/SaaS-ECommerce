<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalShipmentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_shipment_endpoint_is_gone_without_a_token(): void
    {
        $this->postJson('/api/v1/external/shipments', [])
            ->assertNotFound();
    }

    public function test_external_shipment_endpoint_is_gone_with_a_storefront_token(): void
    {
        $token = $this->token();

        $this->withToken($token)
            ->postJson('/api/v1/external/shipments', [
                'external_order_number' => 'WEB-SHIP-1',
                'external_shipment_id' => 'SHIP-123',
                'status' => 'shipped',
            ])
            ->assertNotFound();
    }

    private function token(): string
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Gone Shipment Store',
            'slug' => 'gone-ship-'.Str::random(6),
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

        return $token;
    }
}
