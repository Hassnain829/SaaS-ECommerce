<?php

namespace App\Services\Carriers\FedEx\Validation;

final class FedExLabelArtifactValidator
{
    public static function isValid(string $absolutePath, string $expectedFormat): bool
    {
        if (! is_file($absolutePath) || filesize($absolutePath) <= 0) {
            return false;
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 8) ?: '';
        fclose($handle);

        return match (strtoupper($expectedFormat)) {
            'PNG' => str_starts_with($header, "\x89PNG\r\n\x1a\n"),
            'ZPL', 'ZPLII' => self::looksLikeZpl(file_get_contents($absolutePath) ?: ''),
            default => str_starts_with($header, '%PDF'),
        };
    }

    private static function looksLikeZpl(string $contents): bool
    {
        $trimmed = ltrim($contents);

        return $trimmed !== ''
            && (str_contains($trimmed, '^XA') || str_contains($trimmed, '^XZ') || str_contains($trimmed, '^FO'));
    }
}
