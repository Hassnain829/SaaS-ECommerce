<?php

use App\Console\Scheduling\ProjectRetentionScheduleConfigurator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

ProjectRetentionScheduleConfigurator::register();

Schedule::command('checkouts:expire-abandoned')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('fedex:refresh-tracking --limit=100')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->when(fn (): bool => filter_var(config('carriers.fedex.ops_tracking_enabled', false), FILTER_VALIDATE_BOOL));
