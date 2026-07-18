<?php

use App\Http\Controllers\Api\DeviceHeartbeatController;
use App\Http\Controllers\Api\Ingest\EnvironmentalReadingIngestController;
use App\Http\Controllers\Api\Ingest\GasReadingIngestController;
use App\Http\Controllers\Api\Ingest\PpeViolationIngestController;
use App\Http\Controllers\Api\Ingest\TagReadingIngestController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Device API (surface B) — DOC-01 / DOC-02 / DOC-08
|--------------------------------------------------------------------------
|
| Laravel prefixes this file with /api automatically.
|
*/

Route::get('/health', function () {
    return ApiResponse::ok(['status' => 'ok']);
})->name('api.health');

Route::middleware('auth.device')->group(function (): void {
    Route::post('/devices/{device}/heartbeat', DeviceHeartbeatController::class)
        ->name('api.devices.heartbeat');

    Route::middleware('throttle:ingest')->prefix('ingest')->name('api.ingest.')->group(function (): void {
        Route::post('/tag-readings', TagReadingIngestController::class)->name('tag-readings');
        Route::post('/ppe-violations', PpeViolationIngestController::class)->name('ppe-violations');
        Route::post('/gas-readings', GasReadingIngestController::class)->name('gas-readings');
        Route::post('/environmental-readings', EnvironmentalReadingIngestController::class)
            ->name('environmental-readings');
    });
});
