<?php

namespace App\Support\ProjectHygiene;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

final class ProjectHygieneReporter
{
    /** @var list<string> */
    private const LEAK_PATTERNS = [
        '.env',
        'database/database.sqlite',
        'vendor/',
        'node_modules/',
        'storage/logs/',
        'bootstrap/cache/',
        '.phpunit.cache',
    ];

    public function __construct(
        private readonly ProjectPathGuard $paths,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $root = $this->paths->projectRoot();

        $directories = [
            'vendor' => $root.'/vendor',
            'node_modules' => $root.'/node_modules',
            'dev_test_storefront_node_modules' => $root.'/dev-test-storefront/node_modules',
            'storage' => $root.'/storage',
            'storage_logs' => $root.'/storage/logs',
            'storage_framework_cache' => $root.'/storage/framework/cache',
            'storage_framework_views' => $root.'/storage/framework/views',
            'fedex_validation' => $root.'/storage/app/fedex-validation',
            'usps_validation' => $root.'/storage/app/usps-validation',
            'bootstrap_cache' => $root.'/bootstrap/cache',
            'phpunit_cache' => $root.'/.phpunit.cache',
        ];

        $sizes = [];
        foreach ($directories as $key => $path) {
            $sizes[$key] = $this->directoryStats($path);
        }

        return [
            'project_root' => $root,
            'total_project_size_bytes' => $this->directorySize($root),
            'total_project_size_human' => $this->humanSize($this->directorySize($root)),
            'directories' => $sizes,
            'largest_directories' => $this->largestTopLevelDirectories($root, 10),
            'potential_archive_leaks' => $this->detectPotentialLeaks($root),
            'git_tracked_secret_risk_files' => $this->trackedSecretRiskFiles(),
            'retention_preview' => $this->retentionPreview(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function retentionPreview(): array
    {
        $retention = new ProjectRetentionService(
            $this->paths,
            new ProjectStorageProtection($this->paths),
        );

        $scan = $retention->scan();

        return [
            'summary' => $scan['summary'],
            'configured_retention' => $scan['configured_retention'],
            'categories' => $scan['categories'],
        ];
    }

    /**
     * @return array{exists: bool, bytes: int, human: string, file_count: int}
     */
    private function directoryStats(string $path): array
    {
        if (! is_dir($path)) {
            return ['exists' => false, 'bytes' => 0, 'human' => '0 B', 'file_count' => 0];
        }

        $bytes = $this->directorySize($path);
        $count = $this->fileCount($path);

        return [
            'exists' => true,
            'bytes' => $bytes,
            'human' => $this->humanSize($bytes),
            'file_count' => $count,
        ];
    }

    private function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return is_file($path) ? (int) filesize($path) : 0;
        }

        $total = 0;
        $finder = (new Finder)->in($path)->ignoreDotFiles(false)->files();

        foreach ($finder as $file) {
            $total += $file->getSize();
        }

        return $total;
    }

    private function fileCount(string $path): int
    {
        if (! is_dir($path)) {
            return is_file($path) ? 1 : 0;
        }

        return iterator_count((new Finder)->in($path)->ignoreDotFiles(false)->files());
    }

    /**
     * @return list<array{path: string, bytes: int, human: string}>
     */
    private function largestTopLevelDirectories(string $root, int $limit): array
    {
        $entries = [];

        foreach (File::directories($root) as $directory) {
            $bytes = $this->directorySize($directory);
            $entries[] = [
                'path' => basename($directory),
                'bytes' => $bytes,
                'human' => $this->humanSize($bytes),
            ];
        }

        usort($entries, fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes']);

        return array_slice($entries, 0, $limit);
    }

    /**
     * @return list<array{category: string, path: string, note: string}>
     */
    private function detectPotentialLeaks(string $root): array
    {
        $leaks = [];

        foreach (self::LEAK_PATTERNS as $pattern) {
            $full = $root.'/'.trim($pattern, '/');
            if (str_ends_with($pattern, '/')) {
                if (is_dir($full) && $this->fileCount($full) > 0) {
                    $leaks[] = [
                        'category' => 'runtime_or_dependency_directory',
                        'path' => $pattern,
                        'note' => 'Present in worktree; must not ship in source archives.',
                    ];
                }

                continue;
            }

            if (is_file($full)) {
                $leaks[] = [
                    'category' => str_contains($pattern, '.env') ? 'environment_secret_file' : 'local_database_file',
                    'path' => $pattern,
                    'note' => 'Local-only file present; exclude from archives and git.',
                ];
            }
        }

        foreach (glob($root.'/*.zip') ?: [] as $zip) {
            $leaks[] = [
                'category' => 'local_archive_output',
                'path' => basename($zip),
                'note' => 'Temporary archive output at repository root.',
            ];
        }

        return $leaks;
    }

    /**
     * @return list<string>
     */
    private function trackedSecretRiskFiles(): array
    {
        if (! is_dir($this->paths->projectRoot().'/.git')) {
            return [];
        }

        $output = [];
        $process = proc_open(
            'git ls-files',
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->paths->projectRoot(),
        );

        if (! is_resource($process)) {
            return [];
        }

        $listed = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        foreach (preg_split('/\R/', $listed) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($line === '.env'
                || str_starts_with($line, '.env.')
                && ! str_ends_with($line, '.example')
                || $line === 'database/database.sqlite'
                || str_contains($line, 'vendor/')
                || str_contains($line, 'node_modules/')) {
                $output[] = $line;
            }
        }

        return $output;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2).' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
