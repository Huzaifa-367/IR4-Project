<?php

use App\Http\Controllers\Web\Permit\WorkerDocumentController;
use App\Http\Controllers\Web\Tracking\PortableDeviceController;
use App\Http\Controllers\Web\Tracking\TagController;
use App\Http\Controllers\Web\Tracking\WorkerController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-tracking')->prefix('workforce/workers')->name('tracking.workers.')->group(function (): void {
    Route::get('/', [WorkerController::class, 'index'])->name('index');
    Route::get('create', [WorkerController::class, 'create'])
        ->middleware('permission:manage-workers')
        ->name('create');
    Route::post('/', [WorkerController::class, 'store'])
        ->middleware('permission:manage-workers')
        ->name('store');
    Route::get('import', [WorkerController::class, 'importForm'])
        ->middleware('permission:manage-workers')
        ->name('import');
    Route::post('import', [WorkerController::class, 'import'])
        ->middleware('permission:manage-workers')
        ->name('import.store');
    Route::get('import/template', [WorkerController::class, 'template'])
        ->middleware('permission:manage-workers')
        ->name('import.template');
    Route::get('{worker}', [WorkerController::class, 'show'])->name('show');
    Route::get('{worker}/edit', [WorkerController::class, 'edit'])
        ->middleware('permission:manage-workers')
        ->name('edit');
    Route::put('{worker}', [WorkerController::class, 'update'])
        ->middleware('permission:manage-workers')
        ->name('update');
    Route::post('{worker}/deactivate', [WorkerController::class, 'deactivate'])
        ->middleware('permission:manage-workers')
        ->name('deactivate');
    Route::post('{worker}/reactivate', [WorkerController::class, 'reactivate'])
        ->middleware('permission:manage-workers')
        ->name('reactivate');
    Route::post('{worker}/offboard', [WorkerController::class, 'offboard'])
        ->middleware('permission:manage-workers')
        ->name('offboard');
    Route::delete('{worker}', [WorkerController::class, 'destroy'])
        ->middleware('permission:manage-workers')
        ->name('destroy');
    Route::post('{worker}/replace-tag', [TagController::class, 'replace'])
        ->middleware('permission:manage-tags')
        ->name('replace-tag');
});

Route::middleware('permission:manage-portable-devices')->prefix('workforce/portable-devices')->name('tracking.portable-devices.')->group(function (): void {
    Route::get('/', [PortableDeviceController::class, 'index'])->name('index');
    Route::post('/', [PortableDeviceController::class, 'store'])->name('store');
    Route::post('{portableDevice}/revoke', [PortableDeviceController::class, 'revoke'])->name('revoke');
});

Route::middleware('permission:manage-worker-documents')->prefix('workforce/workers/{worker}/documents')->name('workers.documents.')->group(function (): void {
    Route::get('/', [WorkerDocumentController::class, 'index'])->name('index');
    Route::post('/', [WorkerDocumentController::class, 'store'])->name('store');
    Route::put('{document}', [WorkerDocumentController::class, 'update'])->name('update');
    Route::post('{document}/verify', [WorkerDocumentController::class, 'verify'])->name('verify');
    Route::post('{document}/reject', [WorkerDocumentController::class, 'reject'])->name('reject');
    Route::delete('{document}', [WorkerDocumentController::class, 'destroy'])->name('destroy');
});
