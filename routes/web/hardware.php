<?php

use App\Http\Controllers\Web\Settings\AssetController;
use App\Http\Controllers\Web\Settings\CameraController;
use App\Http\Controllers\Web\Settings\DeviceController;
use App\Http\Controllers\Web\Tracking\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-tracking')->prefix('hardware/tags')->name('tracking.tags.')->group(function (): void {
    Route::get('/', [TagController::class, 'index'])->name('index');
    Route::post('/', [TagController::class, 'store'])
        ->middleware('permission:manage-tags')
        ->name('store');
    Route::post('{tag}/assign', [TagController::class, 'assign'])
        ->middleware('permission:manage-tags')
        ->name('assign');
    Route::post('{tag}/unassign', [TagController::class, 'unassign'])
        ->middleware('permission:manage-tags')
        ->name('unassign');
});

Route::middleware('permission:manage-devices')->group(function (): void {
    Route::prefix('hardware/assets')->name('settings.assets.')->group(function (): void {
        Route::get('/', [AssetController::class, 'index'])->name('index');
        Route::post('/', [AssetController::class, 'store'])->name('store');
        Route::get('{asset}', [AssetController::class, 'show'])->name('show');
        Route::put('{asset}', [AssetController::class, 'update'])->name('update');
        Route::delete('{asset}', [AssetController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('hardware/cameras')->name('settings.cameras.')->group(function (): void {
        Route::get('/', [CameraController::class, 'index'])->name('index');
        Route::post('/', [CameraController::class, 'store'])->name('store');
        Route::put('{camera}', [CameraController::class, 'update'])->name('update');
        Route::patch('{camera}/status', [CameraController::class, 'setStatus'])->name('status');
        Route::patch('{camera}/ai', [CameraController::class, 'toggleAi'])->name('toggle-ai');
    });

    Route::prefix('hardware/devices')->name('settings.devices.')->group(function (): void {
        Route::get('/', [DeviceController::class, 'index'])->name('index');
        Route::post('/', [DeviceController::class, 'store'])->name('store');
        Route::put('{device}', [DeviceController::class, 'update'])->name('update');
        Route::patch('{device}/status', [DeviceController::class, 'setStatus'])->name('status');
        Route::post('{device}/token', [DeviceController::class, 'regenerateToken'])->name('token');
    });
});
