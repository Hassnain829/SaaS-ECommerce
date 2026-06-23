<?php

namespace App\Support\ProjectHygiene;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Finder\Finder;

final class ProjectRetentionService
{
    public function __construct(
        private readonly ProjectPathGuard $paths,
        private readonly ProjectStorageProtection $protection,
    ) {}

    /**
     * @param  list<string>|null  $categories
     * @return array<string, mixed>
     */
    public function scan(?array $categories = null, ?int $olderThanDaysOverride = null): array
    {
        return $this->run(
            force: false,
            dryRun: true,
            categories: $this->resolveCategories($categories),
            olderThanDaysOverride: $olderThanDaysOverride,
        );
    }

    /**
     * @param  list<string>|null  $categories
     * @return array<string, mixed>
     */
    public function run(
        bool $force,
        bool $dryRun,
        ?array $categories = null,
        ?int $olderThanDaysOverride = null,
    ): array {
        DestructiveHygieneRootGuard::assertAllowed($this->paths, $force, $dryRun);

        $categories = $this->resolveCategories($categories);
        $entries = [];
        $deleted = [];
        $failures = [];

        foreach ($categories as $category) {
            $entries = array_merge($entries, $this->collectCategoryEntries($category, $olderThanDaysOverride));
        }

        $entries = $this->uniqueEntries($entries);
        $shouldDelete = $force && ! $dryRun;

        foreach ($entries as $entry) {
            if ($entry['status'] !== 'eligible') {
                continue;
            }

            if (! $shouldDelete) {
                continue;
            }

            $path = $entry['path'];

            try {
                $this->paths->assertSafeDeletionTarget($path);
            } catch (RuntimeException $exception) {
                $failures[] = ['path' => $path, 'reason' => $exception->getMessage()];

                continue;
            }

            if ($this->protection->isProtected($path)) {
                $failures[] = ['path' => $path, 'reason' => 'Protected at deletion time.'];

                continue;
            }

            if (is_file($path)) {
                if (@unlink($path)) {
                    $deleted[] = $path;
                } elseif (! is_file($path)) {
                    continue;
                } else {
                    $failures[] = ['path' => $path, 'reason' => 'File delete failed.'];
                }

                continue;
            }

            if (is_dir($path)) {
                if ($this->directoryContainsProtectedEntries($path)) {
                    $failures[] = ['path' => $path, 'reason' => 'Directory contains protected entries.'];

                    continue;
                }

                try {
                    File::deleteDirectory($path);
                    if (! is_dir($path)) {
                        $deleted[] = $path;
                    } else {
                        $failures[] = ['path' => $path, 'reason' => 'Directory delete failed.'];
                    }
                } catch (\Throwable $exception) {
                    $failures[] = ['path' => $path, 'reason' => $exception->getMessage()];
                }
            }
        }

        $summary = $this->summarizeEntries($entries);

        return [
            'dry_run' => ! $shouldDelete,
            'force' => $force,
            'categories' => $categories,
            'configured_retention' => $this->configuredRetention($olderThanDaysOverride),
            'entries' => $entries,
            'summary' => $summary,
            'deleted' => $deleted,
            'failures' => $failures,
        ];
    }

    /**
     * @return list<string>
     */
    public function assertKnownCategories(?string $categoryOption): array
    {
        if ($categoryOption === null || $categoryOption === '' || $categoryOption === 'all') {
            return ProjectStorageClassification::RETENTION_CATEGORIES;
        }

        $categories = array_values(array_filter(array_map('trim', explode(',', $categoryOption))));

        foreach ($categories as $category) {
            if (! in_array($category, ProjectStorageClassification::RETENTION_CATEGORIES, true)) {
                throw new RuntimeException('Unknown retention category: '.$category);
            }
        }

        return $categories;
    }

    /**
     * @return list<array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}>
     */
    private function collectCategoryEntries(string $category, ?int $olderThanDaysOverride): array
    {
        return match ($category) {
            'cache' => $this->cacheEntries($olderThanDaysOverride),
            'logs' => $this->logEntries($olderThanDaysOverride),
            'validation-temp' => $this->validationTempEntries($olderThanDaysOverride),
            'source-archives' => $this->sourceArchiveEntries($olderThanDaysOverride),
            'test-artifacts' => $this->testArtifactEntries($olderThanDaysOverride),
            default => throw new RuntimeException('Unknown retention category: '.$category),
        };
    }

    /**
     * @return list<array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}>
     */
    private function cacheEntries(?int $olderThanDaysOverride): array
    {
        $root = $this->paths->projectRoot();
        $cutoff = $this->cacheCutoff($olderThanDaysOverride);
        $entries = [];

        foreach (glob($this->globPath($root.'/bootstrap/cache/*.php')) ?: [] as $file) {
            $entries[] = $this->evaluateFile($file, 'cache', $cutoff, 'Compiled bootstrap cache file');
        }

        foreach ($this->filesIn($root.'/storage/framework/cache/data') as $file) {
            $entries[] = $this->evaluateFile($file, 'cache', $cutoff, 'Framework cache data file');
        }

        foreach ($this->filesIn($root.'/storage/framework/views', fn (string $path): bool => str_ends_with($path, '.php')) as $file) {
            $entries[] = $this->evaluateFile($file, 'cache', $cutoff, 'Compiled Blade view');
        }

        if (config('project_retention.session_cleanup_enabled', false)) {
            foreach ($this->filesIn(
                $root.'/storage/framework/sessions',
                fn (string $path): bool => ! str_ends_with($path, '.gitignore'),
            ) as $file) {
                $entries[] = $this->evaluateFile($file, 'cache', $cutoff, 'Framework session file');
            }
        }

        return $entries;
    }

    /**
     * @return list<array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}>
     */
    private function logEntries(?int $olderThanDaysOverride): array
    {
        $root = $this->paths->projectRoot();
        $cutoff = $this->daysCutoff($olderThanDaysOverride ?? (int) config('project_retention.log_days', 30));
        $activeLog = $this->activeLogPath();
        $entries = [];

        foreach ($this->filesIn(
            $root.'/storage/logs',
            fn (string $path): bool => str_ends_with($path, '.log'),
        ) as $file) {
            $status = 'eligible';
            $reason = 'Old rotated log file';

            if ($this->samePath($file, $activeLog)) {
                $status = 'skipped_active';
                $reason = 'Current active log file';
            } elseif ($this->fileAgeEligible($file, $cutoff)) {
                $status = 'eligible';
            } else {
                $status = 'skipped_recent';
                $reason = 'Within configured retention window';
            }

            $entries[] = $this->buildEntry($file, 'logs', $status, $reason);
        }

        return $entries;
    }

    /**
     * @return list<array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}>
     */
    private function validationTempEntries(?int $olderThanDaysOverride): array
    {
        $root = $this->paths->projectRoot();
        $cutoff = $this->daysCutoff($olderThanDaysOverride ?? (int) config('project_retention.validation_temp_days', 14));
        $entries = [];

        $stagingPattern = $this->globPath($root.'/storage/app/fedex-validation/*/*/FedEx_Integrator_Validation_BaasPlatformFedExSandbox');
        foreach (glob($stagingPattern, GLOB_ONLYDIR) ?: [] as $directory) {
            $entries[] = $this->evaluateDirectory($directory, 'validation-temp', $cutoff, 'FedEx validation staging workspace');
        }

        foreach (glob($this->globPath($root.'/storage/app/fedex-validation/*/*/fedex-validation-diagnostic-*.zip')) ?: [] as $file) {
            $entries[] = $this->evaluateFile($file, 'validation-temp', $cutoff, 'FedEx diagnostic validation ZIP');
        }

        foreach (glob($this->globPath($root.'/storage/app/fedex-validation/*/*/fedex-validation-bundle-*.zip')) ?: [] as $file) {
            $entries[] = $this->evaluateFile($file, 'validation-temp', $cutoff, 'Legacy FedEx validation bundle ZIP');
        }

        if (is_dir($root.'/storage/app/usps-validation')) {
            $finder = (new Finder)->in($root.'/storage/app/usps-validation')->directories()->name('staging');
            foreach ($finder as $directory) {
                $path = str_replace('\\', '/', $directory->getPathname());
                $entries[] = $this->evaluateDirectory($path, 'validation-temp', $cutoff, 'USPS validation staging directory');
            }
        }

        return $entries;
    }

    /**
     * @return list<array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}>
     */
    private function sourceArchiveEntries(?int $olderThanDaysOverride): array
    {
        $directory = $this->paths->projectRoot().'/storage/app/source-archives';
        if (! is_dir($directory)) {
            return [];
        }

        $keepCount = max(0, (int) config('project_retention.source_archive_count', 5));
        $cutoff = $this->daysCutoff($olderThanDaysOverride ?? (int) config('project_retention.source_archive_days', 30));
        $archives = [];

        foreach (glob($this->globPath($directory.'/E_COMMERCE_OFFICE-source-*.zip')) ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }

            $archives[] = [
                'path' => str_replace('\\', '/', $file),
                'mtime' => @filemtime($file) ?: 0,
            ];
        }

        usort($archives, fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        $entries = [];
        foreach ($archives as $index => $archive) {
            $path = $archive['path'];
            $retainedByCount = $index < $keepCount;
            $oldEnough = $archive['mtime'] <= $cutoff->getTimestamp();

            if ($this->protection->isProtected($path)) {
                $entries[] = $this->buildEntry($path, 'source-archives', 'protected', 'Protected source archive');

                continue;
            }

            if ($retainedByCount) {
                $entries[] = $this->buildEntry($path, 'source-archives', 'skipped_recent', 'Retained in latest-N source archives');

                continue;
            }

            if ($oldEnough) {
                $entries[] = $this->buildEntry($path, 'source-archives', 'eligible', 'Source archive exceeds latest-N and max age');
            } else {
                $entries[] = $this->buildEntry($path, 'source-archives', 'skipped_recent', 'Within configured source archive age window');
            }
        }

        return $entries;
    }

    /**
     * @return list<array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}>
     */
    private function testArtifactEntries(?int $olderThanDaysOverride): array
    {
        $root = $this->paths->projectRoot();
        $hours = $olderThanDaysOverride !== null
            ? $olderThanDaysOverride * 24
            : (int) config('project_retention.test_artifact_hours', 24);
        $cutoff = Carbon::now()->subHours(max(0, $hours));
        $entries = [];

        foreach ($this->filesIn($root.'/.phpunit.cache') as $file) {
            $entries[] = $this->evaluateFile($file, 'test-artifacts', $cutoff, 'PHPUnit cache file');
        }

        $resultCache = $root.'/.phpunit.result.cache';
        if (is_file($resultCache)) {
            $entries[] = $this->evaluateFile($resultCache, 'test-artifacts', $cutoff, 'PHPUnit result cache');
        }

        return $entries;
    }

    /**
     * @return array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}
     */
    private function evaluateFile(string $path, string $category, Carbon $cutoff, string $eligibleReason): array
    {
        $normalized = str_replace('\\', '/', $path);
        $unsafe = $this->unsafeReason($normalized);
        if ($unsafe !== null) {
            return $this->buildEntry($normalized, $category, 'skipped_unsafe', $unsafe);
        }

        if ($this->protection->isProtected($normalized)) {
            return $this->buildEntry($normalized, $category, 'protected', 'Protected artifact');
        }

        if ($this->fileAgeEligible($normalized, $cutoff)) {
            return $this->buildEntry($normalized, $category, 'eligible', $eligibleReason);
        }

        return $this->buildEntry($normalized, $category, 'skipped_recent', 'Within configured retention window');
    }

    /**
     * @return array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}
     */
    private function evaluateDirectory(string $path, string $category, Carbon $cutoff, string $eligibleReason): array
    {
        $normalized = str_replace('\\', '/', $path);
        $unsafe = $this->unsafeReason($normalized);
        if ($unsafe !== null) {
            return $this->buildEntry($normalized, $category, 'skipped_unsafe', $unsafe);
        }

        if ($this->protection->isProtected($normalized) || $this->directoryContainsProtectedEntries($normalized)) {
            return $this->buildEntry($normalized, $category, 'protected', 'Protected artifact directory');
        }

        $mtime = @filemtime($normalized) ?: null;
        if ($mtime !== null && $mtime <= $cutoff->getTimestamp()) {
            return $this->buildEntry($normalized, $category, 'eligible', $eligibleReason);
        }

        return $this->buildEntry($normalized, $category, 'skipped_recent', 'Within configured retention window');
    }

    /**
     * @return array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}
     */
    private function buildEntry(string $path, string $category, string $status, string $reason): array
    {
        $mtime = is_file($path) || is_dir($path) ? (@filemtime($path) ?: null) : null;
        $bytes = is_file($path) ? (int) (@filesize($path) ?: 0) : (is_dir($path) ? $this->directoryBytes($path) : 0);

        return [
            'path' => str_replace('\\', '/', $path),
            'category' => $category,
            'bytes' => $bytes,
            'mtime' => $mtime,
            'status' => $status,
            'reason' => $reason,
        ];
    }

    private function unsafeReason(string $absolutePath): ?string
    {
        try {
            $this->paths->assertSafeDeletionTarget($absolutePath);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        $relative = ProjectTrackedFiles::toRelativePath($this->paths->projectRoot(), $absolutePath);

        foreach (ProjectStorageClassification::FORBIDDEN_DELETION_PREFIXES as $prefix) {
            if ($relative === rtrim($prefix, '/') || str_starts_with($relative, $prefix)) {
                return 'Refusing unsafe deletion prefix: '.$prefix;
            }
        }

        return null;
    }

    private function fileAgeEligible(string $path, Carbon $cutoff): bool
    {
        $mtime = @filemtime($path);

        return $mtime !== false && $mtime <= $cutoff->getTimestamp();
    }

    private function cacheCutoff(?int $olderThanDaysOverride): Carbon
    {
        if ($olderThanDaysOverride !== null) {
            return Carbon::now()->subDays(max(0, $olderThanDaysOverride));
        }

        return Carbon::now()->subHours(max(0, (int) config('project_retention.cache_hours', 24)));
    }

    private function daysCutoff(int $days): Carbon
    {
        return Carbon::now()->subDays(max(0, $days));
    }

    private function activeLogPath(): string
    {
        $root = $this->paths->projectRoot().'/storage/logs';
        $channel = (string) config('logging.default', 'stack');
        $channels = (array) config('logging.channels', []);
        $resolved = $channels[$channel]['driver'] ?? 'single';

        if ($resolved === 'daily') {
            return str_replace('\\', '/', $root.'/laravel-'.now()->format('Y-m-d').'.log');
        }

        return str_replace('\\', '/', $root.'/laravel.log');
    }

    /**
     * @param  list<string>|null  $categories
     * @return list<string>
     */
    private function resolveCategories(?array $categories): array
    {
        if ($categories === null || $categories === []) {
            return ProjectStorageClassification::RETENTION_CATEGORIES;
        }

        return $categories;
    }

    /**
     * @param  list<array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}>  $entries
     * @return list<array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}>
     */
    private function uniqueEntries(array $entries): array
    {
        $seen = [];
        $unique = [];

        foreach ($entries as $entry) {
            if (isset($seen[$entry['path']])) {
                continue;
            }

            $seen[$entry['path']] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    /**
     * @param  list<array{path: string, category: string, bytes: int, mtime: int|null, status: string, reason: string}>  $entries
     * @return array<string, int|null>
     */
    private function summarizeEntries(array $entries): array
    {
        $summary = [
            'total_count' => count($entries),
            'total_bytes' => 0,
            'eligible_count' => 0,
            'eligible_bytes' => 0,
            'protected_count' => 0,
            'skipped_count' => 0,
            'oldest_mtime' => null,
            'newest_mtime' => null,
        ];

        foreach ($entries as $entry) {
            $summary['total_bytes'] += $entry['bytes'];

            if ($entry['mtime'] !== null) {
                $summary['oldest_mtime'] = $summary['oldest_mtime'] === null
                    ? $entry['mtime']
                    : min($summary['oldest_mtime'], $entry['mtime']);
                $summary['newest_mtime'] = $summary['newest_mtime'] === null
                    ? $entry['mtime']
                    : max($summary['newest_mtime'], $entry['mtime']);
            }

            if ($entry['status'] === 'eligible') {
                $summary['eligible_count']++;
                $summary['eligible_bytes'] += $entry['bytes'];
            } elseif ($entry['status'] === 'protected') {
                $summary['protected_count']++;
            } else {
                $summary['skipped_count']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, int|bool>
     */
    private function configuredRetention(?int $olderThanDaysOverride): array
    {
        return [
            'enabled' => (bool) config('project_retention.enabled', false),
            'dry_run_default' => (bool) config('project_retention.dry_run', true),
            'require_force' => (bool) config('project_retention.require_force', true),
            'log_days' => $olderThanDaysOverride ?? (int) config('project_retention.log_days', 30),
            'cache_hours' => $olderThanDaysOverride !== null
                ? $olderThanDaysOverride * 24
                : (int) config('project_retention.cache_hours', 24),
            'source_archive_count' => (int) config('project_retention.source_archive_count', 5),
            'source_archive_days' => $olderThanDaysOverride ?? (int) config('project_retention.source_archive_days', 30),
            'validation_temp_days' => $olderThanDaysOverride ?? (int) config('project_retention.validation_temp_days', 14),
            'test_artifact_hours' => $olderThanDaysOverride !== null
                ? $olderThanDaysOverride * 24
                : (int) config('project_retention.test_artifact_hours', 24),
            'session_cleanup_enabled' => (bool) config('project_retention.session_cleanup_enabled', false),
        ];
    }

    /**
     * @param  callable(string): bool|null  $allowed
     * @return list<string>
     */
    private function filesIn(string $directory, ?callable $allowed = null): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $finder = (new Finder)->in($directory)->ignoreDotFiles(false)->files();

        foreach ($finder as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($allowed !== null && ! $allowed($path)) {
                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    private function directoryContainsProtectedEntries(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }

        $finder = (new Finder)->in($directory)->ignoreDotFiles(false);

        foreach ($finder as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($this->protection->isProtected($path)) {
                return true;
            }
        }

        return false;
    }

    private function directoryBytes(string $directory): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $total = 0;
        $finder = (new Finder)->in($directory)->ignoreDotFiles(false)->files();

        foreach ($finder as $file) {
            $total += $file->getSize();
        }

        return $total;
    }

    private function samePath(string $a, string $b): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return strtolower(str_replace('\\', '/', $a)) === strtolower(str_replace('\\', '/', $b));
        }

        return str_replace('\\', '/', $a) === str_replace('\\', '/', $b);
    }

    private function globPath(string $path): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return str_replace('/', DIRECTORY_SEPARATOR, $path);
        }

        return $path;
    }
}
