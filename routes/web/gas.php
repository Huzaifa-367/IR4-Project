<?php

use App\Http\Controllers\Web\Gas\GasDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-gas')->prefix('gas')->name('gas.')->group(function (): void {
    Route::get('/', [GasDashboardController::class, 'index'])->name('index');
    Route::get('api/live', [GasDashboardController::class, 'live'])->name('api.live');
    Route::get('trends', [GasDashboardController::class, 'trends'])->name('trends.index');
    Route::get('alarms', [GasDashboardController::class, 'alarms'])->name('alarms.index');
    Route::post('alarms/{alarm}/acknowledge', [GasDashboardController::class, 'acknowledge'])
        ->middleware('permission:acknowledge-alerts')
        ->name('alarms.acknowledge');
});

Route::get('settings/gas-thresholds', [GasDashboardController::class, 'thresholds'])
    ->middleware('permission:view-gas')
    ->name('gas.thresholds.index');
Route::put('settings/gas-thresholds', [GasDashboardController::class, 'updateThresholds'])
    ->middleware('permission:manage-gas-thresholds')
    ->name('gas.thresholds.update');
