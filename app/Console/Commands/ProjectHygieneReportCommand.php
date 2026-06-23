<?php

namespace App\Console\Commands;

use App\Support\ProjectHygiene\ProjectHygieneReporter;
use App\Support\ProjectHygiene\ProjectPathGuard;
use Illuminate\Console\Command;

class ProjectHygieneReportCommand extends Command
{
    protected $signature = 'project:hygiene-report';

    protected $description = 'Read-only repository size and hygiene report (no secret values)';

    public function handle(): int
    {
        $report = (new ProjectHygieneReporter(ProjectPathGuard::forProject()))->report();

        $this->info('Project hygiene report');
        $this->line('Root: '.$report['project_root']);
        $this->line('Total size: '.$report['total_project_size_human']);

        $this->newLine();
        $this->info('Directory sizes');
        foreach ($report['directories'] as $key => $stats) {
            if (! ($stats['exists'] ?? false)) {
                $this->line(sprintf('- %s: (not present)', $key));

                continue;
            }

            $this->line(sprintf(
                '- %s: %s (%d files)',
                $key,
                $stats['human'],
                $stats['file_count'],
            ));
        }

        $this->newLine();
        $this->info('Largest top-level directories');
        foreach ($report['largest_directories'] as $entry) {
            $this->line(sprintf('- %s: %s', $entry['path'], $entry['human']));
        }

        $this->newLine();
        $this->info('Potential archive leaks (local-only artifacts present)');
        if ($report['potential_archive_leaks'] === []) {
            $this->line('- none detected');
        } else {
            foreach ($report['potential_archive_leaks'] as $leak) {
                $this->line(sprintf('- [%s] %s — %s', $leak['category'], $leak['path'], $leak['note']));
            }
        }

        $trackedRisks = $report['git_tracked_secret_risk_files'];
        $this->newLine();
        $this->info('Git tracked secret-risk paths');
        if ($trackedRisks === []) {
            $this->line('- none detected');
        } else {
            foreach ($trackedRisks as $path) {
                $this->warn('- '.$path);
            }
        }

        return self::SUCCESS;
    }
}
