<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_and_a_user_is_created(): void
    {
        $userRole = $this->createUserRole();

        $response = $this->post(route('register.store'), [
            'name' => 'New Merchant',
            'email' => 'merchant@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('onboarding-StoreDetails-1'));

        $this->assertDatabaseHas('users', [
            'name' => 'New Merchant',
            'email' => 'merchant@example.com',
            'role_id' => $userRole->id,
        ]);
    }

    public function test_newly_registered_user_gets_the_default_user_role(): void
    {
        $userRole = $this->createUserRole();

        $this->post(route('register.store'), [
            'name' => 'Role Check User',
            'email' => 'rolecheck@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'rolecheck@example.com')->firstOrFail();

        $this->assertSame($userRole->id, $user->role_id);
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_newly_registered_user_is_authenticated_after_registration(): void
    {
        $this->createUserRole();

        $this->post(route('register.store'), [
            'name' => 'Authenticated User',
            'email' => 'authed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $this->assertSame('authed@example.com', auth()->user()?->email);
    }

    public function test_duplicate_email_registration_is_rejected(): void
    {
        $userRole = $this->createUserRole();

        User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'role_id' => $userRole->id,
        ]);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Duplicate User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_confirmation_is_required(): void
    {
        $this->createUserRole();

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'No Confirmation User',
            'email' => 'noconfirm@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', [
            'email' => 'noconfirm@example.com',
        ]);
    }

    protected function createUserRole(): Role
    {
        return Role::firstOrCreate(['name' => 'user']);
    }
}
