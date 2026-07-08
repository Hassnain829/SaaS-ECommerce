<?php

namespace App\Services\Carriers\USPS\Support;

use RuntimeException;

final class USPSMerchantOAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'oauth_unavailable',
    ) {
        parent::__construct($message);
    }
}
