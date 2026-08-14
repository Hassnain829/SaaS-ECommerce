<?php

namespace App\Services\Payments;

use App\Models\ProviderWebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;

class ProviderWebhookEventService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function claim(string $provider, string $eventId, string $eventType, ?string $intentId = null, array $payload = []): ?ProviderWebhookEvent
    {
        $eventId = trim($eventId);
        if ($eventId === '') {
            return new ProviderWebhookEvent([
                'provider' => $provider,
                'provider_event_id' => '',
                'event_type' => $eventType,
                'status' => ProviderWebhookEvent::STATUS_PROCESSING,
            ]);
        }

        $existing = ProviderWebhookEvent::query()
            ->where('provider', $provider)
            ->where('provider_event_id', $eventId)
            ->first();

        if ($existing) {
            if ($existing->processed_at !== null) {
                return null;
            }

            return $existing;
        }

        try {
            return ProviderWebhookEvent::query()->create([
                'provider' => $provider,
                'provider_event_id' => $eventId,
                'event_type' => $eventType,
                'provider_intent_id' => $intentId,
                'status' => ProviderWebhookEvent::STATUS_PROCESSING,
                'payload' => $payload === [] ? null : $payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            $race = ProviderWebhookEvent::query()
                ->where('provider', $provider)
                ->where('provider_event_id', $eventId)
                ->first();

            if ($race?->processed_at !== null) {
                return null;
            }

            return $race;
        }
    }

    public function markProcessed(ProviderWebhookEvent $event, ?string $skipReason = null): void
    {
        if ($event->id === null) {
            return;
        }

        $event->forceFill([
            'status' => $skipReason ? ProviderWebhookEvent::STATUS_SKIPPED : ProviderWebhookEvent::STATUS_PROCESSED,
            'skip_reason' => $skipReason,
            'processed_at' => now(),
        ])->save();
    }
}
