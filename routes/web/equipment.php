<?php

use App\Http\Controllers\Api\EquipmentLookupController;
use App\Http\Controllers\Web\Equipment\EquipmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-equipment')->prefix('equipment')->name('equipment.')->group(function (): void {
    Route::get('/', [EquipmentController::class, 'index'])->name('index');
    Route::get('create', [EquipmentController::class, 'create'])
        ->middleware('permission:manage-equipment')
        ->name('create');
    Route::post('/', [EquipmentController::class, 'store'])
        ->middleware('permission:manage-equipment')
        ->name('store');
    Route::get('checkouts', [EquipmentController::class, 'checkoutsIndex'])->name('checkouts.index');
    Route::get('import', [EquipmentController::class, 'importForm'])
        ->middleware('permission:manage-equipment')
        ->name('import');
    Route::post('import', [EquipmentController::class, 'import'])
        ->middleware('permission:manage-equipment')
        ->name('import.store');
    Route::get('import/template', [EquipmentController::class, 'importTemplate'])
        ->middleware('permission:manage-equipment')
        ->name('import.template');
    Route::post('print-labels', [EquipmentController::class, 'printLabels'])
        ->middleware('permission:manage-equipment')
        ->name('print-labels');
    Route::post('labels', [EquipmentController::class, 'bulkLabels'])
        ->middleware('permission:manage-equipment')
        ->name('labels');
    Route::post('checkouts/{checkout}/return', [EquipmentController::class, 'returnCheckout'])
        ->middleware('permission:manage-equipment')
        ->name('checkouts.return');
    Route::get('{equipment}', [EquipmentController::class, 'show'])->name('show');
    Route::put('{equipment}', [EquipmentController::class, 'update'])
        ->middleware('permission:manage-equipment')
        ->name('update');
    Route::post('{equipment}/retire', [EquipmentController::class, 'retire'])
        ->middleware('permission:manage-equipment')
        ->name('retire');
    Route::delete('{equipment}', [EquipmentController::class, 'destroy'])
        ->middleware('permission:manage-equipment')
        ->name('destroy');
    Route::post('{equipment}/inspections', [EquipmentController::class, 'storeInspection'])
        ->middleware('permission:manage-equipment')
        ->name('inspections.store');
    Route::post('{equipment}/maintenances', [EquipmentController::class, 'storeMaintenance'])
        ->middleware('permission:manage-equipment')
        ->name('maintenances.store');
    Route::put('{equipment}/schedules', [EquipmentController::class, 'syncSchedules'])
        ->middleware('permission:manage-equipment')
        ->name('schedules.sync');
    Route::post('{equipment}/documents', [EquipmentController::class, 'storeDocument'])
        ->middleware('permission:manage-equipment')
        ->name('documents.store');
    Route::delete('{equipment}/documents/{document}', [EquipmentController::class, 'destroyDocument'])
        ->middleware('permission:manage-equipment')
        ->name('documents.destroy');
    Route::post('{equipment}/checkout', [EquipmentController::class, 'checkout'])
        ->middleware('permission:manage-equipment')
        ->name('checkout');
    Route::get('{equipment}/qr', [EquipmentController::class, 'qr'])->name('qr');
    Route::post('{equipment}/print-label', [EquipmentController::class, 'printLabel'])->name('print-label');
    Route::get('{equipment}/checkouts', [EquipmentController::class, 'show'])->name('checkouts.show');
});

Route::get('api/equipment/by-token/{qrToken}', [EquipmentLookupController::class, 'show'])
    ->middleware('permission:view-equipment')
    ->whereUuid('qrToken')
    ->name('api.equipment.by-token');
