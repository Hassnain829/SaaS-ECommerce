<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Support\OrderLifecycle;
use App\Support\StorePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MerchantReadinessBatch2Test extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_general_settings_from_shared_store_editor(): void
    {
        [$owner, $store] = $this->ownerStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings'))
            ->assertOk()
            ->assertSeeText('Edit store')
            ->assertDontSeeText('editable later')
            ->assertSeeText('Store Profile')
            ->assertDontSeeText('Read-only fact')
            ->assertDontSeeText('Branding colors');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => 'Updated Batch2 Store',
                'contact_email' => 'ops@batch2.test',
                'primary_market' => 'Europe',
                'address' => '10 Merchant Way',
                'currency' => 'EUR',
                'timezone' => 'Europe/London',
                'category' => 'physical',
                'business_models' => ['Physical Goods'],
                'redirect_to' => 'generalSettings',
            ])
            ->assertRedirect(route('generalSettings'));

        $store->refresh();
        $this->assertSame('Updated Batch2 Store', $store->name);
        $this->assertSame('ops@batch2.test', $store->settings['contact_email'] ?? null);
        $this->assertSame('EUR', $store->currency);
        $this->assertSame('Europe/London', $store->timezone);
    }

    public function test_staff_cannot_update_store_settings(): void
    {
        [$owner, $store] = $this->ownerStore();
        $staff = $this->merchant('staff-batch2@example.test');
        $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);

        $this->assertFalse($staff->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => 'Staff Should Fail',
                'primary_market' => 'Global Market',
                'address' => 'Nope',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'category' => 'physical',
            ])
            ->assertForbidden();
    }

    public function test_cross_store_settings_update_is_blocked(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Store A');
        [$ownerB, $storeB] = $this->ownerStore('Store B');

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->put(route('store.update', ['storeId' => $storeB->id]), [
                'name' => 'Cross Store Hijack',
                'primary_market' => 'Global Market',
                'address' => 'Nope',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'category' => 'physical',
            ])
            ->assertNotFound();
    }

    public function test_store_default_updates_do_not_rewrite_historical_orders(): void
    {
        [$owner, $store] = $this->ownerStore();
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#BATCH2-1001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'subtotal' => 25,
            'total' => 25,
            'grand_total' => 25,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 0,
            'total_quantity' => 0,
            'placed_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => $store->name,
                'primary_market' => 'Global Market',
                'address' => $store->address,
                'currency' => 'EUR',
                'timezone' => 'Europe/London',
                'category' => 'physical',
                'redirect_to' => 'generalSettings',
            ])
            ->assertRedirect(route('generalSettings'));

        $order->refresh();
        $this->assertSame('USD', $order->currency_code);
        $this->assertSame('25.00', number_format((float) $order->grand_total, 2, '.', ''));
    }

    public function test_onboarding_completion_is_truthful_and_uses_workspace_links(): void
    {
        [$owner, $store] = $this->ownerStore();
        $store->update(['onboarding_completed' => false]);

        $step2 = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->get(route('onboarding-Step2-AddProductVariations'))
            ->assertOk();

        $step2->assertSeeText('Add product');
        $step2->assertSee(route('onboarding-Step2-AddProductVariations', ['add_product' => 1]), false);
        $step2->assertSee(route('products.import.create'), false);
        $step2->assertDontSeeText('Upload CSV');
        $step2->assertDontSeeText('marketplace is live');
        $step2->assertDontSee('id="product-onboarding-form"', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->get(route('onboarding-Step2-AddProductVariations', ['add_product' => 1]))
            ->assertOk()
            ->assertSee('id="product-onboarding-form"', false)
            ->assertSeeText('Basic Information');

        $step3 = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id, 'onboarding_store_id' => $store->id])
            ->get(route('onboarding_StoreReady'))
            ->assertOk();

        $step3->assertSeeText('Your management workspace is ready');
        $step3->assertSeeText('Review inventory');
        $step3->assertSeeText('when the production connected-channel feature becomes available');
        $step3->assertDontSeeText('baas.com');
        $step3->assertDontSee('cdn.tailwindcss.com', false);
    }

    public function test_password_reset_request_is_non_enumerating_and_resets_with_valid_token(): void
    {
        Notification::fake();
        $user = $this->merchant('reset-me@example.test');

        $this->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, QueuedResetPassword::class, function (QueuedResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);
            $actionUrl = $mail->actionUrl ?? null;
            $this->assertIsString($actionUrl);
            $this->assertStringContainsString('/reset-password/'.$notification->token, $actionUrl);
            $this->assertStringNotContainsString('email=', $actionUrl);
            $this->assertStringNotContainsString(rawurlencode($user->email), $actionUrl);

            $this->get(route('password.reset', [
                'token' => $notification->token,
            ]))->assertOk()->assertSee('data-password-toggle', false);

            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertRedirect(route('signin'));

            $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));

            return true;
        });
    }

    public function test_invalid_password_reset_token_fails_safely(): void
    {
        $user = $this->merchant('bad-token@example.test');

        $this->from(route('password.request'))
            ->post(route('password.store'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
    }

    public function test_email_verification_send_confirm_and_already_verified(): void
    {
        Notification::fake();
        $user = $this->merchant('verify-me@example.test');
        $this->assertNull($user->email_verified_at);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($user, QueuedVerifyEmail::class);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_terms_privacy_and_logout_security(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSeeText('Legal review is incomplete')
            ->assertDontSeeText('MAIL_MAILER');
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSeeText('Legal review is incomplete');

        $this->get(route('signin'))
            ->assertOk()
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('password.request'), false)
            ->assertSee('data-password-toggle="signin_password"', false);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertDontSeeText('MAIL_MAILER')
            ->assertDontSeeText('.env')
            ->assertDontSeeText('MAIL_USERNAME');

        $user = $this->merchant('logout@example.test');
        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('register'));

        $this->assertGuest();

        $this->actingAs($this->merchant('get-logout@example.test'))
            ->get('/logout')
            ->assertMethodNotAllowed();
    }

    public function test_registration_sends_verification_notification(): void
    {
        Notification::fake();
        Role::firstOrCreate(['name' => 'user']);

        $this->post(route('register.store'), [
            'name' => 'Verify On Register',
            'email' => 'verify-on-register@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'verify-on-register@example.test')->firstOrFail();
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_registration_survives_verification_delivery_failure(): void
    {
        Role::firstOrCreate(['name' => 'user']);

        config([
            'queue.default' => 'sync',
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'scheme' => null,
                'url' => null,
                'host' => '127.0.0.1',
                'port' => 1,
                'username' => 'invalid',
                'password' => 'invalid',
                'timeout' => 1,
                'local_domain' => 'localhost',
            ],
        ]);

        $this->post(route('register.store'), [
            'name' => 'Smtp Fail User',
            'email' => 'smtp-fail@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'email' => 'smtp-fail@example.test',
        ]);
        $this->assertAuthenticated();

        $this->post(route('logout'))->assertRedirect(route('register'));
        $this->assertGuest();

        $this->post(route('register.store'), [
            'name' => 'Smtp Fail User',
            'email' => 'smtp-fail@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertSessionHasErrors('email');
    }

    public function test_expired_password_reset_token_fails_safely(): void
    {
        $user = $this->merchant('expired-token@example.test');
        $token = Password::broker()->createToken($user);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->from(route('password.request'))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
    }

    public function test_logged_out_verification_link_returns_after_signin(): void
    {
        $user = $this->merchant('verify-intended@example.test');
        $user->forceFill(['password' => Hash::make('password123')])->save();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->get($url)->assertRedirect(route('signin'));

        $this->post(route('signin.attempt'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect($url);

        $this->get($url)->assertRedirect(route('onboarding-StoreDetails-1'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_manager_and_staff_see_settings_read_only_without_edit_cta(): void
    {
        [$owner, $store] = $this->ownerStore('Visibility Store');
        $manager = $this->merchant('manager-settings@example.test');
        $staff = $this->merchant('staff-settings@example.test');
        $store->members()->attach($manager->id, ['role' => Store::ROLE_MANAGER]);
        $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings'))
            ->assertOk()
            ->assertSeeText('Edit store');

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings'))
            ->assertOk()
            ->assertDontSeeText('Edit store')
            ->assertSeeText('Read-only for your role');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings'))
            ->assertOk()
            ->assertDontSeeText('Edit store')
            ->assertSeeText('Read-only for your role');
    }

    public function test_settings_account_tab_shows_profile_and_password_forms(): void
    {
        [$owner, $store] = $this->ownerStore('Account Tab Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings', ['tab' => 'account']))
            ->assertOk()
            ->assertSeeText('Your account')
            ->assertSeeText('Personal information')
            ->assertSeeText('Password')
            ->assertSeeText('Store access')
            ->assertSee('id="profileForm"', false)
            ->assertSee('id="password"', false)
            ->assertDontSeeText('Edit store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('profileSettings'))
            ->assertRedirect(route('generalSettings', ['tab' => 'account']));
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name = 'Batch2 Store'): array
    {
        $owner = $this->merchant();
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    private function merchant(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->unverified()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
            'password' => Hash::make('password'),
        ]);
    }
}
