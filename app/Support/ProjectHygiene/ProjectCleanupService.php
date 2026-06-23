<?php

namespace App\Support\ProjectHygiene;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Finder\Finder;

final class ProjectCleanupService
{
    /** @var list<string> */
    public const PROTECTED_RELATIVE_PATHS = [
        'storage/app/fedex-validation',
        'storage/app/usps-validation',
    ];

    /** @var list<string> */
    public const PROTECTED_CARRIER_SUBPATHS = [
        '/labels/',
        '/uploads/',
    ];

    /** @var list<string> */
    public const PLACEHOLDER_GITIGNORE_PATHS = [
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
    ];

    public function __construct(
        private readonly ProjectPathGuard $paths,
    ) {}

    /**
     * @return list<array{path: string, category: string, reason: string}>
     */
    public function targets(?string $category = null): array
    {
        $categories = $category === null || $category === 'all'
            ? ['cache', 'logs', 'carrier-validation']
            : [$category];

        $targets = [];

        foreach ($categories as $name) {
            $targets = array_merge($targets, match ($name) {
                'cache' => $this->cacheTargets(),
                'logs' => $this->logTargets(),
                'carrier-validation' => $this->carrierValidationTargets(),
                default => throw new RuntimeException('Unknown cleanup category: '.$name),
            });
        }

        return $this->uniqueTargets($targets);
    }

    /**
     * @return array{dry_run: bool, deleted: list<string>, skipped: list<string>, categories: list<string>}
     */
    public function cleanup(bool $force, ?string $category = null, bool $dryRun = true): array
    {
        $targets = $this->targets($category);
        $deleted = [];
        $skipped = [];

        foreach ($targets as $target) {
            $path = $target['path'];

            try {
                $this->paths->assertSafeDeletionTarget($path);
            } catch (RuntimeException $exception) {
                $skipped[] = $path.' ('.$exception->getMessage().')';

                continue;
            }

            if ($this->isProtectedPath($path) || $this->isProtectedFromDeletion($path)) {
                $skipped[] = $path.' (protected)';

                continue;
            }

            if ($dryRun || ! $force) {
                continue;
            }

            if (is_file($path)) {
                if (@unlink($path)) {
                    $deleted[] = $path;
                } else {
                    $skipped[] = $path.' (delete failed)';
                }

                continue;
            }

            if (is_dir($path)) {
                if ($this->directoryContainsProtectedEntries($path)) {
                    $skipped[] = $path.' (contains protected or tracked files)';

                    continue;
                }

                File::deleteDirectory($path);
                $deleted[] = $path;
            }
        }

        return [
            'dry_run' => $dryRun || ! $force,
            'deleted' => $deleted,
            'skipped' => $skipped,
            'categories' => $category === null ? ['cache', 'logs', 'carrier-validation'] : [$category],
        ];
    }

    /**
     * @return list<array{path: string, category: string, reason: string}>
     */
    private function cacheTargets(): array
    {
        $root = $this->paths->projectRoot();
        $targets = [];

        foreach (glob($root.'/bootstrap/cache/*.php') ?: [] as $file) {
            if ($this->isProtectedFromDeletion($file)) {
                continue;
            }

            $targets[] = [
                'path' => $file,
                'category' => 'cache',
                'reason' => 'Compiled bootstrap cache file',
            ];
        }

        foreach ($this->runtimeFilesIn($root.'/storage/framework/cache/data', fn (string $file): bool => true) as $file) {
            $targets[] = [
                'path' => $file,
                'category' => 'cache',
                'reason' => 'Framework cache data file',
            ];
        }

        foreach ($this->runtimeFilesIn(
            $root.'/storage/framework/views',
            fn (string $file): bool => str_ends_with($file, '.php'),
        ) as $file) {
            $targets[] = [
                'path' => $file,
                'category' => 'cache',
                'reason' => 'Compiled Blade view',
            ];
        }

        foreach ($this->runtimeFilesIn(
            $root.'/storage/framework/sessions',
            fn (string $file): bool => ! str_ends_with($file, '.gitignore'),
        ) as $file) {
            $targets[] = [
                'path' => $file,
                'category' => 'cache',
                'reason' => 'Framework session file',
            ];
        }

        foreach ($this->runtimeFilesIn($root.'/.phpunit.cache', fn (string $file): bool => true) as $file) {
            $targets[] = [
                'path' => $file,
                'category' => 'cache',
                'reason' => 'PHPUnit cache file',
            ];
        }

        $phpunitResultCache = $root.'/.phpunit.result.cache';
        if (is_file($phpunitResultCache) && ! $this->isProtectedFromDeletion($phpunitResultCache)) {
            $targets[] = [
                'path' => $phpunitResultCache,
                'category' => 'cache',
                'reason' => 'PHPUnit result cache',
            ];
        }

        return $targets;
    }

    /**
     * @return list<array{path: string, category: string, reason: string}>
     */
    private function logTargets(): array
    {
        $targets = [];
        foreach ($this->runtimeFilesIn(
            $this->paths->projectRoot().'/storage/logs',
            fn (string $file): bool => str_ends_with($file, '.log'),
        ) as $file) {
            $targets[] = [
                'path' => $file,
                'category' => 'logs',
                'reason' => 'Application log file',
            ];
        }

        return $targets;
    }

    /**
     * @return list<array{path: string, category: string, reason: string}>
     */
    private function carrierValidationTargets(): array
    {
        $root = $this->paths->projectRoot();
        $targets = [];

        $stagingPattern = $root.'/storage/app/fedex-validation/*/*/FedEx_Integrator_Validation_BaasPlatformFedExSandbox';
        foreach (glob($stagingPattern, GLOB_ONLYDIR) ?: [] as $directory) {
            if ($this->isProtectedPath($directory) || $this->directoryContainsProtectedEntries($directory)) {
                continue;
            }

            $targets[] = [
                'path' => $directory,
                'category' => 'carrier-validation',
                'reason' => 'Temporary FedEx validation bundle staging directory',
            ];
        }

        $uspsStaging = $root.'/storage/app/usps-validation/**/staging';
        foreach (glob(str_replace('/**/', '/*/', $uspsStaging), GLOB_ONLYDIR) ?: [] as $directory) {
            if ($this->directoryContainsProtectedEntries($directory)) {
                continue;
            }

            $targets[] = [
                'path' => $directory,
                'category' => 'carrier-validation',
                'reason' => 'Temporary USPS validation staging directory',
            ];
        }

        return $targets;
    }

    /**
     * @param  list<array{path: string, category: string, reason: string}>  $targets
     * @return list<array{path: string, category: string, reason: string}>
     */
    private function uniqueTargets(array $targets): array
    {
        $seen = [];
        $unique = [];

        foreach ($targets as $target) {
            $path = str_replace('\\', '/', $target['path']);
            if (isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;
            $unique[] = array_merge($target, ['path' => $path]);
        }

        return $unique;
    }

    private function isProtectedPath(string $path): bool
    {
        $relative = ProjectTrackedFiles::toRelativePath($this->paths->projectRoot(), $path);

        foreach (self::PROTECTED_CARRIER_SUBPATHS as $protected) {
            if (str_contains($relative, trim($protected, '/'))) {
                return true;
            }
        }

        return false;
    }

    private function isProtectedFromDeletion(string $path): bool
    {
        if (basename($path) === '.gitignore') {
            return true;
        }

        if (ProjectTrackedFiles::isTracked($this->paths->projectRoot(), $path)) {
            return true;
        }

        $relative = ProjectTrackedFiles::toRelativePath($this->paths->projectRoot(), $path);

        return in_array($relative, self::PLACEHOLDER_GITIGNORE_PATHS, true);
    }

    private function directoryContainsProtectedEntries(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }

        $finder = (new Finder)->in($directory)->ignoreDotFiles(false);

        foreach ($finder as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if ($this->isProtectedFromDeletion($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  callable(string): bool  $allowed
     * @return list<string>
     */
    private function runtimeFilesIn(string $directory, callable $allowed): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $finder = (new Finder)->in($directory)->ignoreDotFiles(false)->files();

        foreach ($finder as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (! $allowed($path) || $this->isProtectedFromDeletion($path)) {
                continue;
            }

            $files[] = $path;
        }

        return $files;
    }
}
