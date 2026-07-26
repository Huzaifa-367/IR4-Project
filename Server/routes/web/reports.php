<?php

use App\Http\Controllers\Web\Reports\VehicleViolationController;
use App\Http\Controllers\Web\Reports\WeeklyReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->name('reports.')->group(function (): void {
    Route::get('/', [WeeklyReportController::class, 'index'])
        ->middleware('permission:view-reports')
        ->name('index');
    Route::get('vehicle-violations', [VehicleViolationController::class, 'index'])
        ->middleware('permission:view-vehicle-violations')
        ->name('vehicle-violations.index');
    Route::post('vehicle-violations', [VehicleViolationController::class, 'store'])
        ->middleware('permission:create-vehicle-violations')
        ->name('vehicle-violations.store');
    Route::delete('vehicle-violations/{vehicleViolation}', [VehicleViolationController::class, 'destroy'])
        ->middleware('permission:delete-vehicle-violations')
        ->name('vehicle-violations.destroy');
    Route::get('{report}', [WeeklyReportController::class, 'show'])
        ->middleware('permission:view-reports')
        ->name('show');
});

Route::post('weekly-reports/generate', [WeeklyReportController::class, 'generate'])
    ->middleware('permission:create-reports')
    ->name('weekly-reports.generate');
Route::post('weekly-reports/{report}/publish', [WeeklyReportController::class, 'publish'])
    ->middleware('permission:update-reports')
    ->name('weekly-reports.publish');
Route::get('weekly-reports/{report}/download', [WeeklyReportController::class, 'download'])
    ->middleware('permission:view-reports')
    ->name('weekly-reports.download');
