<?php

namespace Tests\Feature;

use App\Support\ProjectHygiene\ProjectCleanupService;
use App\Support\ProjectHygiene\ProjectHygieneReporter;
use App\Support\ProjectHygiene\ProjectPathGuard;
use App\Support\ProjectHygiene\ProjectSourceArchiveService;
use App\Support\ProjectHygiene\ProjectTrackedFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesRetentionTestSandbox;
use Tests\TestCase;

class ProjectHygieneCommandsTest extends TestCase
{
    use CreatesRetentionTestSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectTrackedFiles::clearCache();
    }

    public function test_source_archive_plan_excludes_env_vendor_and_validation_artifacts(): void
    {
        $plan = (new ProjectSourceArchiveService(ProjectPathGuard::forProject()))->plan(listFiles: true);

        $this->assertGreaterThan(0, $plan['included_file_count']);
        $this->assertNotContains('.env', $plan['files'] ?? []);
        $this->assertContains('.env.example', $plan['files'] ?? []);
        $this->assertTrue(collect($plan['files'] ?? [])->every(
            fn (string $path): bool => ! str_starts_with($path, 'vendor/')
                && ! str_starts_with($path, 'node_modules/')
                && ! str_starts_with($path, 'storage/app/fedex-validation/')
        ));

        foreach (['app/', 'config/', 'database/', 'resources/', 'routes/', 'tests/', 'docs/'] as $prefix) {
            $this->assertTrue($plan['required_paths_present'][$prefix], $prefix.' should be represented');
        }
    }

    public function test_source_archive_creates_and_inspects_actual_zip(): void
    {
        if (! is_dir(base_path().'/.git')) {
            $this->markTestSkipped('Git repository required for source archive integration test.');
        }

        $tempArchive = sys_get_temp_dir().DIRECTORY_SEPARATOR.'project-hygiene-archive-'.uniqid('', true).'.zip';
        $service = new ProjectSourceArchiveService(ProjectPathGuard::forProject());

        try {
            $result = $service->create(dryRun: false, archivePathOverride: $tempArchive);

            $this->assertTrue($result['created']);
            $this->assertFileExists($tempArchive);
            $this->assertGreaterThan(0, (int) $result['archive_size_bytes']);
            $this->assertMatchesRegularExpression(
                '/E_COMMERCE_OFFICE-source-\d{8}-\d{6}\.zip$/',
                $result['archive_name'],
            );

            $entries = $service->listGitArchiveEntries($tempArchive);
            $this->assertNotEmpty($entries);

            $forbiddenPrefixes = ['vendor/', 'node_modules/', 'dev-test-storefront/node_modules/', '.git/'];
            foreach ($forbiddenPrefixes as $needle) {
                $this->assertFalse(
                    collect($entries)->contains(fn (string $entry): bool => str_starts_with($entry, $needle)),
                    'Forbidden archive entry prefix found: '.$needle,
                );
            }

            $secretEnvEntries = collect($entries)->filter(
                fn (string $entry): bool => $entry === '.env'
                    || (str_starts_with($entry, '.env.')
                        && ! str_ends_with($entry, '.example')
                        && $entry !== '.env.testing.example')
            );
            $this->assertTrue($secretEnvEntries->isEmpty(), 'Secret env files must not appear in archive: '.$secretEnvEntries->implode(', '));

            foreach (ProjectSourceArchiveService::REQUIRED_TEMPLATE_PATHS as $required) {
                $this->assertContains($required, $entries, $required.' must be present in archive');
            }

            foreach (ProjectSourceArchiveService::REQUIRED_SOURCE_PATHS as $required) {
                $this->assertContains($required, $entries, $required.' must be present in archive');
            }

            foreach (ProjectSourceArchiveService::REQUIRED_PLACEHOLDER_PATHS as $placeholder) {
                if ($this->isTrackedInHeadCommit($placeholder)) {
                    $this->assertContains($placeholder, $entries, $placeholder.' placeholder must be present');
                }
            }

            $this->assertTrue(
                collect($entries)->contains(fn (string $entry): bool => str_starts_with($entry, 'database/migrations/')),
                'database/migrations/ must be represented in archive',
            );

            $this->assertFalse(
                collect($entries)->contains(fn (string $entry): bool => str_ends_with($entry, '.log')),
                'Runtime log files must not appear in archive',
            );

            $this->assertFalse(
                collect($entries)->contains(fn (string $entry): bool => str_starts_with($entry, 'storage/app/fedex-validation/')
                    && ! str_ends_with($entry, '.gitignore')
                    && ! str_ends_with($entry, '/')),
                'Generated FedEx validation evidence must not appear in archive',
            );
        } finally {
            if (is_file($tempArchive)) {
                @unlink($tempArchive);
            }
        }
    }

    public function test_cleanup_dry_run_deletes_nothing(): void
    {
        $root = base_path();
        $tempLog = $root.'/storage/logs/project-hygiene-dry-run.log';
        File::put($tempLog, 'temporary log line');

        $result = (new ProjectCleanupService(ProjectPathGuard::forProject()))->cleanup(
            force: false,
            category: 'logs',
            dryRun: true,
        );

        $this->assertTrue($result['dry_run']);
        $this->assertFileExists($tempLog);
        $this->assertSame([], $result['deleted']);

        File::delete($tempLog);
    }

    public function test_cleanup_force_deletes_only_approved_log_target(): void
    {
        $this->withCleanupSandbox(function (string $root, ProjectCleanupService $service): void {
            $tempLog = $root.'/storage/logs/project-hygiene-force.log';
            File::put($tempLog, 'temporary log line');

            $result = $service->cleanup(force: true, category: 'logs', dryRun: false);

            $this->assertFalse($result['dry_run']);
            $this->assertNotEmpty($result['deleted']);
            $this->assertFileDoesNotExist($tempLog);
        });
    }

    public function test_cleanup_force_cache_preserves_tracked_gitignore_placeholders(): void
    {
        $this->withCleanupSandbox(function (string $root, ProjectCleanupService $service): void {
            $gitignore = $root.'/storage/framework/views/.gitignore';
            File::ensureDirectoryExists(dirname($gitignore));
            File::put($gitignore, "*\n!.gitignore\n");
            File::put($root.'/storage/framework/views/hygiene-test-view.php', '<?php echo 1;');
            File::put($root.'/storage/framework/cache/data/hygiene-test-cache.bin', 'cache');

            $result = $service->cleanup(force: true, category: 'cache', dryRun: false);

            $this->assertFileExists($gitignore);
            $this->assertNotContains(str_replace('\\', '/', $gitignore), $result['deleted']);
        });
    }

    public function test_cleanup_force_never_deletes_git_tracked_file(): void
    {
        $this->withCleanupSandbox(function (string $root, ProjectCleanupService $service): void {
            $protected = $root.'/.env.example';
            File::put($protected, "APP_NAME=Sandbox\n");

            $result = $service->cleanup(force: true, category: 'all', dryRun: false);

            $this->assertFileExists($protected);
            $this->assertNotContains(str_replace('\\', '/', $protected), $result['deleted']);
        });
    }

    public function test_path_guard_rejects_unix_and_nested_traversal(): void
    {
        $guard = ProjectPathGuard::forProject(base_path());

        foreach (['../outside', 'storage/../../outside', 'storage/logs/../../../../../etc/passwd'] as $path) {
            $this->assertFalse($guard->isWithinProject($path), $path.' should be rejected');
        }
    }

    public function test_path_guard_rejects_windows_and_mixed_separator_traversal(): void
    {
        $guard = ProjectPathGuard::forProject(base_path());

        foreach (['../outside', '..\\outside', 'storage/../../outside', 'storage/logs/../../../outside'] as $path) {
            $this->assertFalse($guard->isWithinProject($path), $path.' should be rejected');
        }
    }

    public function test_path_guard_rejects_non_existing_outside_target(): void
    {
        $guard = ProjectPathGuard::forProject(base_path());

        $this->assertFalse($guard->isWithinProject('../outside/non-existing-file.log'));
    }

    public function test_path_guard_accepts_valid_internal_runtime_path(): void
    {
        $guard = ProjectPathGuard::forProject(base_path());

        $this->assertTrue($guard->isWithinProject('storage/logs/project-hygiene-internal.log'));
    }

    public function test_path_guard_rejects_symlink_outside_project_root(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('Symlink support unavailable.');
        }

        $this->withCleanupSandbox(function (string $root): void {
            $outsideDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'project-hygiene-outside-'.uniqid('', true);
            $linkPath = $root.'/storage/logs/hygiene-outside-link';

            File::ensureDirectoryExists($outsideDir);
            File::put($outsideDir.'/secret.txt', 'outside');

            try {
                if (is_link($linkPath) || file_exists($linkPath)) {
                    @unlink($linkPath);
                }

                if (! @symlink($outsideDir, $linkPath)) {
                    $this->markTestSkipped('Unable to create symlink in this environment.');
                }

                $guard = ProjectPathGuard::forProject($root);
                $this->assertFalse($guard->isWithinProject($linkPath));
            } finally {
                if (is_link($linkPath)) {
                    @unlink($linkPath);
                }
                File::deleteDirectory($outsideDir);
            }
        });
    }

    public function test_cleanup_refuses_paths_outside_project_root(): void
    {
        $guard = ProjectPathGuard::forProject(base_path());

        $this->expectException(\RuntimeException::class);
        $guard->assertWithinProject('/outside/project/path');
    }

    public function test_carrier_validation_cleanup_preserves_label_and_upload_paths(): void
    {
        $this->withCleanupSandbox(function (string $root): void {
            $labelDir = $root.'/storage/app/fedex-validation/99/labels';
            $uploadDir = $root.'/storage/app/fedex-validation/99/uploads';
            File::ensureDirectoryExists($labelDir);
            File::ensureDirectoryExists($uploadDir);
            File::put($labelDir.'/label.pdf', '%PDF-test');
            File::put($uploadDir.'/scan.pdf', '%PDF-test');

            $targets = collect((new ProjectCleanupService(ProjectPathGuard::forProject($root)))->targets('carrier-validation'))
                ->pluck('path');

            $this->assertFalse($targets->contains(fn (string $path): bool => str_contains($path, '/labels/')));
            $this->assertFalse($targets->contains(fn (string $path): bool => str_contains($path, '/uploads/')));
        });
    }

    public function test_hygiene_report_does_not_include_env_secret_values(): void
    {
        $report = (new ProjectHygieneReporter(ProjectPathGuard::forProject()))->report();
        $encoded = json_encode($report);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('sk_test_', $encoded);
        $this->assertStringNotContainsString('sk_live_', $encoded);
        $this->assertStringNotContainsString('FEDEX_SANDBOX_CLIENT_SECRET=', $encoded);
    }

    public function test_project_commands_are_registered(): void
    {
        $this->artisan('project:hygiene-report')->assertExitCode(0);
        $this->artisan('project:cleanup')->assertExitCode(0);
        $this->artisan('project:source-archive --dry-run')->assertExitCode(0);
        $this->artisan('project:retention --dry-run')->assertExitCode(0);
    }

    private function isTrackedInHeadCommit(string $relativePath): bool
    {
        if (! is_dir(base_path().'/.git')) {
            return false;
        }

        $process = proc_open(
            'git ls-tree -r HEAD --name-only',
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );

        if (! is_resource($process)) {
            return false;
        }

        $listed = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        foreach (preg_split('/\R/', $listed) ?: [] as $line) {
            if (trim($line) === $relativePath) {
                return true;
            }
        }

        return false;
    }
}
