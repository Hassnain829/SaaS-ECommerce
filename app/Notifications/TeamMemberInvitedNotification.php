<?php

namespace App\Notifications;

use App\Services\Settings\StoreMemberInvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamMemberInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $inviterName,
        public string $storeLabel,
        public bool $needsPassword,
        public string $inviteToken,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('You are invited to '.$this->storeLabel)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->inviterName.' invited you to work in '.$this->storeLabel.' on '.config('app.name').'.');

        if ($this->needsPassword) {
            $message->line('Accept the invitation to choose a password and start using your store access.');
        } else {
            $message->line('Sign in with this email address, then accept the invitation to join with the access they chose for you.');
        }

        return $message
            ->action('Accept invitation', $this->inviteUrl($notifiable))
            ->line('This link expires in '.StoreMemberInvitationService::EXPIRY_DAYS.' days. If you were not expecting this email, you can ignore it.');
    }

    public function inviteUrl(object $notifiable): string
    {
        return app(StoreMemberInvitationService::class)->inviteUrl($this->inviteToken);
    }
}
