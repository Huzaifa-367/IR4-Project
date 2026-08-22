<?php

use App\Http\Controllers\Web\Reports\WeeklyReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->name('reports.')->group(function (): void {
    Route::get('/', [WeeklyReportController::class, 'index'])
        ->middleware('permission:view-reports')
        ->name('index');
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
