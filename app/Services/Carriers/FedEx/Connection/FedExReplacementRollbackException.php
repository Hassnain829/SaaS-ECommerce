<?php

namespace App\Services\Carriers\FedEx\Connection;

/**
 * Thrown inside the reconnect replacement transaction so retirement + activation roll back together.
 * Incoming/session failure is recorded in a separate short transaction after rollback.
 */
final class FedExReplacementRollbackException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $fallbackMessage,
    ) {
        parent::__construct($fallbackMessage);
    }
}
