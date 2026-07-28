<?php

namespace App\Services\Notifications;

use App\Models\Store;
use App\Models\StoreNotification;
use App\Models\User;
use App\Support\NotificationEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationQueryService
{
    public function unreadCount(Store $store, User $user): int
    {
        return StoreNotification::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('channel', NotificationEvent::CHANNEL_IN_APP)
            ->where('is_read', false)
            ->count();
    }

    /**
     * @param  array{status?: string, type?: string, category?: string, q?: string}  $filters
     */
    public function paginateForUser(Store $store, User $user, array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        $query = StoreNotification::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->latest('id');

        if (($filters['status'] ?? null) === 'failed') {
            $query->where('channel', NotificationEvent::CHANNEL_EMAIL)
                ->where('status', NotificationEvent::STATUS_FAILED);
        } else {
            $query->where('channel', NotificationEvent::CHANNEL_IN_APP);

            if (($filters['status'] ?? null) === 'unread') {
                $query->where('is_read', false);
            }
        }

        if (! empty($filters['type'])) {
            $query->where('type', (string) $filters['type']);
        } elseif (! empty($filters['category'])) {
            $events = NotificationEvent::eventsForCategory((string) $filters['category']);
            if ($events !== []) {
                $query->whereIn('type', $events);
            }
        }

        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('title', 'like', $term)
                    ->orWhere('body', 'like', $term);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Group notifications for the center UI.
     *
     * @param  Collection<int, StoreNotification>  $notifications
     * @return array{Today: Collection, Yesterday: Collection, Older: Collection}
     */
    public function groupByRecency(Collection $notifications): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        $groups = [
            'Today' => collect(),
            'Yesterday' => collect(),
            'Older' => collect(),
        ];

        foreach ($notifications as $notification) {
            $created = $notification->created_at?->copy()->startOfDay();
            if ($created && $created->equalTo($today)) {
                $groups['Today']->push($notification);
            } elseif ($created && $created->equalTo($yesterday)) {
                $groups['Yesterday']->push($notification);
            } else {
                $groups['Older']->push($notification);
            }
        }

        return $groups;
    }

    public function findForUser(Store $store, User $user, int $notificationId): StoreNotification
    {
        return StoreNotification::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->whereKey($notificationId)
            ->firstOrFail();
    }

    public function markAllRead(Store $store, User $user): int
    {
        $now = now();

        return StoreNotification::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('channel', NotificationEvent::CHANNEL_IN_APP)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => $now,
                'status' => NotificationEvent::STATUS_READ,
                'updated_at' => $now,
            ]);
    }

    /**
     * Failed merchant email rows for the current user (retryable).
     *
     * @return Collection<int, StoreNotification>
     */
    public function failedEmailsForUser(Store $store, User $user, int $limit = 10): Collection
    {
        return StoreNotification::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->where('status', NotificationEvent::STATUS_FAILED)
            ->latest('failed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Failed customer transactional emails for the current store.
     *
     * @return Collection<int, StoreNotification>
     */
    public function failedCustomerEmailsForStore(Store $store, int $limit = 20): Collection
    {
        return StoreNotification::query()
            ->where('store_id', $store->id)
            ->whereNull('user_id')
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->where('status', NotificationEvent::STATUS_FAILED)
            ->where(function ($query): void {
                $query->where('data->audience', 'customer')
                    ->orWhere('recipient_key', 'like', 'email:%');
            })
            ->latest('failed_at')
            ->limit($limit)
            ->get();
    }

    public function findFailedCustomerEmail(Store $store, int $notificationId): StoreNotification
    {
        return StoreNotification::query()
            ->where('store_id', $store->id)
            ->whereNull('user_id')
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->where('status', NotificationEvent::STATUS_FAILED)
            ->where(function ($query): void {
                $query->where('data->audience', 'customer')
                    ->orWhere('recipient_key', 'like', 'email:%');
            })
            ->whereKey($notificationId)
            ->firstOrFail();
    }
}
