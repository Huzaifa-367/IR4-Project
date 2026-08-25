<?php

use App\Http\Controllers\Web\CameraRoi\CameraRoiController;
use App\Http\Controllers\Web\Settings\AssetController;
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

Route::redirect('/hardware/cameras', '/hardware/devices?device_type=camera')
    ->middleware('permission:view-devices');

Route::prefix('hardware/camera-rois')->name('hardware.camera-rois.')->group(function (): void {
    Route::get('/', [CameraRoiController::class, 'index'])
        ->middleware('permission:view-camera-rois')
        ->name('index');
    Route::get('/{camera:uuid}', [CameraRoiController::class, 'edit'])
        ->middleware('permission:view-camera-rois')
        ->name('edit');
    Route::put('/{camera:uuid}', [CameraRoiController::class, 'update'])
        ->middleware('permission:manage-camera-rois')
        ->name('update');
    Route::post('/{camera:uuid}/publish', [CameraRoiController::class, 'publish'])
        ->middleware('permission:manage-camera-rois')
        ->name('publish');
    Route::post('/{camera:uuid}/mark-stale', [CameraRoiController::class, 'markStale'])
        ->middleware('permission:manage-camera-rois')
        ->name('mark-stale');
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
    Route::patch('{device}/ai', [DeviceController::class, 'toggleAi'])
        ->middleware('permission:update-devices')
        ->name('toggle-ai');
});
