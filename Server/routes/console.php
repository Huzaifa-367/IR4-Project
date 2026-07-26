<?php

use App\Jobs\BackupDatabase;
use App\Jobs\CheckDiskSpace;
use App\Jobs\FlagOverdueEquipment;
use App\Jobs\GenerateWeeklyReport;
use App\Jobs\PruneExportFiles;
use App\Jobs\PruneRawSensorData;
use App\Services\AssetHealthService;
use App\Services\Backup\BackupService;
use App\Services\SettingsService;
use App\Services\TrackingService;
use App\Services\WeeklyReportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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

Schedule::command('ir4:permits-tick')
    ->everyMinute()
    ->name('ir4:permits-tick')
    ->withoutOverlapping(55);

Schedule::call(function (TrackingService $tracking): void {
    $tracking->sweepOffsiteTags();
})->hourly()->name('ir4:tracking-absence-sweep');

Schedule::job(new FlagOverdueEquipment)->daily()->name('ir4:flag-overdue-equipment');

Schedule::job(new PruneRawSensorData)
    ->dailyAt('03:15')
    ->name('ir4:prune-raw-sensor-data')
    ->withoutOverlapping(120);

Schedule::job(new PruneExportFiles)
    ->dailyAt('03:30')
    ->name('ir4:prune-export-files')
    ->withoutOverlapping(60);

Schedule::job(new BackupDatabase)
    ->dailyAt('02:30')
    ->name('ir4:backup-database')
    ->withoutOverlapping(120);

Schedule::job(new CheckDiskSpace)
    ->everyFifteenMinutes()
    ->name('ir4:check-disk-space')
    ->withoutOverlapping(10);

Schedule::call(function (BackupService $backups): void {
    $backups->raiseIfBackupMissing();
})->hourly()->name('ir4:backup-gap-check');

Schedule::call(function (SettingsService $settings, WeeklyReportService $reports): void {
    $day = strtolower((string) $settings->get('report.generation_day', 'sunday'));
    $time = (string) $settings->get('report.generation_time', '06:00');
    if (now()->format('l') !== ucfirst($day)) {
        return;
    }
    if (now()->format('H:i') !== $time) {
        return;
    }

    [$start, $end] = $reports->previousReportingWeek();
    GenerateWeeklyReport::dispatch(
        periodStart: $start->toDateString(),
        periodEnd: $end->toDateString(),
        userId: null,
        auto: true,
    );
})->everyMinute()->name('ir4:generate-weekly-report');
