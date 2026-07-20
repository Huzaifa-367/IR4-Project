<?php

use App\Http\Controllers\Web\Tracking\CoverageController;
use App\Http\Controllers\Web\Tracking\EntryExitController;
use App\Http\Controllers\Web\Tracking\EvacuationController;
use App\Http\Controllers\Web\Tracking\TrackingApiController;
use App\Http\Controllers\Web\Tracking\TrackingDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-tracking')->prefix('tracking')->name('tracking.')->group(function (): void {
    Route::get('/', TrackingDashboardController::class)->name('index');
    Route::get('coverage', CoverageController::class)->name('coverage');
    Route::get('api/headcount', [TrackingApiController::class, 'headcount'])->name('api.headcount');
    Route::get('api/positions', [TrackingApiController::class, 'positions'])->name('api.positions');

    Route::get('entry-exit', [EntryExitController::class, 'index'])
        ->middleware('permission:view-entry-exit')
        ->name('entry-exit.index');
    Route::get('entry-exit/export', [EntryExitController::class, 'export'])
        ->middleware('permission:view-entry-exit')
        ->name('entry-exit.export');
    Route::post('entry-exit/corrections', [EntryExitController::class, 'correct'])
        ->middleware('permission:manage-workers')
        ->name('entry-exit.corrections');

    Route::get('evacuation', [EvacuationController::class, 'index'])->name('evacuation.index');
    Route::post('evacuation', [EvacuationController::class, 'store'])
        ->middleware('permission:trigger-evacuation')
        ->name('evacuation.store');
    Route::get('evacuation/{evacuation}', [EvacuationController::class, 'show'])->name('evacuation.show');
    Route::post('evacuation/{evacuation}/close', [EvacuationController::class, 'close'])
        ->middleware('permission:manage-evacuation')
        ->name('evacuation.close');
    Route::post('evacuation/{evacuation}/entries/{entry}', [EvacuationController::class, 'account'])
        ->middleware('permission:manage-evacuation')
        ->name('evacuation.account');
    Route::get('evacuation/{evacuation}/download', [EvacuationController::class, 'download'])
        ->name('evacuation.download');
});
