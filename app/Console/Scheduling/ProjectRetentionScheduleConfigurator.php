<?php

namespace App\Console\Scheduling;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;

final class ProjectRetentionScheduleConfigurator
{
    public static function register(?Schedule $schedule = null): void
    {
        if (! config('project_retention.schedule.enabled', false)) {
            return;
        }

        $schedule ??= ScheduleFacade::getFacadeRoot();
        $categories = config('project_retention.schedule.categories', ['cache', 'validation-temp']);
        $force = (bool) config('project_retention.schedule.force', false);
        $frequency = (string) config('project_retention.schedule.frequency', 'daily');

        foreach ($categories as $category) {
            if (! in_array($category, config('project_retention.categories', []), true)) {
                continue;
            }

            $event = $schedule->command('project:retention', self::commandOptions($category, $force));

            if ($frequency === 'weekly') {
                $event->weekly();
            } else {
                $event->daily();
            }

            $event->withoutOverlapping();
        }
    }

    /**
     * @return list<string>
     */
    public static function commandOptions(string $category, bool $force): array
    {
        $options = ['--category='.$category];

        if ($force) {
            $options[] = '--force';
        } else {
            $options[] = '--dry-run';
        }

        return $options;
    }
}
