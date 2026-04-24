<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportDuplicateHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_rejects_case_insensitive_duplicate_headers(): void
    {
        Storage::fake('local');

        $owner = $this->createMerchantUser('dup@example.com');
        $store = $this->createMemberStore($owner, 'Dup Store', Store::ROLE_OWNER);

        $csv = "SKU,sku,Price\nA,B,1\n";
        $file = UploadedFile::fake()->createWithContent('dup.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('product_imports', ['store_id' => $store->id]);
    }

    protected function createMerchantUser(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    protected function createMemberStore(User $user, string $name, string $role): Store
    {
        $store = Store::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => null,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($user->id, ['role' => $role]);

        return $store;
    }
}
