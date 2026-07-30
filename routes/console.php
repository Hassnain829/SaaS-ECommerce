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
