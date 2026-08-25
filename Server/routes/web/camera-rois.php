<?php

use App\Http\Controllers\Web\CameraRoi\CameraRoiController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings/camera-rois')->name('settings.camera-rois.')->group(function (): void {
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
