<?php

namespace App\Console\Commands;

use App\Support\ProjectHygiene\ProjectCleanupService;
use App\Support\ProjectHygiene\ProjectPathGuard;
use App\Support\ProjectHygiene\UnsafeRetentionTestRootException;
use Illuminate\Console\Command;

class ProjectCleanupCommand extends Command
{
    protected $signature = 'project:cleanup
                            {--force : Actually delete approved runtime artifacts}
                            {--category= : Limit cleanup to cache, logs, or carrier-validation}';

    protected $description = 'Safely remove runtime/cache/temp artifacts (dry-run by default)';

    public function handle(): int
    {
        $category = $this->option('category');
        if ($category !== null && $category !== ''
            && ! in_array($category, ['cache', 'logs', 'carrier-validation', 'all'], true)) {
            $this->error('Invalid category. Use cache, logs, carrier-validation, or all.');

            return self::FAILURE;
        }

        if ($category === 'all') {
            $category = null;
        }

        $service = new ProjectCleanupService(ProjectPathGuard::forProject());
        $targets = $service->targets($category);
        $force = (bool) $this->option('force');
        $dryRun = ! $force;

        $this->info($dryRun ? 'DRY RUN — no files will be deleted.' : 'FORCE — deleting approved runtime artifacts.');
        $this->line('Target count: '.count($targets));

        foreach ($targets as $target) {
            $this->line(sprintf('[%s] %s — %s', $target['category'], $target['path'], $target['reason']));
        }

        try {
            $result = $service->cleanup($force, $category, $dryRun);
        } catch (UnsafeRetentionTestRootException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Re-run with --force to delete the targets listed above.');
        } else {
            $this->newLine();
            $this->info('Deleted: '.count($result['deleted']));
            foreach ($result['deleted'] as $path) {
                $this->line('  - '.$path);
            }

            if ($result['skipped'] !== []) {
                $this->warn('Skipped: '.count($result['skipped']));
                foreach ($result['skipped'] as $note) {
                    $this->line('  - '.$note);
                }
            }
        }

        return self::SUCCESS;
    }
}
