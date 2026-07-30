<?php

namespace App\Jobs;

use App\Mail\StoreEventMail;
use App\Models\StoreNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const UNCERTAIN_DELIVERY_MESSAGE = 'Email delivery outcome is uncertain after an interrupted worker. Review before retrying.';

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Unique lock must outlast the full automatic retry window (sum of backoffs + margin).
     */
    public int $uniqueFor = 900;

    public function __construct(
        public int $notificationId,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'send-notification-email:'.$this->notificationId;
    }

    /**
     * Execution-time lock keyed by notification ID across the full retry window.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->expireAfter($this->uniqueFor)
                ->releaseAfter(15),
        ];
    }

    public function handle(): void
    {
        $notification = StoreNotification::query()->with('store')->find($this->notificationId);
        if (! $notification) {
            return;
        }

        if ($notification->channel !== NotificationEvent::CHANNEL_EMAIL) {
            return;
        }

        // Never downgrade a delivered notification.
        if ($notification->status === NotificationEvent::STATUS_SENT) {
            return;
        }

        // Cap check before missing-recipient failure so attempts cannot exceed MAX.
        if ((int) $notification->attempts >= NotificationDispatcher::MAX_EMAIL_ATTEMPTS) {
            $this->markTerminalFailed(
                $notification->error_message ?: 'Email delivery attempts exhausted.',
                incrementAttempts: false
            );

            return;
        }

        if (! filled($notification->recipient_email)) {
            $this->markTerminalFailed('Missing recipient email.', incrementAttempts: true);

            return;
        }

        if (! $this->claimForSending()) {
            $this->recoverStaleSendingIfNeeded();

            return;
        }

        $notification = $notification->fresh();
        if (! $notification || $notification->status !== NotificationEvent::STATUS_SENDING) {
            return;
        }

        try {
            Mail::mailer(config('mail.default'))->send(new StoreEventMail($notification));
        } catch (Throwable $e) {
            $this->releaseForRetryOrFail($e->getMessage() ?: 'Email delivery failed.');

            throw $e;
        }

        StoreNotification::query()
            ->whereKey($this->notificationId)
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->where('status', NotificationEvent::STATUS_SENDING)
            ->update([
                'status' => NotificationEvent::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
                'updated_at' => now(),
            ]);
    }

    public function failed(?Throwable $exception): void
    {
        // Terminal only — attempts were already incremented on each retryable failure.
        $this->markTerminalFailed(
            $exception?->getMessage() ?: 'Email delivery failed.',
            incrementAttempts: false
        );
    }

    private function claimForSending(): bool
    {
        $claimed = StoreNotification::query()
            ->whereKey($this->notificationId)
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->where('status', NotificationEvent::STATUS_QUEUED)
            ->where('attempts', '<', NotificationDispatcher::MAX_EMAIL_ATTEMPTS)
            ->update([
                'status' => NotificationEvent::STATUS_SENDING,
                'updated_at' => now(),
            ]);

        return $claimed === 1;
    }

    /**
     * Concurrent attempt 1 that loses the claim stays a no-op.
     * A later queue attempt/redelivery that still sees `sending` marks the row failed
     * (uncertain outcome — do not auto-resend) so merchants can review and retry.
     */
    private function recoverStaleSendingIfNeeded(): void
    {
        if ($this->jobAttemptNumber() <= 1) {
            return;
        }

        StoreNotification::query()
            ->whereKey($this->notificationId)
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->where('status', NotificationEvent::STATUS_SENDING)
            ->update([
                'status' => NotificationEvent::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => self::UNCERTAIN_DELIVERY_MESSAGE,
                'updated_at' => now(),
            ]);
    }

    private function jobAttemptNumber(): int
    {
        return method_exists($this, 'attempts') ? max(1, (int) $this->attempts()) : 1;
    }

    private function releaseForRetryOrFail(string $message): void
    {
        $isFinalJobAttempt = $this->jobAttemptNumber() >= $this->tries;

        if ($isFinalJobAttempt) {
            StoreNotification::query()
                ->whereKey($this->notificationId)
                ->where('channel', NotificationEvent::CHANNEL_EMAIL)
                ->whereIn('status', [
                    NotificationEvent::STATUS_SENDING,
                    NotificationEvent::STATUS_QUEUED,
                ])
                ->update([
                    'status' => NotificationEvent::STATUS_FAILED,
                    'failed_at' => now(),
                    'error_message' => mb_substr($message, 0, 2000),
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);

            return;
        }

        StoreNotification::query()
            ->whereKey($this->notificationId)
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->where('status', NotificationEvent::STATUS_SENDING)
            ->update([
                'status' => NotificationEvent::STATUS_QUEUED,
                'error_message' => mb_substr($message, 0, 2000),
                'failed_at' => null,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);
    }

    private function markTerminalFailed(string $message, bool $incrementAttempts): void
    {
        $query = StoreNotification::query()
            ->whereKey($this->notificationId)
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->whereIn('status', [
                NotificationEvent::STATUS_QUEUED,
                NotificationEvent::STATUS_SENDING,
                NotificationEvent::STATUS_FAILED,
            ])
            ->where('status', '!=', NotificationEvent::STATUS_SENT);

        $payload = [
            'status' => NotificationEvent::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => mb_substr($message, 0, 2000),
            'updated_at' => now(),
        ];

        if ($incrementAttempts) {
            $query->where('attempts', '<', NotificationDispatcher::MAX_EMAIL_ATTEMPTS);
            $payload['attempts'] = DB::raw('attempts + 1');
        }

        $query->update($payload);
    }
}
