<?php

namespace Tests\Feature;

use App\Support\ProjectHygiene\ProjectCleanupService;
use App\Support\ProjectHygiene\ProjectPathGuard;
use App\Support\ProjectHygiene\ProjectRetentionService;
use App\Support\ProjectHygiene\ProjectStorageProtection;
use App\Support\ProjectHygiene\RetentionTestSandbox;
use App\Support\ProjectHygiene\UnsafeRetentionTestRootException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesRetentionTestSandbox;
use Tests\TestCase;

class RetentionTestIsolationTest extends TestCase
{
    use CreatesRetentionTestSandbox;
    use RefreshDatabase;

    public function test_force_retention_artisan_against_real_base_path_is_blocked(): void
    {
        config(['project_retention.enabled' => true]);

        $sentinel = base_path('.env.example');
        $hashBefore = hash_file('sha256', $sentinel);

        $exitCode = Artisan::call('project:retention', [
            '--force' => true,
            '--category' => 'logs',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Destructive hygiene is blocked', $output);
        $this->assertStringContainsString('No files were changed', $output);
        $this->assertSame($hashBefore, hash_file('sha256', $sentinel));
    }

    public function test_force_cleanup_artisan_against_real_base_path_is_blocked(): void
    {
        $sentinel = base_path('composer.json');
        $hashBefore = hash_file('sha256', $sentinel);

        $exitCode = Artisan::call('project:cleanup', [
            '--force' => true,
            '--category' => 'logs',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Destructive hygiene is blocked', $output);
        $this->assertStringContainsString('No files were changed', $output);
        $this->assertSame($hashBefore, hash_file('sha256', $sentinel));
    }

    public function test_force_retention_against_storage_path_root_is_blocked(): void
    {
        config(['project_retention.enabled' => true]);

        $paths = ProjectPathGuard::forProject(storage_path());
        $service = new ProjectRetentionService($paths, new ProjectStorageProtection($paths));

        $this->expectException(UnsafeRetentionTestRootException::class);
        $this->expectExceptionMessage('No files were changed');

        $service->run(force: true, dryRun: false, categories: ['logs']);
    }

    public function test_force_retention_against_real_storage_app_subdirectory_is_blocked(): void
    {
        config(['project_retention.enabled' => true]);

        $paths = ProjectPathGuard::forProject(storage_path('app'));
        $service = new ProjectRetentionService($paths, new ProjectStorageProtection($paths));

        $this->expectException(UnsafeRetentionTestRootException::class);

        $service->run(force: true, dryRun: false, categories: ['validation-temp']);
    }

    public function test_temp_directory_without_marker_is_rejected_for_force(): void
    {
        config(['project_retention.enabled' => true]);

        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ecommerce-office-retention-tests'.DIRECTORY_SEPARATOR.uniqid('unmarked-', true);
        File::ensureDirectoryExists($root.'/storage/logs');

        try {
            $paths = ProjectPathGuard::forProject($root);
            $service = new ProjectRetentionService($paths, new ProjectStorageProtection($paths));

            $this->expectException(UnsafeRetentionTestRootException::class);
            $this->expectExceptionMessage('marked retention sandbox');

            $service->run(force: true, dryRun: false, categories: ['logs']);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_marked_sandbox_accepts_force_and_deletes_only_sandbox_files(): void
    {
        config(['project_retention.enabled' => true, 'project_retention.log_days' => 0]);

        $this->withRetentionSandbox(function (string $root, ProjectRetentionService $service): void {
            $oldLog = $root.'/storage/logs/sandbox-old.log';
            $keepLog = $root.'/storage/logs/laravel.log';
            File::put($oldLog, 'delete-me');
            File::put($keepLog, 'keep');
            touch($oldLog, time() - 86400);

            $service->run(force: true, dryRun: false, categories: ['logs']);

            $this->assertFileDoesNotExist($oldLog);
            $this->assertFileExists($keepLog);
        });
    }

    public function test_invalid_marker_format_is_rejected(): void
    {
        config(['project_retention.enabled' => true]);

        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ecommerce-office-retention-tests'.DIRECTORY_SEPARATOR.uniqid('bad-marker-', true);
        File::ensureDirectoryExists($root.'/storage/logs');
        File::put($root.'/'.RetentionTestSandbox::MARKER_FILENAME, '{"environment":"production"}');

        try {
            $paths = ProjectPathGuard::forProject($root);
            $service = new ProjectRetentionService($paths, new ProjectStorageProtection($paths));

            $this->expectException(UnsafeRetentionTestRootException::class);

            $service->run(force: true, dryRun: false, categories: ['logs']);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_misconfigured_root_pointing_at_real_storage_is_rejected(): void
    {
        config(['project_retention.enabled' => true]);

        $paths = ProjectPathGuard::forProject(base_path('storage'));
        $service = new ProjectRetentionService($paths, new ProjectStorageProtection($paths));

        $this->expectException(UnsafeRetentionTestRootException::class);

        $service->run(force: true, dryRun: false, categories: ['cache']);
    }

    public function test_guard_runs_before_scanning_or_deleting(): void
    {
        config(['project_retention.enabled' => true]);

        $paths = ProjectPathGuard::forProject(base_path());
        $service = new ProjectRetentionService($paths, new ProjectStorageProtection($paths));

        $probe = base_path('storage/logs/guard-probe-'.uniqid('', true).'.log');
        File::put($probe, 'probe');
        touch($probe, time() - (40 * 86400));

        try {
            try {
                $service->run(force: true, dryRun: false, categories: ['logs']);
                $this->fail('Expected UnsafeRetentionTestRootException.');
            } catch (UnsafeRetentionTestRootException) {
                $this->assertFileExists($probe);
            }
        } finally {
            @unlink($probe);
        }
    }

    public function test_cleanup_service_parity_blocks_real_worktree_force(): void
    {
        $service = new ProjectCleanupService(ProjectPathGuard::forProject(base_path()));

        $this->expectException(UnsafeRetentionTestRootException::class);

        $service->cleanup(force: true, category: 'logs', dryRun: false);
    }

    public function test_cleanup_force_in_marked_sandbox_succeeds(): void
    {
        $this->withCleanupSandbox(function (string $root, ProjectCleanupService $service): void {
            $log = $root.'/storage/logs/sandbox-cleanup.log';
            File::put($log, 'line');

            $result = $service->cleanup(force: true, category: 'logs', dryRun: false);

            $this->assertFileDoesNotExist($log);
            $this->assertNotEmpty($result['deleted']);
        });
    }

    public function test_path_traversal_target_is_rejected_by_path_guard(): void
    {
        $guard = ProjectPathGuard::forProject(base_path());

        $this->assertFalse($guard->isWithinProject('../outside/evidence.zip'));
    }

    public function test_symlink_from_sandbox_to_real_storage_is_not_within_sandbox(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('Symlink support unavailable.');
        }

        $this->withRetentionSandbox(function (string $root): void {
            $link = $root.'/storage/logs/real-storage-link';
            $target = storage_path('logs');

            if (! @symlink($target, $link)) {
                $this->markTestSkipped('Unable to create symlink in this environment.');
            }

            $guard = ProjectPathGuard::forProject($root);
            $this->assertFalse($guard->isWithinProject($link));
        });
    }

    public function test_destructive_test_files_use_sandbox_trait(): void
    {
        $files = [
            base_path('tests/Feature/ProjectRetentionCommandsTest.php'),
            base_path('tests/Feature/ProjectHygieneCommandsTest.php'),
            base_path('tests/Feature/RetentionTestIsolationTest.php'),
        ];

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);
            $this->assertStringContainsString('CreatesRetentionTestSandbox', $contents, basename($file));
        }
    }

    public function test_destructive_service_calls_in_tests_use_sandbox_helpers(): void
    {
        $files = [
            base_path('tests/Feature/ProjectRetentionCommandsTest.php'),
            base_path('tests/Feature/ProjectHygieneCommandsTest.php'),
        ];

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);

            if (str_contains($contents, '->run(force: true') || str_contains($contents, 'cleanup(force: true')) {
                $this->assertTrue(
                    str_contains($contents, 'withRetentionSandbox')
                        || str_contains($contents, 'withCleanupSandbox')
                        || str_contains($contents, 'UnsafeRetentionTestRootException'),
                    basename($file).' must use sandbox helpers or expect UnsafeRetentionTestRootException for destructive service calls.',
                );
            }
        }
    }

    public function test_carrier_api_events_unaffected_by_blocked_force_attempt(): void
    {
        config(['project_retention.enabled' => true]);
        $before = \App\Models\CarrierApiEvent::count();

        $this->artisan('project:retention --force --category=validation-temp')->assertExitCode(1);

        $this->assertSame($before, \App\Models\CarrierApiEvent::count());
    }

    public function test_dry_run_against_real_worktree_remains_allowed(): void
    {
        $this->artisan('project:retention --dry-run --category=logs')->assertExitCode(0);
        $this->artisan('project:cleanup --category=logs')->assertExitCode(0);
    }

    public function test_assert_retention_sandbox_root_validates_marker_and_location(): void
    {
        $root = $this->createRetentionTestSandbox();

        try {
            $this->assertRetentionSandboxRoot($root);
            $this->assertTrue(RetentionTestSandbox::hasValidMarker($root));
            $this->assertFalse(RetentionTestSandbox::isRealWorktreePath($root));
        } finally {
            File::deleteDirectory($root);
        }
    }
}
