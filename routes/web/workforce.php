<?php

use App\Http\Controllers\Web\Permit\WorkerDocumentController;
use App\Http\Controllers\Web\Tracking\PortableDeviceController;
use App\Http\Controllers\Web\Tracking\TagController;
use App\Http\Controllers\Web\Tracking\WorkerController;
use Illuminate\Support\Facades\Route;

Route::prefix('workforce/workers')->name('tracking.workers.')->group(function (): void {
    Route::get('/', [WorkerController::class, 'index'])
        ->middleware('permission:view-tracking')
        ->name('index');
    Route::get('create', [WorkerController::class, 'create'])
        ->middleware('permission:create-workers')
        ->name('create');
    Route::post('/', [WorkerController::class, 'store'])
        ->middleware('permission:create-workers')
        ->name('store');
    Route::get('import', [WorkerController::class, 'importForm'])
        ->middleware('permission:create-workers')
        ->name('import');
    Route::post('import', [WorkerController::class, 'import'])
        ->middleware('permission:create-workers')
        ->name('import.store');
    Route::get('import/template', [WorkerController::class, 'template'])
        ->middleware('permission:create-workers')
        ->name('import.template');
    Route::get('{worker}', [WorkerController::class, 'show'])
        ->middleware('permission:view-tracking')
        ->name('show');
    Route::get('{worker}/edit', [WorkerController::class, 'edit'])
        ->middleware('permission:update-workers')
        ->name('edit');
    Route::put('{worker}', [WorkerController::class, 'update'])
        ->middleware('permission:update-workers')
        ->name('update');
    Route::post('{worker}/deactivate', [WorkerController::class, 'deactivate'])
        ->middleware('permission:update-workers')
        ->name('deactivate');
    Route::post('{worker}/reactivate', [WorkerController::class, 'reactivate'])
        ->middleware('permission:update-workers')
        ->name('reactivate');
    Route::post('{worker}/offboard', [WorkerController::class, 'offboard'])
        ->middleware('permission:update-workers')
        ->name('offboard');
    Route::delete('{worker}', [WorkerController::class, 'destroy'])
        ->middleware('permission:delete-workers')
        ->name('destroy');
    Route::post('{worker}/replace-tag', [TagController::class, 'replace'])
        ->middleware('permission:update-tags')
        ->name('replace-tag');
});

Route::prefix('workforce/portable-devices')->name('tracking.portable-devices.')->group(function (): void {
    Route::get('/', [PortableDeviceController::class, 'index'])
        ->middleware('permission:view-portable-devices')
        ->name('index');
    Route::post('/', [PortableDeviceController::class, 'store'])
        ->middleware('permission:create-portable-devices')
        ->name('store');
    Route::post('{portableDevice}/revoke', [PortableDeviceController::class, 'revoke'])
        ->middleware('permission:update-portable-devices')
        ->name('revoke');
});

Route::prefix('workforce/workers/{worker}/documents')->name('workers.documents.')->group(function (): void {
    Route::get('/', [WorkerDocumentController::class, 'index'])
        ->middleware('permission:view-worker-documents')
        ->name('index');
    Route::post('/', [WorkerDocumentController::class, 'store'])
        ->middleware('permission:create-worker-documents')
        ->name('store');
    Route::put('{document}', [WorkerDocumentController::class, 'update'])
        ->middleware('permission:update-worker-documents')
        ->name('update');
    Route::post('{document}/verify', [WorkerDocumentController::class, 'verify'])
        ->middleware('permission:update-worker-documents')
        ->name('verify');
    Route::post('{document}/reject', [WorkerDocumentController::class, 'reject'])
        ->middleware('permission:update-worker-documents')
        ->name('reject');
    Route::delete('{document}', [WorkerDocumentController::class, 'destroy'])
        ->middleware('permission:delete-worker-documents')
        ->name('destroy');
});
