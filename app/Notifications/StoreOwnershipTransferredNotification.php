<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StoreOwnershipTransferredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $storeName,
        public string $previousOwnerName,
        public string $newOwnerName,
        public int $actorId,
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
        $isNewOwner = (int) $notifiable->id !== $this->actorId;

        $message = (new MailMessage)
            ->subject('Ownership changed for '.$this->storeName)
            ->greeting('Hello '.$notifiable->name.',');

        if ($isNewOwner) {
            $message
                ->line($this->previousOwnerName.' transferred ownership of '.$this->storeName.' to you.')
                ->line('You now have full owner control for this store, including team, settings, and owner-only actions.');
        } else {
            $message
                ->line('You transferred ownership of '.$this->storeName.' to '.$this->newOwnerName.'.')
                ->line('You remain a team member with full operational access. Ask the new owner if you need owner-level control again.');
        }

        return $message->action('Open team members', route('team-members.index'));
    }
}
