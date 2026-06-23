<?php

namespace App\Console\Commands;

use App\Support\ProjectHygiene\ProjectPathGuard;
use App\Support\ProjectHygiene\ProjectRetentionReporter;
use App\Support\ProjectHygiene\ProjectRetentionService;
use App\Support\ProjectHygiene\ProjectStorageProtection;
use App\Support\ProjectHygiene\UnsafeRetentionTestRootException;
use Illuminate\Console\Command;
use RuntimeException;

class ProjectRetentionCommand extends Command
{
    protected $signature = 'project:retention
                            {--dry-run : Report eligible artifacts without deleting (default)}
                            {--force : Delete eligible artifacts (required for destructive runs)}
                            {--category= : Limit to cache, logs, validation-temp, source-archives, test-artifacts, or all}
                            {--older-than= : Override day-based retention thresholds for applicable categories}
                            {--report= : Output format: table (default) or json}';

    protected $description = 'Conservative runtime storage retention and pruning (dry-run by default)';

    public function handle(): int
    {
        if (! config('project_retention.enabled', false) && $this->option('force')) {
            $this->error('Retention is disabled. Set PROJECT_RETENTION_ENABLED=true before running with --force.');

            return self::FAILURE;
        }

        $paths = ProjectPathGuard::forProject();
        $service = new ProjectRetentionService($paths, new ProjectStorageProtection($paths));

        try {
            $categories = $service->assertKnownCategories($this->option('category'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $olderThan = $this->option('older-than');
        $olderThanDays = $olderThan !== null && $olderThan !== ''
            ? max(0, (int) $olderThan)
            : null;

        $force = (bool) $this->option('force');
        $dryRun = ! $force;

        try {
            $result = $service->run(
                force: $force,
                dryRun: $dryRun,
                categories: $categories,
                olderThanDaysOverride: $olderThanDays,
            );
        } catch (UnsafeRetentionTestRootException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('report') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return $result['failures'] === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->renderTableReport($result);

        return $result['failures'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderTableReport(array $result): void
    {
        $summary = $result['summary'];

        $this->info($result['dry_run'] ? 'DRY RUN — no files will be deleted.' : 'FORCE — deleting eligible artifacts.');
        $this->line('Categories: '.implode(', ', $result['categories']));
        $this->line(sprintf(
            'Totals: %d files/dirs, %s eligible (%d), %d protected, %d skipped',
            $summary['total_count'],
            ProjectRetentionReporter::humanSize($summary['eligible_bytes']),
            $summary['eligible_count'],
            $summary['protected_count'],
            $summary['skipped_count'],
        ));

        $this->newLine();
        $this->info('Configured retention');
        foreach ($result['configured_retention'] as $key => $value) {
            $this->line(sprintf('- %s: %s', $key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value));
        }

        $this->newLine();
        $this->info('Entries');
        foreach ($result['entries'] as $entry) {
            $this->line(sprintf(
                '[%s][%s] %s — %s (%s)',
                $entry['category'],
                $entry['status'],
                $entry['path'],
                $entry['reason'],
                ProjectRetentionReporter::humanSize($entry['bytes']),
            ));
        }

        if ($result['deleted'] !== []) {
            $this->newLine();
            $this->info('Deleted');
            foreach ($result['deleted'] as $path) {
                $this->line('  - '.$path);
            }
        }

        if ($result['failures'] !== []) {
            $this->newLine();
            $this->warn('Failures');
            foreach ($result['failures'] as $failure) {
                $this->line('  - '.$failure['path'].' ('.$failure['reason'].')');
            }
        }

        if ($result['dry_run']) {
            $this->newLine();
            $this->comment('Re-run with --force to delete eligible entries. Retention must be enabled for forced runs.');
        }
    }
}
