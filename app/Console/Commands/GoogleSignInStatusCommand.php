<?php

namespace App\Console\Commands;

use App\Services\GoogleSignInService;
use Illuminate\Console\Command;

class GoogleSignInStatusCommand extends Command
{
    protected $signature = 'google:status';

    protected $description = 'Show whether Continue with Google is enabled (does not print secrets)';

    public function handle(GoogleSignInService $googleSignIn): int
    {
        if ($googleSignIn->isEnabled()) {
            $this->info('Google Sign-In: enabled');

            return self::SUCCESS;
        }

        $this->warn('Google Sign-In: disabled');
        $this->line('Fill GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in this server .env.');
        $this->line('Leave GOOGLE_REDIRECT_URI empty, then run: php artisan config:clear && php artisan config:cache');

        return self::SUCCESS;
    }
}
