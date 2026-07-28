<?php

namespace App\Mail;

use App\Models\StoreNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StoreEventMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public StoreNotification $notification,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
            to: [$this->notification->recipient_email],
        );
    }

    public function content(): Content
    {
        $isCustomer = ($this->notification->data['audience'] ?? null) === 'customer';

        return new Content(
            markdown: $isCustomer ? 'emails.customer-event' : 'emails.merchant-event',
            with: [
                'notification' => $this->notification,
                'actionUrl' => $this->notification->actionUrl(),
                'actionLabel' => $this->notification->actionLabel(),
                'storeName' => $this->notification->store?->name,
            ],
        );
    }
}
