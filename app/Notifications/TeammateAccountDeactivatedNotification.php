<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeammateAccountDeactivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $storeName,
        public string $memberName,
        public string $memberEmail,
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
        return (new MailMessage)
            ->subject($this->memberName.' deactivated their account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->memberName.' ('.$this->memberEmail.') deactivated their '.config('app.name').' login.')
            ->line('This only closes their own sign-in. '.$this->storeName.' and your owner access are unchanged.')
            ->line('They can no longer open the store until their account is reactivated. You can also remove or suspend them from Team members if you no longer need that membership.')
            ->action('Open team members', route('team-members.index'));
    }
}
