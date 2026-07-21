<?php

use App\Http\Controllers\Web\Gas\GasDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('gas')->name('gas.')->group(function (): void {
    Route::get('/', [GasDashboardController::class, 'index'])
        ->middleware('permission:view-gas')
        ->name('index');
    Route::get('api/live', [GasDashboardController::class, 'live'])
        ->middleware('permission:view-gas')
        ->name('api.live');
    Route::get('trends', [GasDashboardController::class, 'trends'])
        ->middleware('permission:view-gas')
        ->name('trends.index');
    Route::get('alarms', [GasDashboardController::class, 'alarms'])
        ->middleware('permission:view-gas')
        ->name('alarms.index');
    Route::post('alarms/{alarm}/acknowledge', [GasDashboardController::class, 'acknowledge'])
        ->middleware('permission:acknowledge-alerts')
        ->name('alarms.acknowledge');
});

Route::get('settings/gas-thresholds', [GasDashboardController::class, 'thresholds'])
    ->middleware('permission:view-gas-thresholds')
    ->name('gas.thresholds.index');
Route::put('settings/gas-thresholds', [GasDashboardController::class, 'updateThresholds'])
    ->middleware('permission:update-gas-thresholds')
    ->name('gas.thresholds.update');
