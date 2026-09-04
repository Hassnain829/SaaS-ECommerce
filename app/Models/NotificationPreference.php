<?php

namespace App\Models;

use App\Support\NotificationEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'store_id',
        'user_id',
        'channel',
        'is_enabled',
        'event_types',
        'settings',
        'quiet_hours',
        'locale',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'event_types' => 'array',
        'settings' => 'array',
        'quiet_hours' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function allowsEvent(string $event): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        $types = is_array($this->event_types) ? $this->event_types : NotificationEvent::defaultEventTypes();

        if (! array_key_exists($event, $types)) {
            return true;
        }

        return (bool) $types[$event];
    }

    /**
     * Quiet hours: ['enabled' => bool, 'start' => '22:00', 'end' => '07:00', 'timezone' => 'UTC']
     */
    public function isInQuietHours(?\DateTimeInterface $at = null): bool
    {
        $hours = is_array($this->quiet_hours) ? $this->quiet_hours : [];
        if (empty($hours['enabled'])) {
            return false;
        }

        $start = (string) ($hours['start'] ?? '');
        $end = (string) ($hours['end'] ?? '');
        if ($start === '' || $end === '') {
            return false;
        }

        $timezone = (string) ($hours['timezone'] ?? 'UTC');
        try {
            $now = Carbon::instance($at ?? now())->timezone($timezone);
        } catch (\Throwable) {
            $now = Carbon::instance($at ?? now())->timezone('UTC');
        }

        $startAt = $now->copy()->setTimeFromTimeString($start);
        $endAt = $now->copy()->setTimeFromTimeString($end);

        if ($startAt->equalTo($endAt)) {
            return false;
        }

        if ($startAt->lessThan($endAt)) {
            return $now->betweenIncluded($startAt, $endAt);
        }

        // Overnight window (e.g. 22:00–07:00).
        return $now->greaterThanOrEqualTo($startAt) || $now->lessThanOrEqualTo($endAt);
    }
}
