<?php

use App\Http\Controllers\Web\Settings\AssetController;
use App\Http\Controllers\Web\Settings\CameraController;
use App\Http\Controllers\Web\Settings\DeviceController;
use App\Http\Controllers\Web\Tracking\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('hardware/tags')->name('tracking.tags.')->group(function (): void {
    Route::get('/', [TagController::class, 'index'])
        ->middleware('permission:view-tracking')
        ->name('index');
    Route::post('/', [TagController::class, 'store'])
        ->middleware('permission:create-tags')
        ->name('store');
    Route::post('{tag}/assign', [TagController::class, 'assign'])
        ->middleware('permission:update-tags')
        ->name('assign');
    Route::post('{tag}/unassign', [TagController::class, 'unassign'])
        ->middleware('permission:update-tags')
        ->name('unassign');
});

Route::prefix('hardware/assets')->name('settings.assets.')->group(function (): void {
    Route::get('/', [AssetController::class, 'index'])
        ->middleware('permission:view-devices')
        ->name('index');
    Route::post('/', [AssetController::class, 'store'])
        ->middleware('permission:create-devices')
        ->name('store');
    Route::get('{asset}', [AssetController::class, 'show'])
        ->middleware('permission:view-devices')
        ->name('show');
    Route::put('{asset}', [AssetController::class, 'update'])
        ->middleware('permission:update-devices')
        ->name('update');
    Route::delete('{asset}', [AssetController::class, 'destroy'])
        ->middleware('permission:delete-devices')
        ->name('destroy');
});

Route::prefix('hardware/cameras')->name('settings.cameras.')->group(function (): void {
    Route::get('/', [CameraController::class, 'index'])
        ->middleware('permission:view-devices')
        ->name('index');
    Route::post('/', [CameraController::class, 'store'])
        ->middleware('permission:create-devices')
        ->name('store');
    Route::put('{camera}', [CameraController::class, 'update'])
        ->middleware('permission:update-devices')
        ->name('update');
    Route::patch('{camera}/status', [CameraController::class, 'setStatus'])
        ->middleware('permission:update-devices')
        ->name('status');
    Route::patch('{camera}/ai', [CameraController::class, 'toggleAi'])
        ->middleware('permission:update-devices')
        ->name('toggle-ai');
});

Route::prefix('hardware/devices')->name('settings.devices.')->group(function (): void {
    Route::get('/', [DeviceController::class, 'index'])
        ->middleware('permission:view-devices')
        ->name('index');
    Route::post('/', [DeviceController::class, 'store'])
        ->middleware('permission:create-devices')
        ->name('store');
    Route::put('{device}', [DeviceController::class, 'update'])
        ->middleware('permission:update-devices')
        ->name('update');
    Route::patch('{device}/status', [DeviceController::class, 'setStatus'])
        ->middleware('permission:update-devices')
        ->name('status');
    Route::post('{device}/token', [DeviceController::class, 'regenerateToken'])
        ->middleware('permission:update-devices')
        ->name('token');
});
