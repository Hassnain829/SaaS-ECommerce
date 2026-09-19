<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => '',
            'services.google.client_secret' => '',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
        putenv('GOOGLE_CLIENT_ID=');
        putenv('GOOGLE_CLIENT_SECRET=');
        putenv('GOOGLE_REDIRECT_URI=');
        $_ENV['GOOGLE_CLIENT_ID'] = '';
        $_ENV['GOOGLE_CLIENT_SECRET'] = '';
        $_ENV['GOOGLE_REDIRECT_URI'] = '';
        $_SERVER['GOOGLE_CLIENT_ID'] = '';
        $_SERVER['GOOGLE_CLIENT_SECRET'] = '';
        $_SERVER['GOOGLE_REDIRECT_URI'] = '';
    }

    public function test_google_button_is_hidden_until_oauth_is_configured(): void
    {
        $this->get(route('signin'))
            ->assertOk()
            ->assertDontSeeText('Continue with Google');

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSeeText('Continue with Google');
    }

    public function test_google_button_appears_when_oauth_is_configured(): void
    {
        $this->enableGoogle();

        $this->get(route('signin'))
            ->assertOk()
            ->assertSeeText('Continue with Google')
            ->assertSee(route('auth.google.redirect'), false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSeeText('Continue with Google')
            ->assertSeeText('By continuing with Google, you agree to our');
    }

    public function test_google_button_appears_when_env_has_keys_but_cached_config_is_empty(): void
    {
        putenv('GOOGLE_CLIENT_ID=env-google-client-id');
        putenv('GOOGLE_CLIENT_SECRET=env-google-client-secret');
        $_ENV['GOOGLE_CLIENT_ID'] = 'env-google-client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'env-google-client-secret';

        try {
            $this->get(route('signin'))
                ->assertOk()
                ->assertSeeText('Continue with Google');
        } finally {
            putenv('GOOGLE_CLIENT_ID=');
            putenv('GOOGLE_CLIENT_SECRET=');
            $_ENV['GOOGLE_CLIENT_ID'] = '';
            $_ENV['GOOGLE_CLIENT_SECRET'] = '';
            unset($_SERVER['GOOGLE_CLIENT_ID'], $_SERVER['GOOGLE_CLIENT_SECRET']);
        }
    }

    public function test_google_redirect_is_blocked_when_oauth_is_not_configured(): void
    {
        $this->get(route('auth.google.redirect'))
            ->assertRedirect(route('signin'))
            ->assertSessionHasErrors('email');
    }

    public function test_google_redirect_sends_the_merchant_to_google(): void
    {
        $this->enableGoogle();
        $this->mockGoogleRedirect('https://accounts.google.com/o/oauth2/auth');

        $this->get(route('auth.google.redirect'))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_google_callback_creates_a_verified_merchant_and_starts_onboarding(): void
    {
        $this->enableGoogle();
        $this->createUserRole();
        $this->mockGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('onboarding-StoreDetails-1'));

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'google.merchant@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_google_callback_signs_in_an_existing_account_with_the_same_email(): void
    {
        $this->enableGoogle();
        $role = $this->createUserRole();
        $user = User::factory()->create([
            'email' => 'google.merchant@example.com',
            'role_id' => $role->id,
            'google_id' => null,
        ]);
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => 'Linked Store',
            'slug' => 'linked-store-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => '100 Main St',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$user->id => ['role' => Store::ROLE_OWNER]]);
        $this->mockGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('google-123', $user->fresh()->google_id);
    }

    public function test_google_callback_rejects_deactivated_accounts(): void
    {
        $this->enableGoogle();
        $role = $this->createUserRole();
        User::factory()->create([
            'email' => 'google.merchant@example.com',
            'role_id' => $role->id,
            'is_active' => false,
        ]);
        $this->mockGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('signin'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_google_callback_rejects_unverified_google_emails(): void
    {
        $this->enableGoogle();
        $this->createUserRole();
        $this->mockGoogleUser(['email_verified' => false]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('signin'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'google.merchant@example.com']);
    }

    private function enableGoogle(): void
    {
        config([
            'services.google.client_id' => 'test-google-client-id',
            'services.google.client_secret' => 'test-google-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    private function createUserRole(): Role
    {
        return Role::firstOrCreate(['name' => 'user']);
    }

    /**
     * @param  array{id?: string, name?: string, email?: string, email_verified?: bool}  $overrides
     */
    private function mockGoogleUser(array $overrides = []): void
    {
        $socialiteUser = (new SocialiteUser)->setRaw([
            'email_verified' => $overrides['email_verified'] ?? true,
        ])->map([
            'id' => $overrides['id'] ?? 'google-123',
            'name' => $overrides['name'] ?? 'Google Merchant',
            'email' => $overrides['email'] ?? 'google.merchant@example.com',
        ]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function mockGoogleRedirect(string $url): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('scopes')->once()->andReturnSelf();
        $provider->shouldReceive('with')->once()->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect($url));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
