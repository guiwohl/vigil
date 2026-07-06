<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The scheduler ticks every few seconds, fanning out one queued CheckMonitor job
// per due monitor. Sub-minute cadence requires `schedule:work` (or Sail's scheduler
// container), not a once-a-minute cron. Interval granularity per monitor is honoured
// inside MonitoringService::dueMonitors().
Schedule::command('monitors:tick')
    ->everyFiveSeconds()
    ->withoutOverlapping();
