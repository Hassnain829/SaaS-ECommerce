<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\Store;
use App\Models\User;
use App\Support\NotificationEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class NotificationPreferenceService
{
    public function forUser(Store $store, User $user, string $channel): NotificationPreference
    {
        if ($user->is_active === false || $store->roleForUser($user) === null) {
            throw new AuthorizationException(
                'The notification recipient does not belong to this store.'
            );
        }

        $preference = NotificationPreference::query()->firstOrCreate(
            [
                'store_id' => $store->id,
                'user_id' => $user->id,
                'channel' => $channel,
            ],
            [
                'is_enabled' => true,
                'event_types' => NotificationEvent::defaultEventTypes(),
                'settings' => [],
                'quiet_hours' => ['enabled' => false],
            ]
        );

        if (! is_array($preference->event_types) || $preference->event_types === []) {
            $preference->forceFill([
                'event_types' => NotificationEvent::defaultEventTypes(),
            ])->save();
        }

        return $preference->refresh();
    }

    /**
     * @return array{in_app: NotificationPreference, email: NotificationPreference}
     */
    public function pairForUser(Store $store, User $user): array
    {
        return [
            NotificationEvent::CHANNEL_IN_APP => $this->forUser($store, $user, NotificationEvent::CHANNEL_IN_APP),
            NotificationEvent::CHANNEL_EMAIL => $this->forUser($store, $user, NotificationEvent::CHANNEL_EMAIL),
        ];
    }

    /**
     * @param  array{
     *     in_app_enabled?: bool,
     *     email_enabled?: bool,
     *     event_types?: array<string, bool>
     * }  $payload
     * @return array{in_app: NotificationPreference, email: NotificationPreference}
     */
    public function updateForUser(Store $store, User $user, array $payload, ?User $actor = null): array
    {
        $allowedEvents = NotificationEvent::merchantPreferenceEvents();
        $eventTypes = NotificationEvent::defaultEventTypes();

        if (isset($payload['event_types']) && is_array($payload['event_types'])) {
            foreach ($allowedEvents as $event) {
                $eventTypes[$event] = (bool) ($payload['event_types'][$event] ?? false);
            }
        }

        $inApp = $this->forUser($store, $user, NotificationEvent::CHANNEL_IN_APP);
        $inApp->fill([
            'is_enabled' => array_key_exists('in_app_enabled', $payload)
                ? (bool) $payload['in_app_enabled']
                : $inApp->is_enabled,
            'event_types' => $eventTypes,
            'updated_by' => $actor?->id ?? $user->id,
        ])->save();

        $email = $this->forUser($store, $user, NotificationEvent::CHANNEL_EMAIL);
        $email->fill([
            'is_enabled' => array_key_exists('email_enabled', $payload)
                ? (bool) $payload['email_enabled']
                : $email->is_enabled,
            'event_types' => $eventTypes,
            'updated_by' => $actor?->id ?? $user->id,
        ])->save();

        return [
            NotificationEvent::CHANNEL_IN_APP => $inApp->refresh(),
            NotificationEvent::CHANNEL_EMAIL => $email->refresh(),
        ];
    }

    public function allows(Store $store, User $user, string $channel, string $event): bool
    {
        $preference = $this->forUser($store, $user, $channel);

        return $preference->allowsEvent($event);
    }

    /**
     * Default merchant recipients: owners and managers.
     *
     * @return Collection<int, User>
     */
    public function defaultMerchantRecipients(Store $store): Collection
    {
        $store->loadMissing('members');

        return $store->members
            ->filter(function (User $member) use ($store): bool {
                $role = $store->roleForUser($member);

                return in_array($role, [Store::ROLE_OWNER, Store::ROLE_MANAGER], true)
                    && $member->is_active !== false;
            })
            ->values();
    }
}
