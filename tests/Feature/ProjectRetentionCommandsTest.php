<?php

namespace Tests\Feature;

use App\Console\Scheduling\ProjectRetentionScheduleConfigurator;
use App\Models\CarrierApiEvent;
use App\Support\ProjectHygiene\ProjectPathGuard;
use App\Support\ProjectHygiene\ProjectRetentionService;
use App\Support\ProjectHygiene\UnsafeRetentionTestRootException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesRetentionTestSandbox;
use Tests\TestCase;

class ProjectRetentionCommandsTest extends TestCase
{
    use CreatesRetentionTestSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Support\ProjectHygiene\ProjectTrackedFiles::clearCache();
    }

    public function test_retention_defaults_are_conservative(): void
    {
        $this->assertFalse(config('project_retention.enabled'));
        $this->assertTrue(config('project_retention.dry_run'));
        $this->assertTrue(config('project_retention.require_force'));
        $this->assertTrue(config('project_retention.preserve_protected'));
        $this->assertTrue(config('project_retention.test_sandbox_required'));
        $this->assertFalse(config('project_retention.schedule.enabled'));
        $this->assertFalse(config('project_retention.session_cleanup_enabled'));
    }

    public function test_retention_env_values_map_correctly(): void
    {
        $this->withRetentionSandbox(function (string $root, ProjectRetentionService $service): void {
            config([
                'project_retention.log_days' => 45,
                'project_retention.cache_hours' => 12,
                'project_retention.validation_temp_days' => 21,
            ]);

            $scan = $service->scan(['logs']);

            $this->assertSame(45, $scan['configured_retention']['log_days']);
            $this->assertSame(12, $scan['configured_retention']['cache_hours']);
            $this->assertSame(21, $scan['configured_retention']['validation_temp_days']);
        });
    }

    public function test_retention_rejects_unknown_category(): void
    {
        $this->artisan('project:retention --category=unknown')
            ->assertExitCode(1);
    }

    public function test_retention_dry_run_deletes_nothing(): void
    {
        $root = base_path();
        $log = $root.'/storage/logs/retention-dry-run-'.uniqid('', true).'.log';
        File::put($log, 'line');
        $this->makeOld($log, 40);

        $this->artisan('project:retention --category=logs --dry-run')->assertExitCode(0);
        $this->assertFileExists($log);

        @unlink($log);
    }

    public function test_retention_force_requires_enabled_flag(): void
    {
        config(['project_retention.enabled' => false]);

        $this->artisan('project:retention --category=logs --force')
            ->assertExitCode(1);
    }

    public function test_retention_force_deletes_old_log_and_preserves_active_log(): void
    {
        $this->withRetentionSandbox(function (string $root, ProjectRetentionService $service): void {
            config(['project_retention.enabled' => true, 'project_retention.log_days' => 1]);

            $oldLog = $root.'/storage/logs/retention-force-old.log';
            $activeLog = $root.'/storage/logs/laravel.log';
            File::put($oldLog, 'old');
            File::put($activeLog, 'active');
            $this->makeOld($oldLog, 40);

            $result = $service->run(force: true, dryRun: false, categories: ['logs']);

            $this->assertFileDoesNotExist($oldLog);
            $this->assertFileExists($activeLog);
            $this->assertNotEmpty($result['deleted']);
        });
    }

    public function test_retention_preserves_gitignore_and_tracked_files(): void
    {
        $this->withRetentionSandbox(function (string $root, ProjectRetentionService $service): void {
            config(['project_retention.enabled' => true, 'project_retention.log_days' => 0]);

            $gitignore = $root.'/storage/logs/.gitignore';
            File::put($gitignore, "*\n!.gitignore\n");

            $oldLog = $root.'/storage/logs/old.log';
            File::put($oldLog, 'old');
            $this->makeOld($oldLog, 30);

            $result = $service->run(force: true, dryRun: false, categories: ['logs']);

            $this->assertFileExists($gitignore);
            $this->assertNotContains(str_replace('\\', '/', $gitignore), $result['deleted']);
        });
    }

    public function test_source_archive_retention_keeps_latest_n_and_prunes_old_extras(): void
    {
        $this->withRetentionSandbox(function (string $root, ProjectRetentionService $service): void {
            config([
                'project_retention.enabled' => true,
                'project_retention.source_archive_count' => 2,
                'project_retention.source_archive_days' => 1,
            ]);

            $dir = $root.'/storage/app/source-archives';

            for ($i = 0; $i < 4; $i++) {
                $path = $dir.'/E_COMMERCE_OFFICE-source-2026010'.(1 + $i).'-12000'.(1 + $i).'.zip';
                File::put($path, 'zip-'.$i);
                $this->makeOld($path, 10 - $i);
            }

            $scan = $service->scan(['source-archives']);
            $eligible = collect($scan['entries'])->where('status', 'eligible')->pluck('path')->all();

            $this->assertCount(2, $eligible);

            $result = $service->run(force: true, dryRun: false, categories: ['source-archives']);
            $this->assertCount(2, $result['deleted']);
        });
    }

    public function test_source_archive_retention_preserves_protected_and_unrelated_zip(): void
    {
        $this->withRetentionSandbox(function (string $root, ProjectRetentionService $service): void {
            config([
                'project_retention.enabled' => true,
                'project_retention.source_archive_count' => 0,
                'project_retention.source_archive_days' => 0,
            ]);

            $dir = $root.'/storage/app/source-archives';
            $protected = $dir.'/E_COMMERCE_OFFICE-source-20260101-120000.zip';
            $unrelated = $dir.'/manual-delivery.zip';
            File::put($protected, 'protected');
            File::put($dir.'/.protected', '1');
            File::put($unrelated, 'manual');
            $this->makeOld($protected, 30);
            $this->makeOld($unrelated, 30);

            $scan = $service->scan(['source-archives']);
            $protectedEntry = collect($scan['entries'])->first(
                fn (array $entry): bool => str_ends_with($entry['path'], 'E_COMMERCE_OFFICE-source-20260101-120000.zip'),
            );

            $this->assertNotNull($protectedEntry);
            $this->assertSame('protected', $protectedEntry['status']);
            $this->assertFalse(collect($scan['entries'])->contains(
                fn (array $entry): bool => str_ends_with($entry['path'], 'manual-delivery.zip'),
            ));

            $result = $service->run(force: true, dryRun: false, categories: ['source-archives']);
            $this->assertFileExists($protected);
            $this->assertFileExists($unrelated);
            $this->assertSame([], $result['deleted']);
        });
    }

    public function test_validation_temp_staging_expires_but_canonical_and_uploads_survive(): void
    {
        $this->withRetentionSandbox(function (string $root, ProjectRetentionService $service): void {
            config([
                'project_retention.enabled' => true,
                'project_retention.validation_temp_days' => 1,
            ]);

            $storeId = 88001;
            $timestamp = '20260101_120000';
            $staging = $root."/storage/app/fedex-validation/{$storeId}/{$timestamp}/FedEx_Integrator_Validation_BaasPlatformFedExSandbox";
            $finalZip = $root."/storage/app/fedex-validation/{$storeId}/{$timestamp}/fedex-validation-final-{$storeId}-{$timestamp}.zip";
            $diagnosticZip = $root."/storage/app/fedex-validation/{$storeId}/{$timestamp}/fedex-validation-diagnostic-{$storeId}-{$timestamp}.zip";
            $label = $root."/storage/app/fedex-validation/{$storeId}/labels/retention-label.pdf";
            $upload = $root."/storage/app/fedex-validation/{$storeId}/uploads/retention-scan.pdf";

            File::ensureDirectoryExists($staging);
            File::put($staging.'/README.md', 'staging');
            File::put($finalZip, 'final');
            File::put($diagnosticZip, 'diagnostic');
            File::ensureDirectoryExists(dirname($label));
            File::ensureDirectoryExists(dirname($upload));
            File::put($label, '%PDF');
            File::put($upload, '%PDF');

            foreach ([$staging, $finalZip, $diagnosticZip] as $path) {
                $this->makeOld($path, 5);
            }
            clearstatcache();

            $beforeEvents = CarrierApiEvent::count();

            $service->run(force: true, dryRun: false, categories: ['validation-temp']);

            $this->assertFalse(is_dir($staging));
            $this->assertFileDoesNotExist($diagnosticZip);
            $this->assertFileExists($finalZip);
            $this->assertFileExists($label);
            $this->assertFileExists($upload);
            $this->assertSame($beforeEvents, CarrierApiEvent::count());
        });
    }

    public function test_protected_marker_survives_all_prune_modes(): void
    {
        $this->withRetentionSandbox(function (string $root, ProjectRetentionService $service): void {
            config([
                'project_retention.enabled' => true,
                'project_retention.validation_temp_days' => 0,
            ]);

            $dir = $root.'/storage/app/fedex-validation/88002/20260102_120000';
            File::ensureDirectoryExists($dir);
            File::put($dir.'/.protected', '1');
            $diagnostic = $dir.'/fedex-validation-diagnostic-88002-20260102_120000.zip';
            File::put($diagnostic, 'diag');
            $this->makeOld($diagnostic, 30);

            $result = $service->run(force: true, dryRun: false, categories: ['validation-temp']);

            $this->assertFileExists($diagnostic);
            $this->assertSame([], $result['deleted']);
        });
    }

    public function test_retention_json_report_is_valid_and_contains_no_secrets(): void
    {
        $this->artisan('project:retention --category=logs --report=json')
            ->assertExitCode(0)
            ->expectsOutputToContain('"dry_run": true');

        $this->artisan('project:retention --category=cache --report=json')
            ->assertExitCode(0);
    }

    public function test_retention_schedule_disabled_by_default(): void
    {
        $this->assertFalse(config('project_retention.schedule.enabled'));
    }

    public function test_retention_schedule_registers_when_enabled(): void
    {
        config([
            'project_retention.schedule.enabled' => true,
            'project_retention.schedule.categories' => ['cache', 'logs'],
            'project_retention.schedule.force' => false,
        ]);

        $schedule = app(Schedule::class);
        ProjectRetentionScheduleConfigurator::register($schedule);

        $events = collect($schedule->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'project:retention'));

        $this->assertCount(2, $events);

        foreach ($events as $event) {
            $this->assertStringContainsString('project:retention', (string) $event->command);
            $this->assertStringContainsString('--dry-run', (string) $event->command);
            $this->assertNotSame('', $event->mutexName());
        }
    }

    public function test_path_guard_rejects_outside_root_for_retention_targets(): void
    {
        $guard = ProjectPathGuard::forProject(base_path());

        $this->expectException(\RuntimeException::class);
        $guard->assertSafeDeletionTarget('/outside/project/file.log');
    }

    public function test_project_retention_command_is_registered(): void
    {
        $this->artisan('project:retention --dry-run')->assertExitCode(0);
    }

    public function test_hygiene_report_includes_retention_preview(): void
    {
        $this->artisan('project:hygiene-report')
            ->expectsOutputToContain('Retention preview')
            ->assertExitCode(0);
    }

    public function test_destructive_retention_service_rejects_real_worktree_root(): void
    {
        config(['project_retention.enabled' => true]);

        $paths = ProjectPathGuard::forProject(base_path());
        $service = new ProjectRetentionService($paths, new \App\Support\ProjectHygiene\ProjectStorageProtection($paths));

        $this->expectException(UnsafeRetentionTestRootException::class);
        $service->run(force: true, dryRun: false, categories: ['logs']);
    }

    private function makeOld(string $path, int $days): void
    {
        $timestamp = time() - ($days * 86400);
        touch($path, $timestamp);
        if (is_dir($path)) {
            return;
        }

        $directory = dirname($path);
        if (is_dir($directory)) {
            touch($directory, $timestamp);
        }

        clearstatcache();
    }
}
