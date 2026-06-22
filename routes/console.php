<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $lookback = config('wb.schedule_lookback_days');

    Artisan::call('wb:sync', [
        '--from' => now()->subDays($lookback)->toDateString(),
        '--to' => now()->toDateString(),
    ]);

    echo Artisan::output();
})
    ->twiceDaily(config('wb.schedule_hour_1'), config('wb.schedule_hour_2'))
    ->timezone(config('app.timezone'))
    ->name('wb-sync')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wb-sync-schedule.log'));
