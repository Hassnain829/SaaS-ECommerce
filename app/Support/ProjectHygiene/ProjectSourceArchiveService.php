<?php

namespace App\Support\ProjectHygiene;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class ProjectSourceArchiveService
{
    /** @var list<string> */
    private const EXCLUDED_PREFIXES = [
        '.git/',
        'vendor/',
        'node_modules/',
        'dev-test-storefront/node_modules/',
        'storage/logs/',
        'storage/framework/cache/',
        'storage/framework/sessions/',
        'storage/framework/views/',
        'storage/app/fedex-validation/',
        'storage/app/usps-validation/',
        'storage/app/source-archives/',
        'bootstrap/cache/',
        '.phpunit.cache/',
        'database/database.sqlite',
    ];

    /** @var list<string> */
    private const EXCLUDED_EXACT = [
        '.env',
        '.env.local',
        '.env.production',
        '.env.staging',
        '.env.backup',
        '.env.testing',
        '.phpunit.result.cache',
        'dev-test-storefront/.env',
    ];

    /** @var list<string> */
    public const REQUIRED_PLACEHOLDER_PATHS = [
        'bootstrap/cache/.gitignore',
        'storage/logs/.gitignore',
        'storage/framework/cache/.gitignore',
        'storage/framework/cache/data/.gitignore',
        'storage/framework/sessions/.gitignore',
        'storage/framework/views/.gitignore',
        'storage/app/.gitignore',
    ];

    /** @var list<string> */
    public const REQUIRED_TEMPLATE_PATHS = [
        '.env.example',
        'dev-test-storefront/.env.example',
    ];

    /** @var list<string> */
    public const REQUIRED_SOURCE_PATHS = [
        'composer.json',
        'package.json',
        'README.md',
        'AGENTS.md',
        'ENTERPRISE_PROJECT_CONTEXT.md',
        'ENTERPRISE_ROADMAP_2026.md',
    ];

    public function __construct(
        private readonly ProjectPathGuard $paths,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(bool $listFiles = false): array
    {
        $files = $this->collectSourceFiles();
        $excludedCategories = $this->summarizeExcludedCategories();

        return [
            'archive_name' => $this->archiveFilename(),
            'output_directory' => $this->outputDirectory(),
            'included_file_count' => count($files),
            'excluded_categories' => $excludedCategories,
            'files' => $listFiles ? $files : null,
            'required_paths_present' => $this->requiredPathsPresent($files),
            'git_required' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function create(bool $dryRun = false, ?string $archivePathOverride = null): array
    {
        $this->assertGitAvailable();

        $plan = $this->plan(listFiles: false);

        if ($dryRun) {
            return array_merge($plan, [
                'dry_run' => true,
                'created' => false,
                'archive_path' => null,
                'archive_size_bytes' => 0,
                'archive_size_human' => '0 B',
            ]);
        }

        $archivePath = $archivePathOverride ?? $this->defaultArchivePath();
        File::ensureDirectoryExists(dirname($archivePath));
        $this->createViaGitArchive($archivePath);

        $size = is_file($archivePath) ? (int) filesize($archivePath) : 0;

        return array_merge($plan, [
            'dry_run' => false,
            'created' => true,
            'archive_path' => $archivePath,
            'archive_size_bytes' => $size,
            'archive_size_human' => $this->humanSize($size),
        ]);
    }

    /**
     * @return list<string>
     */
    public function listGitArchiveEntries(string $archivePath): array
    {
        if (! is_file($archivePath)) {
            throw new RuntimeException('Archive file does not exist.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open archive for inspection.');
        }

        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && $name !== '') {
                $entries[] = str_replace('\\', '/', $name);
            }
        }

        $zip->close();
        sort($entries);

        return $entries;
    }

    private function assertGitAvailable(): void
    {
        if (! is_dir($this->paths->projectRoot().'/.git')) {
            throw new RuntimeException(
                'Git repository required. Source archives must be created with `git archive` so export-ignore rules apply.'
            );
        }
    }

    private function archiveFilename(): string
    {
        return 'E_COMMERCE_OFFICE-source-'.now()->format('Ymd-His').'.zip';
    }

    private function outputDirectory(): string
    {
        return $this->paths->projectRoot().'/storage/app/source-archives';
    }

    private function defaultArchivePath(): string
    {
        return $this->outputDirectory().DIRECTORY_SEPARATOR.$this->archiveFilename();
    }

    /**
     * @return list<string>
     */
    private function collectSourceFiles(): array
    {
        $this->assertGitAvailable();

        $process = proc_open(
            'git ls-files -z',
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->paths->projectRoot(),
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to enumerate git tracked files.');
        }

        $listed = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $files = [];
        foreach (explode("\0", $listed) as $relative) {
            $relative = trim(str_replace('\\', '/', $relative));
            if ($relative === '' || $this->shouldExclude($relative)) {
                continue;
            }

            $absolute = $this->paths->projectRoot().'/'.$relative;
            if (is_file($absolute)) {
                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }

    private function shouldExclude(string $relativePath): bool
    {
        if (in_array($relativePath, self::EXCLUDED_EXACT, true)) {
            return true;
        }

        if (str_starts_with($relativePath, '.env.')
            && ! str_ends_with($relativePath, '.example')) {
            return true;
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        if (str_ends_with($relativePath, '.log')
            || (str_ends_with($relativePath, '.zip') && str_contains($relativePath, 'fedex-validation'))) {
            return true;
        }

        if (preg_match('/(^|[\/\\\\])[A-Za-z]:[\/\\\\]/', $relativePath)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function summarizeExcludedCategories(): array
    {
        return [
            'environment_secrets (.env and non-template .env.* variants)',
            'vendor/',
            'node_modules/ (root and dev-test-storefront/)',
            'runtime storage contents (logs, cache, sessions, compiled views)',
            'generated carrier validation evidence (fedex-validation/, usps-validation/)',
            'bootstrap/cache runtime files (placeholders preserved via export-ignore exceptions)',
            'local sqlite database files',
            'local archive outputs',
        ];
    }

    /**
     * @param  list<string>  $files
     * @return array<string, bool>
     */
    private function requiredPathsPresent(array $files): array
    {
        $requiredPrefixes = [
            'app/' => false,
            'config/' => false,
            'database/' => false,
            'resources/' => false,
            'routes/' => false,
            'tests/' => false,
            'docs/' => false,
        ];

        foreach ($files as $file) {
            foreach (array_keys($requiredPrefixes) as $prefix) {
                if (str_starts_with($file, $prefix)) {
                    $requiredPrefixes[$prefix] = true;
                }
            }
        }

        return $requiredPrefixes;
    }

    private function createViaGitArchive(string $archivePath): void
    {
        $command = sprintf(
            'git archive --worktree-attributes --format=zip --output=%s HEAD',
            escapeshellarg($archivePath),
        );

        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->paths->projectRoot(),
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start git archive.');
        }

        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr) !== '' ? trim($stderr) : 'git archive failed.');
        }
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
