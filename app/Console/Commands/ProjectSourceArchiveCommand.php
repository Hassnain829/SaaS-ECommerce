<?php

namespace App\Console\Commands;

use App\Support\ProjectHygiene\ProjectPathGuard;
use App\Support\ProjectHygiene\ProjectSourceArchiveService;
use Illuminate\Console\Command;

class ProjectSourceArchiveCommand extends Command
{
    protected $signature = 'project:source-archive
                            {--dry-run : Show archive plan without creating a ZIP}
                            {--list : List files that would be included}';

    protected $description = 'Create a clean export-safe source ZIP from git-tracked files';

    public function handle(): int
    {
        $service = new ProjectSourceArchiveService(ProjectPathGuard::forProject());
        $dryRun = (bool) $this->option('dry-run');
        $list = (bool) $this->option('list');

        if ($dryRun || $list) {
            $plan = $service->plan(listFiles: $list);

            $this->info($dryRun ? 'Source archive dry-run' : 'Source archive file list');
            $this->line('Archive name: '.$plan['archive_name']);
            $this->line('Output directory: '.$plan['output_directory']);
            $this->line('Included file count: '.$plan['included_file_count']);

            $this->newLine();
            $this->info('Excluded categories');
            foreach ($plan['excluded_categories'] as $category) {
                $this->line('- '.$category);
            }

            $this->newLine();
            $this->info('Required source areas');
            foreach ($plan['required_paths_present'] as $prefix => $present) {
                $this->line(sprintf('- %s %s', $present ? '✓' : '✗', $prefix));
            }

            if ($list && is_array($plan['files'])) {
                foreach ($plan['files'] as $file) {
                    $this->line($file);
                }
            }

            return self::SUCCESS;
        }

        $result = $service->create(dryRun: false);

        $this->info('Source archive created.');
        $this->line('Path: '.$result['archive_path']);
        $this->line('Size: '.$result['archive_size_human']);
        $this->line('Included files: '.$result['included_file_count']);

        return self::SUCCESS;
    }
}
