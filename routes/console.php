<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gps:sync-last-positions')->everySecond()->withoutOverlapping();
Schedule::command('alerts:operational')->everyTenMinutes()->withoutOverlapping();
Schedule::command('requests:expire-pending --minutes=12')->everyMinute()->withoutOverlapping();
Schedule::command('requests:process-timers --finalize-minutes=2')->everyMinute()->withoutOverlapping();
