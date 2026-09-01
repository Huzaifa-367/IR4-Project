<?php

use App\Jobs\FlagOverdueEquipment;
use App\Jobs\GenerateWeeklyReport;
use App\Jobs\PruneExportFiles;
use App\Jobs\PruneRawSensorData;
use App\Services\Hardware\AssetHealthService;
use App\Services\Permit\PermitDetectionService;
use App\Services\Permit\PermitService;
use App\Services\Platform\DiskSpaceMonitor;
use App\Services\Report\WeeklyReportService;
use App\Services\Settings\SettingsService;
use App\Services\Tracking\TrackingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (AssetHealthService $health): void {
    $health->markStale();
})->everyMinute()->name('ir4:asset-health-mark-stale');

Schedule::call(function (TrackingService $tracking): void {
    $tracking->checkStationaryTags();
})->everyMinute()->name('ir4:tracking-stationary-tags');

Schedule::call(function (PermitService $permits, PermitDetectionService $detection): void {
    $permits->expireOverdue();
    $permits->suspendStaleGasTests();
    $detection->run();
})->everyMinute()
    ->name('ir4:permits-tick')
    ->withoutOverlapping(55);

Schedule::call(function (TrackingService $tracking): void {
    $tracking->sweepOffsiteTags();
})->hourly()->name('ir4:tracking-absence-sweep');

Schedule::job(new FlagOverdueEquipment)->daily()->name('ir4:flag-overdue-equipment');

// Spatie order: clean → run → monitor → prune. Site SCCs power off overnight
// after work hours, so keep this window in daytime (APP_TIMEZONE / general.timezone).
// Requires a persistent scheduler worker: `lerd schedule:start` (see scripts/01-setup.sh).
Schedule::command('backup:clean')
    ->dailyAt('14:00')
    ->name('ir4:backup-clean')
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('backup:run')
    ->dailyAt('14:30')
    ->name('ir4:backup-run')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('backup:monitor')
    ->dailyAt('15:00')
    ->name('ir4:backup-monitor')
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::job(new PruneRawSensorData)
    ->dailyAt('15:15')
    ->name('ir4:prune-raw-sensor-data')
    ->withoutOverlapping(120);

Schedule::job(new PruneExportFiles)
    ->dailyAt('15:30')
    ->name('ir4:prune-export-files')
    ->withoutOverlapping(60);

// Weather API — one snapshot per hour (no DB read at console.php load).
Schedule::command('ir4:fetch-weather-api')
    ->hourly()
    ->name('ir4:fetch-weather-api')
    ->withoutOverlapping(55);

Schedule::command('ir4:prune-expired-cache')
    ->hourly()
    ->name('ir4:prune-expired-cache')
    ->withoutOverlapping(10);

Schedule::call(function (DiskSpaceMonitor $monitor): void {
    $monitor->check();
})
    ->everyFifteenMinutes()
    ->name('ir4:check-disk-space')
    ->withoutOverlapping(10);

Schedule::call(function (SettingsService $settings, WeeklyReportService $reports): void {
    $day = strtolower((string) $settings->get('report.generation_day', 'sunday'));
    $time = (string) $settings->get('report.generation_time', '06:00');
    if (strtolower(now()->format('l')) !== $day) {
        return;
    }

    $scheduled = now()->setTimeFromTimeString($time);
    if (now()->lt($scheduled) || now()->gte($scheduled->copy()->addMinutes(15))) {
        return;
    }

    [$start, $end] = $reports->previousReportingWeek();
    $lockKey = 'ir4:weekly-report:'.$start->toDateString();
    if (! Cache::add($lockKey, true, now()->addHours(26))) {
        return;
    }

    GenerateWeeklyReport::dispatch(
        periodStart: $start->toDateString(),
        periodEnd: $end->toDateString(),
        userId: null,
        auto: true,
    );
})->everyMinute()
    ->name('ir4:generate-weekly-report')
    ->withoutOverlapping(55);
