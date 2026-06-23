<?php

namespace App\Support\ProjectHygiene;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Isolated sandbox marker and layout for destructive retention/cleanup tests.
 *
 * Real worktree paths must never be used for forced hygiene operations during tests.
 */
final class RetentionTestSandbox
{
    public const MARKER_FILENAME = '.retention-test-sandbox';

    public const ROOT_BASENAME = 'ecommerce-office-retention-tests';

    /**
     * @return list<string>
     */
    public static function defaultLayoutDirectories(): array
    {
        return [
            'storage/logs',
            'storage/app/source-archives',
            'storage/app/fedex-validation',
            'storage/app/usps-validation',
            'storage/framework/cache/data',
            'storage/framework/views',
            'storage/framework/sessions',
            'bootstrap/cache',
        ];
    }

    public static function createRoot(): string
    {
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.self::ROOT_BASENAME
            .DIRECTORY_SEPARATOR.uniqid('', true);

        self::initialize($root);

        return $root;
    }

    public static function initialize(string $root): void
    {
        foreach (self::defaultLayoutDirectories() as $relative) {
            File::ensureDirectoryExists($root.'/'.$relative);
        }

        self::writeMarker($root);
    }

    public static function writeMarker(string $root): void
    {
        File::put($root.'/'.self::MARKER_FILENAME, json_encode([
            'uuid' => (string) Str::uuid(),
            'created_at' => now()->toIso8601String(),
            'environment' => 'testing',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function hasValidMarker(string $root): bool
    {
        $markerPath = $root.'/'.self::MARKER_FILENAME;
        if (! is_file($markerPath)) {
            return false;
        }

        $payload = json_decode((string) file_get_contents($markerPath), true);

        return is_array($payload)
            && ($payload['environment'] ?? null) === 'testing'
            && filled($payload['uuid'] ?? null);
    }

    public static function assertValidSandboxRoot(string $root): void
    {
        if (! is_dir($root)) {
            throw new \InvalidArgumentException('Retention sandbox root does not exist.');
        }

        if (! is_writable($root)) {
            throw new \InvalidArgumentException('Retention sandbox root is not writable.');
        }

        if (self::isRealWorktreePath($root)) {
            throw new \InvalidArgumentException('Retention sandbox root must be outside the real application worktree.');
        }

        if (! self::hasValidMarker($root)) {
            throw new \InvalidArgumentException('Retention sandbox root is missing a valid .retention-test-sandbox marker.');
        }
    }

    public static function isRealWorktreePath(string $path): bool
    {
        $canonical = self::canonicalPath($path);
        if ($canonical === null) {
            return false;
        }

        foreach (self::realWorktreeAnchors() as $anchor) {
            if ($anchor === null) {
                continue;
            }

            if (self::samePath($canonical, $anchor) || self::isUnderPath($canonical, $anchor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string|null>
     */
    private static function realWorktreeAnchors(): array
    {
        return [
            self::canonicalPath(base_path()),
            self::canonicalPath(storage_path()),
            self::canonicalPath(base_path('storage')),
            self::canonicalPath(base_path('storage/app')),
        ];
    }

    private static function canonicalPath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        $real = realpath($normalized);

        return $real !== false ? str_replace('\\', '/', $real) : null;
    }

    private static function isUnderPath(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/').'/';
        $root = rtrim(str_replace('\\', '/', $root), '/').'/';

        if (PHP_OS_FAMILY === 'Windows') {
            return str_starts_with(strtolower($path), strtolower($root));
        }

        return str_starts_with($path, $root);
    }

    private static function samePath(string $a, string $b): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return strtolower($a) === strtolower($b);
        }

        return $a === $b;
    }
}
