<?php

use App\Http\Controllers\Api\DeviceHeartbeatController;
use App\Http\Controllers\Api\Ingest\EnvironmentalReadingIngestController;
use App\Http\Controllers\Api\Ingest\GasReadingIngestController;
use App\Http\Controllers\Api\Ingest\PpeViolationIngestController;
use App\Http\Controllers\Api\Ingest\TagReadingIngestController;
use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileEquipmentController;
use App\Http\Middleware\EnsureMobileAccountIsUsable;
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

/*
|--------------------------------------------------------------------------
| Mobile operator API (surface A over Sanctum tokens) — DOC-02 / DOC-13 §4.5
|--------------------------------------------------------------------------
*/

Route::prefix('mobile')->name('api.mobile.')->group(function (): void {
    Route::post('/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:mobile-login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [MobileAuthController::class, 'logout'])->name('logout');

        Route::middleware(EnsureMobileAccountIsUsable::class)->group(function (): void {
            Route::get('/me', [MobileAuthController::class, 'me'])->name('me');
            Route::get('/equipment/by-token/{qrToken}', [MobileEquipmentController::class, 'scan'])
                ->whereUuid('qrToken')
                ->name('equipment.by-token');
            Route::post('/equipment/{equipment}/checkout', [MobileEquipmentController::class, 'checkout'])
                ->name('equipment.checkout');
            Route::post('/equipment/{equipment}/return', [MobileEquipmentController::class, 'returnItem'])
                ->name('equipment.return');
        });
    });
});
