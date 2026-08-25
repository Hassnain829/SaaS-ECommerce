<?php

namespace App\Exceptions\Catalog;

use RuntimeException;

final class ProductPermanentDeleteBlockedException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
