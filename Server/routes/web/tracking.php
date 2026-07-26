<?php

use App\Http\Controllers\Web\Tracking\CoverageController;
use App\Http\Controllers\Web\Tracking\EntryExitController;
use App\Http\Controllers\Web\Tracking\EvacuationController;
use App\Http\Controllers\Web\Tracking\TrackingApiController;
use App\Http\Controllers\Web\Tracking\TrackingDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('tracking')->name('tracking.')->group(function (): void {
    Route::get('/', TrackingDashboardController::class)
        ->middleware('permission:view-tracking')
        ->name('index');
    Route::get('coverage', CoverageController::class)
        ->middleware('permission:view-tracking')
        ->name('coverage');
    Route::get('api/headcount', [TrackingApiController::class, 'headcount'])
        ->middleware('permission:view-tracking')
        ->name('api.headcount');
    Route::get('api/positions', [TrackingApiController::class, 'positions'])
        ->middleware('permission:view-tracking')
        ->name('api.positions');

    Route::get('entry-exit', [EntryExitController::class, 'index'])
        ->middleware('permission:view-entry-exit')
        ->name('entry-exit.index');
    Route::get('entry-exit/export', [EntryExitController::class, 'export'])
        ->middleware('permission:view-entry-exit')
        ->name('entry-exit.export');
    Route::post('entry-exit/corrections', [EntryExitController::class, 'correct'])
        ->middleware('permission:update-workers')
        ->name('entry-exit.corrections');

    Route::get('evacuation', [EvacuationController::class, 'index'])
        ->middleware('permission:create-evacuation|update-evacuation')
        ->name('evacuation.index');
    Route::post('evacuation', [EvacuationController::class, 'store'])
        ->middleware('permission:create-evacuation')
        ->name('evacuation.store');
    Route::get('evacuation/{evacuation}', [EvacuationController::class, 'show'])
        ->middleware('permission:create-evacuation|update-evacuation')
        ->name('evacuation.show');
    Route::post('evacuation/{evacuation}/close', [EvacuationController::class, 'close'])
        ->middleware('permission:update-evacuation')
        ->name('evacuation.close');
    Route::post('evacuation/{evacuation}/entries/{entry}', [EvacuationController::class, 'account'])
        ->middleware('permission:update-evacuation')
        ->name('evacuation.account');
    Route::get('evacuation/{evacuation}/download', [EvacuationController::class, 'download'])
        ->middleware('permission:create-evacuation|update-evacuation')
        ->name('evacuation.download');
});
