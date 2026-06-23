<?php

namespace App\Support\ProjectHygiene;

use RuntimeException;

final class UnsafeRetentionTestRootException extends RuntimeException
{
    public static function blocked(string $reason): self
    {
        return new self($reason);
    }
}
