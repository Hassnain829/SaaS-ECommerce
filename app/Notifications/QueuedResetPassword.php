<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\URL;

class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * Build the reset URL without putting the account email in the query string.
     * Merchants enter their email on the reset form; the token remains the secret.
     */
    protected function resetUrl($notifiable): string
    {
        return URL::route('password.reset', [
            'token' => $this->token,
        ], absolute: true);
    }
}
