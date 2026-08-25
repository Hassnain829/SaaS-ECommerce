<?php

namespace App\Exceptions\Catalog;

use RuntimeException;

final class ProductPermanentDeleteCleanupPendingException extends RuntimeException
{
    /**
     * @param  list<string>  $pendingPaths
     */
    public function __construct(
        public readonly string $operationId,
        public readonly array $pendingPaths,
        string $message = 'Product permanent delete succeeded; quarantine cleanup is pending retry.',
    ) {
        parent::__construct($message);
    }
}
