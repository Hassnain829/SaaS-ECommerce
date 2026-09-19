<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TeamMemberInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $inviterName,
        public string $storeLabel,
        public bool $needsPassword,
        public string $storeIds = '',
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
            $message->line('Accept the invitation to join this store with the access they chose for you.');
        }

        return $message
            ->action('Accept invitation', $this->inviteUrl($notifiable))
            ->line('This link expires in 7 days. If you were not expecting this email, you can ignore it.');
    }

    public function inviteUrl(object $notifiable): string
    {
        $parameters = ['user' => $notifiable->id];
        if ($this->storeIds !== '') {
            $parameters['stores'] = $this->storeIds;
        }

        return URL::temporarySignedRoute(
            'team-invites.show',
            now()->addDays(7),
            $parameters,
            absolute: true,
        );
    }
}
