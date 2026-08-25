<?php

namespace App\Services\Catalog;

/**
 * Reversible gallery file moves for product permanent delete.
 *
 * @param  list<array{original: string, quarantine: string}>  $entries
 */
final class ProductGalleryQuarantineSession
{
    public function __construct(
        public readonly string $operationId,
        public readonly array $entries,
    ) {}
}
