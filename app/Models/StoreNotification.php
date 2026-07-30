<?php

namespace App\Models;

use App\Support\NotificationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'store_id',
        'user_id',
        'actor_user_id',
        'type',
        'channel',
        'title',
        'body',
        'status',
        'data',
        'dedupe_key',
        'recipient_key',
        'recipient_email',
        'is_read',
        'attempts',
        'sent_at',
        'failed_at',
        'read_at',
        'error_message',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function markRead(): void
    {
        if ($this->is_read) {
            return;
        }

        $this->forceFill([
            'is_read' => true,
            'read_at' => now(),
            'status' => $this->channel === NotificationEvent::CHANNEL_IN_APP
                ? NotificationEvent::STATUS_READ
                : $this->status,
        ])->save();
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => NotificationEvent::STATUS_SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => NotificationEvent::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => mb_substr($message, 0, 2000),
            'attempts' => (int) $this->attempts + 1,
        ])->save();
    }

    public function actionUrl(): ?string
    {
        $url = $this->data['action_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function actionLabel(): string
    {
        $label = $this->data['action_label'] ?? null;

        return is_string($label) && $label !== '' ? $label : 'View details';
    }

    public function presentation(): array
    {
        return NotificationEvent::presentation((string) $this->type);
    }
}
