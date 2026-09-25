<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep 30 days of raw Meta webhook events (override with --days=N).
Schedule::command('meta:prune-webhook-events', ['--days' => 30])
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();

// Shared hosting (no Supervisor): cron runs schedule:run every minute, and this keeps a worker alive
// for ~55s of each minute so DM replies go out within seconds. Enable with QUEUE_WORK_VIA_SCHEDULER=true.
if (config('queue.work_via_scheduler')) {
    Schedule::command('queue:work', ['--max-time' => 55, '--tries' => 3, '--timeout' => 50, '--sleep' => 1])
        ->everyMinute()
        ->withoutOverlapping(2)
        ->runInBackground();
}

// Data retention: delete contacts (conversations + messages) inactive for DATA_RETENTION_MONTHS (default 12).
Schedule::command('contacts:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();
