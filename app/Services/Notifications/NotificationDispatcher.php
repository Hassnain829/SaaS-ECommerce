<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationEmailJob;
use App\Models\Store;
use App\Models\StoreNotification;
use App\Models\User;
use App\Support\NotificationEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public const MAX_EMAIL_ATTEMPTS = 5;

    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {}

    /**
     * Notify default merchant recipients (owners/managers) for a store event.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $channels
     * @return list<StoreNotification>
     */
    public function notifyStore(
        Store $store,
        string $eventType,
        string $title,
        string $body,
        string $dedupeKey,
        array $data = [],
        ?User $actor = null,
        array $channels = [NotificationEvent::CHANNEL_IN_APP, NotificationEvent::CHANNEL_EMAIL],
    ): array {
        $created = [];

        foreach ($this->preferences->defaultMerchantRecipients($store) as $recipient) {
            $created = array_merge(
                $created,
                $this->notifyUser($store, $recipient, $eventType, $title, $body, $dedupeKey, $data, $actor, $channels)
            );
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $channels
     * @return list<StoreNotification>
     */
    public function notifyUser(
        Store $store,
        User $user,
        string $eventType,
        string $title,
        string $body,
        string $dedupeKey,
        array $data = [],
        ?User $actor = null,
        array $channels = [NotificationEvent::CHANNEL_IN_APP, NotificationEvent::CHANNEL_EMAIL],
    ): array {
        $created = [];
        $recipientKey = 'user:'.$user->id;

        foreach ($channels as $channel) {
            if (! $this->preferences->allows($store, $user, $channel, $eventType)) {
                continue;
            }

            $createdRow = $this->createRow(
                store: $store,
                eventType: $eventType,
                channel: $channel,
                title: $title,
                body: $body,
                dedupeKey: $dedupeKey,
                recipientKey: $recipientKey,
                data: $data,
                actor: $actor,
                user: $user,
                recipientEmail: $user->email,
            );

            if ($createdRow['notification']) {
                $created[] = $createdRow['notification'];
                if ($channel === NotificationEvent::CHANNEL_EMAIL && $createdRow['created']) {
                    SendNotificationEmailJob::dispatch($createdRow['notification']->id);
                }
            }
        }

        return $created;
    }

    /**
     * Customer transactional email (not merchant preference-gated).
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyCustomer(
        Store $store,
        string $email,
        string $eventType,
        string $title,
        string $body,
        string $dedupeKey,
        array $data = [],
        ?User $actor = null,
    ): ?StoreNotification {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if (! NotificationEvent::isCustomerTransactional($eventType)) {
            Log::warning('notification_customer_non_transactional_skipped', [
                'store_id' => $store->id,
                'type' => $eventType,
            ]);

            return null;
        }

        $createdRow = $this->createRow(
            store: $store,
            eventType: $eventType,
            channel: NotificationEvent::CHANNEL_EMAIL,
            title: $title,
            body: $body,
            dedupeKey: $dedupeKey,
            recipientKey: 'email:'.hash('sha256', $email),
            data: array_merge($data, ['audience' => 'customer']),
            actor: $actor,
            user: null,
            recipientEmail: $email,
        );

        if ($createdRow['notification'] && $createdRow['created']) {
            SendNotificationEmailJob::dispatch($createdRow['notification']->id);
        }

        return $createdRow['notification'];
    }

    /**
     * Atomically claim a failed email for retry. Concurrent callers with stale models
     * cannot both transition the same row.
     */
    public function retryEmail(StoreNotification|int $notification): bool
    {
        $id = $notification instanceof StoreNotification ? (int) $notification->id : (int) $notification;

        $updated = StoreNotification::query()
            ->whereKey($id)
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->where('status', NotificationEvent::STATUS_FAILED)
            ->where('attempts', '<', self::MAX_EMAIL_ATTEMPTS)
            ->update([
                'status' => NotificationEvent::STATUS_QUEUED,
                'error_message' => null,
                'failed_at' => null,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            return false;
        }

        SendNotificationEmailJob::dispatch($id);

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{notification: ?StoreNotification, created: bool}
     */
    private function createRow(
        Store $store,
        string $eventType,
        string $channel,
        string $title,
        string $body,
        string $dedupeKey,
        string $recipientKey,
        array $data,
        ?User $actor,
        ?User $user,
        ?string $recipientEmail,
    ): array {
        try {
            $notification = StoreNotification::query()->create([
                'store_id' => $store->id,
                'user_id' => $user?->id,
                'actor_user_id' => $actor?->id,
                'type' => $eventType,
                'channel' => $channel,
                'title' => mb_substr($title, 0, 200),
                'body' => $body,
                'status' => $channel === NotificationEvent::CHANNEL_IN_APP
                    ? NotificationEvent::STATUS_SENT
                    : NotificationEvent::STATUS_QUEUED,
                'data' => $data === [] ? null : $data,
                'dedupe_key' => mb_substr($dedupeKey, 0, 190),
                'recipient_key' => mb_substr($recipientKey, 0, 120),
                'recipient_email' => $recipientEmail,
                'is_read' => false,
                'attempts' => 0,
                'sent_at' => $channel === NotificationEvent::CHANNEL_IN_APP ? now() : null,
            ]);

            return ['notification' => $notification, 'created' => true];
        } catch (UniqueConstraintViolationException $e) {
            $existing = StoreNotification::query()
                ->where('store_id', $store->id)
                ->where('type', $eventType)
                ->where('channel', $channel)
                ->where('dedupe_key', mb_substr($dedupeKey, 0, 190))
                ->where('recipient_key', mb_substr($recipientKey, 0, 120))
                ->first();

            return ['notification' => $existing, 'created' => false];
        }
    }
}
