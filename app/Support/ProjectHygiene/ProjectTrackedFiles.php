<?php

namespace App\Support\ProjectHygiene;

use RuntimeException;

final class ProjectTrackedFiles
{
    /** @var array<string, list<string>> */
    private static array $cache = [];

    /**
     * @return list<string>
     */
    public static function relativePaths(string $projectRoot): array
    {
        $root = str_replace('\\', '/', realpath($projectRoot) ?: $projectRoot);

        if (isset(self::$cache[$root])) {
            return self::$cache[$root];
        }

        if (! is_dir($root.'/.git')) {
            self::$cache[$root] = self::fallbackProtectedRelativePaths();

            return self::$cache[$root];
        }

        $process = proc_open(
            'git ls-files -z',
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to enumerate git tracked files for cleanup protection.');
        }

        $listed = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $files = [];
        foreach (explode("\0", $listed) as $relative) {
            $relative = trim(str_replace('\\', '/', $relative));
            if ($relative !== '') {
                $files[] = $relative;
            }
        }

        sort($files);
        self::$cache[$root] = $files;

        return $files;
    }

    public static function isTracked(string $projectRoot, string $absoluteOrRelativePath): bool
    {
        $relative = self::toRelativePath($projectRoot, $absoluteOrRelativePath);

        return in_array($relative, self::relativePaths($projectRoot), true);
    }

    public static function toRelativePath(string $projectRoot, string $absoluteOrRelativePath): string
    {
        $guard = ProjectPathGuard::forProject($projectRoot);
        $absolute = $guard->resolve($absoluteOrRelativePath);
        $root = rtrim(str_replace('\\', '/', $guard->projectRoot()), '/').'/';

        if (! str_starts_with(str_replace('\\', '/', $absolute), $root)) {
            return str_replace('\\', '/', ltrim($absoluteOrRelativePath, '/'));
        }

        return ltrim(substr(str_replace('\\', '/', $absolute), strlen($root)), '/');
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return list<string>
     */
    private static function fallbackProtectedRelativePaths(): array
    {
        return [
            '.gitignore',
            '.env.example',
            '.env.testing.example',
            'bootstrap/cache/.gitignore',
            'storage/logs/.gitignore',
            'storage/framework/cache/.gitignore',
            'storage/framework/cache/data/.gitignore',
            'storage/framework/sessions/.gitignore',
            'storage/framework/views/.gitignore',
            'storage/app/.gitignore',
            'storage/app/fedex-validation/.gitignore',
            'storage/app/usps-validation/.gitignore',
            'storage/app/source-archives/.gitignore',
            'dev-test-storefront/.env.example',
        ];
    }
}
