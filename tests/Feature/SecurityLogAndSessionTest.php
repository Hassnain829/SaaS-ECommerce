<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use App\Models\UserSession;
use App\Services\SecurityLogRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityLogAndSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_attempts_are_audited_and_successful_login_records_a_session(): void
    {
        $user = $this->merchant('login@example.com');

        $this->post(route('signin.attempt'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('security_logs', [
            'user_id' => $user->id,
            'event_type' => 'failed_login',
            'severity' => SecurityLog::SEVERITY_WARNING,
        ]);

        $this->post(route('signin.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('security_logs', [
            'user_id' => $user->id,
            'event_type' => 'login',
        ]);

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
            'is_current' => true,
        ]);
    }

    public function test_store_switch_team_invite_and_developer_token_are_audited(): void
    {
        $owner = $this->merchant('owner@example.com');
        $alpha = $this->store($owner, 'Alpha Audit');
        $beta = $this->store($owner, 'Beta Audit');
        $this->attach($alpha, $owner, Store::ROLE_OWNER);
        $this->attach($beta, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $alpha->id])
            ->post(route('current-store.update'), ['store_id' => $beta->id])
            ->assertRedirect();

        $this->assertDatabaseHas('security_logs', [
            'store_id' => $beta->id,
            'user_id' => $owner->id,
            'event_type' => 'store_switch',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $beta->id])
            ->post(route('team-members.store'), [
                'name' => 'Audit Staff',
                'email' => 'audit-staff@example.com',
                'role' => Store::ROLE_STAFF,
            ])
            ->assertRedirect(route('team-members.index'));

        $newMemberId = User::query()->where('email', 'audit-staff@example.com')->value('id');
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $beta->id,
            'user_id' => $owner->id,
            'target_user_id' => $newMemberId,
            'event_type' => 'team_member_invited',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $beta->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertRedirect(route('developer-storefront.settings'));

        $this->assertDatabaseHas('security_logs', [
            'store_id' => $beta->id,
            'user_id' => $owner->id,
            'event_type' => 'api_key_created',
        ]);
    }

    public function test_user_can_revoke_another_session(): void
    {
        $user = $this->merchant('sessions@example.com');
        $store = $this->store($user, 'Session Store');
        $this->attach($store, $user, Store::ROLE_OWNER);

        $oldSession = UserSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'old-session-id',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 Chrome/120 Windows',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'Desktop',
            'last_activity' => now()->subDay(),
            'is_current' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('security.sessions.destroy', $oldSession))
            ->assertRedirect();

        $this->assertNotNull($oldSession->fresh()->revoked_at);
        $this->assertNotNull($oldSession->fresh()->ended_at);
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $user->id,
            'event_type' => 'user_session_revoked',
        ]);
    }

    public function test_security_page_shows_real_sessions_and_logs(): void
    {
        $user = $this->merchant('security-page@example.com');
        $store = $this->store($user, 'Security UI Store');
        $this->attach($store, $user, Store::ROLE_OWNER);

        UserSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'visible-session',
            'ip_address' => '10.0.0.2',
            'browser' => 'Firefox',
            'os' => 'Linux',
            'device_type' => 'Desktop',
            'location' => 'Lahore, Pakistan',
            'last_activity' => now(),
            'is_current' => false,
        ]);

        SecurityLog::query()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'event_type' => 'product_bulk_action',
            'severity' => SecurityLog::SEVERITY_INFO,
            'metadata' => ['action' => 'stock'],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('security'));

        $response->assertOk();
        $response->assertSeeText('Firefox on Linux');
        $response->assertSeeText('Lahore, Pakistan');
        $response->assertSeeText('Bulk catalog action');
        $response->assertDontSeeText('Safari on iPhone 15');
    }

    public function test_user_session_location_and_ended_at_are_nullable(): void
    {
        $user = $this->merchant('nullable-session@example.com');
        $session = UserSession::query()->create([
            'user_id' => $user->id,
            'session_id' => 'nullable-session',
            'ip_address' => '10.0.0.3',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'Desktop',
            'last_activity' => now(),
            'is_current' => false,
        ]);

        $this->assertNull($session->fresh()->location);
        $this->assertNull($session->fresh()->ended_at);
    }

    public function test_security_log_recorder_is_fail_safe_when_write_fails(): void
    {
        $user = $this->merchant('log-fail@example.com');
        $store = $this->store($user, 'Log Fail Store');
        $this->attach($store, $user, Store::ROLE_OWNER);

        Log::spy();

        $resource = fopen('php://memory', 'r');

        try {
            $result = app(SecurityLogRecorder::class)->record(
                request(),
                'forced_failure',
                store: $store,
                user: $user,
                metadata: ['stream' => $resource],
            );
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        $this->assertNull($result);

        Log::shouldHaveReceived('warning')
            ->with('Security log write failed', \Mockery::on(function (array $context) use ($store, $user): bool {
                return ($context['event_type'] ?? null) === 'forced_failure'
                    && ($context['store_id'] ?? null) === $store->id
                    && ($context['user_id'] ?? null) === $user->id
                    && array_key_exists('error', $context);
            }));
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
