<?php

namespace App\Services;

use App\Models\ConnectedSite;
use App\Models\ConnectedSiteEventDelivery;
use App\Models\ConnectedSiteOutboxEvent;
use App\Services\Security\OutboundUrlGuard;
use App\Support\ConnectedSiteEventSignature;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ConnectedSiteCatalogEventDeliveryService
{
    /**
     * @var list<int>
     */
    private const BACKOFF_SECONDS = [60, 300, 900, 3600, 21600];

    public function __construct(
        private readonly OutboundUrlGuard $outboundUrlGuard,
    ) {}

    public function deliver(ConnectedSiteEventDelivery $delivery): void
    {
        $delivery->refresh();
        if ($delivery->isDelivered()) {
            return;
        }

        $event = $delivery->event;
        $site = $delivery->site;
        if (! $event || ! $site || ! $site->isActive()) {
            $this->markFailed($delivery, 'Connected site is no longer active.');

            return;
        }

        // Soft-deleted (closed) stores must not receive outbound WordPress/catalog posts.
        $site->loadMissing('store');
        if (! $site->store) {
            $this->markFailed($delivery, 'Store is closed or unavailable; outbound catalog delivery stopped.');

            return;
        }

        if (app()->environment('testing') && ! (bool) config('connected_sites.deliver_in_tests', false)) {
            return;
        }

        $secret = (string) ($site->event_signing_secret ?? '');
        $target = $this->deliveryUrl($site);
        if ($secret === '' || $target === null) {
            $this->scheduleRetry($delivery, 'Website address or event signing secret is missing.');

            return;
        }

        $body = $this->payload($event);
        $raw = json_encode($body, JSON_UNESCAPED_SLASHES);
        if (! is_string($raw)) {
            $this->markFailed($delivery, 'Could not encode catalog event.');

            return;
        }

        $timestamp = (string) time();
        $signature = ConnectedSiteEventSignature::sign($secret, $timestamp, $event->public_id, $raw);
        $timeout = max(2, (int) config('connected_sites.delivery_timeout_seconds', 8));

        try {
            // Resolve, validate, and pin immediately before the outbound request.
            $validatedTarget = $this->outboundUrlGuard->validate($target);
            $response = Http::timeout($timeout)
                ->withOptions($validatedTarget['options'])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Eco-Portal-Catalog-Events/1.0',
                    'X-Eco-Event-Id' => $event->public_id,
                    'X-Eco-Timestamp' => $timestamp,
                    'X-Eco-Signature' => $signature,
                    'X-Eco-Event-Type' => $event->type,
                ])
                ->withBody($raw, 'application/json')
                ->post($validatedTarget['url']);
        } catch (\Throwable $exception) {
            $this->scheduleRetry($delivery, $exception->getMessage());

            return;
        }

        $this->interpretResponse($delivery, $response);
    }

    public function retryDue(int $limit = 50): int
    {
        if (! Schema::hasTable('connected_site_event_deliveries')) {
            return 0;
        }

        $processed = 0;
        ConnectedSiteEventDelivery::query()
            ->with(['event', 'site.store'])
            ->where('status', ConnectedSiteEventDelivery::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (ConnectedSiteEventDelivery $delivery) use (&$processed): void {
                $site = $delivery->site;
                // Closed stores: SoftDeletes makes site.store null — fail terminally, do not retry cycle.
                if ($site && $site->isActive() && ! $site->store) {
                    $this->markFailed($delivery, 'Store is closed or unavailable; outbound catalog delivery stopped.');
                    $processed++;

                    return;
                }

                $this->deliver($delivery);
                $processed++;
            });

        return $processed;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ConnectedSiteOutboxEvent $event): array
    {
        $payload = is_array($event->payload) ? $event->payload : [];

        return [
            'id' => $event->public_id,
            'type' => $event->type,
            'occurred_at' => optional($event->occurred_at)?->toIso8601String(),
            'catalog_version' => $event->catalog_version,
            'store' => [
                'id' => $event->store_id,
            ],
            'resource' => [
                'product_id' => $payload['product_id'] ?? null,
                'variant_id' => $payload['variant_id'] ?? null,
                'category_id' => $payload['category_id'] ?? null,
                'published' => $payload['published'] ?? null,
            ],
        ];
    }

    public function deliveryUrl(ConnectedSite $site): ?string
    {
        $base = rtrim((string) $site->site_url, '/');
        if ($base === '') {
            return null;
        }

        $target = $base.'/wp-json/eco-portal/v1/events';
        $parts = parse_url($target);
        $expected = parse_url((string) ($site->site_url_normalized ?: $site->site_url));
        if (! is_array($parts) || ! is_array($expected)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $expectedHost = strtolower((string) ($expected['host'] ?? ''));
        if ($host === '' || $expectedHost === '' || ! hash_equals($expectedHost, $host)) {
            return null;
        }

        return $target;
    }

    private function interpretResponse(ConnectedSiteEventDelivery $delivery, Response $response): void
    {
        $status = $response->status();
        if ($status >= 200 && $status < 300) {
            $delivery->forceFill([
                'status' => ConnectedSiteEventDelivery::STATUS_DELIVERED,
                'attempt_count' => (int) $delivery->attempt_count + 1,
                'last_http_status' => $status,
                'last_error' => null,
                'delivered_at' => now(),
                'next_retry_at' => null,
            ])->save();

            return;
        }

        $this->scheduleRetry($delivery, 'HTTP '.$status, $status);
    }

    private function scheduleRetry(ConnectedSiteEventDelivery $delivery, string $error, ?int $httpStatus = null): void
    {
        $attempts = (int) $delivery->attempt_count + 1;
        $max = max(1, (int) config('connected_sites.max_delivery_attempts', 10));
        $terminal = $attempts >= $max;
        $delay = self::BACKOFF_SECONDS[min($attempts - 1, count(self::BACKOFF_SECONDS) - 1)];

        $delivery->forceFill([
            'status' => $terminal
                ? ConnectedSiteEventDelivery::STATUS_FAILED
                : ConnectedSiteEventDelivery::STATUS_PENDING,
            'attempt_count' => $attempts,
            'last_error' => mb_substr($error, 0, 500),
            'last_http_status' => $httpStatus,
            'next_retry_at' => $terminal ? null : now()->addSeconds($delay),
        ])->save();
    }

    private function markFailed(ConnectedSiteEventDelivery $delivery, string $error): void
    {
        ConnectedSiteEventDelivery::query()->whereKey($delivery->id)->update([
            'status' => ConnectedSiteEventDelivery::STATUS_FAILED,
            'attempt_count' => (int) $delivery->attempt_count + 1,
            'last_error' => mb_substr($error, 0, 500),
            'next_retry_at' => null,
        ]);

        $delivery->refresh();
    }
}
