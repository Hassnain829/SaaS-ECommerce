<?php

namespace Tests\Feature;

use App\Models\IdempotencyKey;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Checkout\CheckoutIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckoutIdempotencyClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_key_is_rejected_before_a_claim_can_be_created(): void
    {
        $store = $this->store('Missing Key Store');
        $request = Request::create('/api/v1/checkout', 'POST', ['cart' => [['variant_id' => 1]]]);

        try {
            app(CheckoutIdempotencyService::class)->replayOrStart($store, $request, $request->all());
            $this->fail('A checkout without an Idempotency-Key was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }

        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_unfinished_claim_blocks_the_same_attempt_and_only_the_owner_can_release_it(): void
    {
        $store = $this->store('Concurrent Claim Store');
        $service = app(CheckoutIdempotencyService::class);
        $payload = ['cart' => [['variant_id' => 10, 'quantity' => 1]]];
        $ownerRequest = $this->request('checkout-attempt-1', $payload);
        $concurrentRequest = $this->request('checkout-attempt-1', $payload);

        $this->assertNull($service->replayOrStart($store, $ownerRequest, $payload));
        $blocked = $service->replayOrStart($store, $concurrentRequest, $payload);

        $this->assertNotNull($blocked);
        $this->assertSame(409, $blocked->getStatusCode());
        $this->assertSame('idempotency_in_progress', $blocked->getData(true)['code']);
        $this->assertSame('1', $blocked->headers->get('Retry-After'));
        $this->assertDatabaseCount('idempotency_keys', 1);

        $service->releaseOwnUnfinishedClaim($store, $concurrentRequest);
        $this->assertDatabaseCount('idempotency_keys', 1);

        $service->releaseOwnUnfinishedClaim($store, $ownerRequest);
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_completed_claim_replays_and_changed_payload_conflicts(): void
    {
        $store = $this->store('Replay Claim Store');
        $service = app(CheckoutIdempotencyService::class);
        $payload = ['cart' => [['variant_id' => 11, 'quantity' => 1]]];
        $request = $this->request('checkout-attempt-2', $payload);

        $this->assertNull($service->replayOrStart($store, $request, $payload));
        $service->remember($store, $request, ['checkout' => ['id' => 55]], 201, 55);

        $replay = $service->replayOrStart($store, $this->request('checkout-attempt-2', $payload), $payload);
        $this->assertNotNull($replay);
        $this->assertSame(201, $replay->getStatusCode());
        $this->assertSame(55, $replay->getData(true)['checkout']['id']);
        $this->assertNotNull(IdempotencyKey::query()->sole()->completed_at);

        $changed = ['cart' => [['variant_id' => 11, 'quantity' => 2]]];
        try {
            $service->replayOrStart($store, $this->request('checkout-attempt-2', $changed), $changed);
            $this->fail('A completed key was accepted with a changed payload.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_the_same_key_is_isolated_by_store(): void
    {
        $service = app(CheckoutIdempotencyService::class);
        $payload = ['cart' => [['variant_id' => 12, 'quantity' => 1]]];

        $this->assertNull($service->replayOrStart($this->store('Key Store A'), $this->request('shared-key', $payload), $payload));
        $this->assertNull($service->replayOrStart($this->store('Key Store B'), $this->request('shared-key', $payload), $payload));
        $this->assertDatabaseCount('idempotency_keys', 2);
    }

    private function request(string $key, array $payload): Request
    {
        return Request::create(
            '/api/v1/checkout',
            'POST',
            $payload,
            [],
            [],
            ['HTTP_IDEMPOTENCY_KEY' => $key]
        );
    }

    private function store(string $name): Store
    {
        $role = Role::query()->firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);

        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }
}
