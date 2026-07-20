<?php

use App\Http\Controllers\Web\Reports\VehicleViolationController;
use App\Http\Controllers\Web\Reports\WeeklyReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-reports')->prefix('reports')->name('reports.')->group(function (): void {
    Route::get('/', [WeeklyReportController::class, 'index'])->name('index');
    Route::get('settings', [WeeklyReportController::class, 'settings'])
        ->middleware('permission:manage-settings')
        ->name('settings');
    Route::put('settings', [WeeklyReportController::class, 'updateSettings'])
        ->middleware('permission:manage-settings')
        ->name('settings.update');
    Route::get('vehicle-violations', [VehicleViolationController::class, 'index'])
        ->middleware('permission:log-vehicle-violations')
        ->name('vehicle-violations.index');
    Route::post('vehicle-violations', [VehicleViolationController::class, 'store'])
        ->middleware('permission:log-vehicle-violations')
        ->name('vehicle-violations.store');
    Route::delete('vehicle-violations/{vehicleViolation}', [VehicleViolationController::class, 'destroy'])
        ->middleware('permission:log-vehicle-violations')
        ->name('vehicle-violations.destroy');
    Route::get('{report}', [WeeklyReportController::class, 'show'])->name('show');
});

Route::middleware('permission:generate-reports')->post('weekly-reports/generate', [WeeklyReportController::class, 'generate'])
    ->name('weekly-reports.generate');
Route::middleware('permission:publish-reports')->post('weekly-reports/{report}/publish', [WeeklyReportController::class, 'publish'])
    ->name('weekly-reports.publish');
Route::middleware('permission:view-reports')->get('weekly-reports/{report}/download', [WeeklyReportController::class, 'download'])
    ->name('weekly-reports.download');
