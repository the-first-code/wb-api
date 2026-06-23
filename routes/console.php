<?php

use App\Services\WbConsoleDebug;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $lookback = config('wb.schedule_lookback_days');

    if (config('wb.debug')) {
        app(WbConsoleDebug::class)->enable();
    }

    $parameters = [
        '--from' => now()->subDays($lookback)->toDateString(),
        '--to' => now()->toDateString(),
    ];

    if (config('wb.debug')) {
        $parameters['-v'] = true;
    }

    Artisan::call('wb:sync', $parameters);

    echo Artisan::output();
})
    ->twiceDaily(config('wb.schedule_hour_1'), config('wb.schedule_hour_2'))
    ->timezone(config('app.timezone'))
    ->name('wb-sync')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wb-sync-schedule.log'));
