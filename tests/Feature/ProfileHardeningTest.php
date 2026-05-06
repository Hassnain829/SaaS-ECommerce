<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_saves_account_details_avatar_and_audit_log(): void
    {
        Storage::fake('public');
        $user = $this->merchant('profile@example.com');
        $store = $this->store($user, 'Profile Store');
        $this->attach($store, $user, Store::ROLE_OWNER);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('profile.update'), [
                'name' => 'Updated Merchant',
                'email' => 'updated-profile@example.com',
                'phone' => '+15550123',
                'avatar' => UploadedFile::fake()->createWithContent(
                    'avatar.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
                ),
            ]);

        $response->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated Merchant', $user->name);
        $this->assertSame('updated-profile@example.com', $user->email);
        $this->assertSame('+15550123', $user->phone);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);

        $this->assertDatabaseHas('security_logs', [
            'user_id' => $user->id,
            'event_type' => 'profile_updated',
        ]);
    }

    public function test_password_change_updates_password_and_writes_security_log(): void
    {
        $user = $this->merchant('password@example.com');
        $store = $this->store($user, 'Password Store');
        $this->attach($store, $user, Store::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-strong-password',
                'password_confirmation' => 'new-strong-password',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('new-strong-password', $user->fresh()->password));
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $user->id,
            'event_type' => 'password_changed',
        ]);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = $this->merchant('inactive@example.com');
        $user->forceFill(['is_active' => false])->save();

        $this->post(route('signin.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_last_store_owner_cannot_deactivate_account(): void
    {
        $user = $this->merchant('last-owner@example.com');
        $store = $this->store($user, 'Owner Locked Store');
        $this->attach($store, $user, Store::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('profile.deactivate'), [
                'confirm_deactivation' => 'deactivate',
            ])
            ->assertSessionHasErrors('confirm_deactivation');

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_profile_page_uses_current_user_data(): void
    {
        $user = $this->merchant('real-profile@example.com');
        $store = $this->store($user, 'Real Profile Store');
        $this->attach($store, $user, Store::ROLE_MANAGER);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('profileSettings'));

        $response->assertOk();
        $response->assertSeeText($user->name);
        $response->assertSeeText('real-profile@example.com');
        $response->assertSeeText('Real Profile Store');
        $response->assertDontSeeText('Alex Rivers');
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name): Store
    {
        return Store::query()->create([
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

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }
}
