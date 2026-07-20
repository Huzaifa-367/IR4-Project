<?php

use App\Http\Controllers\Web\Permit\CrewRoleController;
use App\Http\Controllers\Web\Permit\PermitController;
use App\Http\Controllers\Web\Permit\PermitTypeController;
use App\Http\Controllers\Web\Permit\WorkerDocumentTypeController;
use App\Http\Controllers\Web\Permit\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-permits')->prefix('workforce/permits')->name('permits.')->group(function (): void {
    Route::get('/', [PermitController::class, 'index'])->name('index');
    Route::get('create', [PermitController::class, 'create'])
        ->middleware('permission:request-permit')
        ->name('create');
    Route::post('/', [PermitController::class, 'store'])
        ->middleware('permission:request-permit')
        ->name('store');
    Route::get('{permit}', [PermitController::class, 'show'])->name('show');
    Route::put('{permit}', [PermitController::class, 'update'])
        ->middleware('permission:request-permit')
        ->name('update');
    Route::post('{permit}/submit', [PermitController::class, 'submit'])
        ->middleware('permission:request-permit')
        ->name('submit');
    Route::post('{permit}/inspection', [PermitController::class, 'inspect'])
        ->middleware('permission:issue-permit|request-permit')
        ->name('inspect');
    Route::post('{permit}/gas-tests', [PermitController::class, 'storeGasTest'])
        ->middleware('permission:perform-gas-test')
        ->name('gas-tests.store');
    Route::get('{permit}/gas-suggestion', [PermitController::class, 'gasSuggestion'])
        ->middleware('permission:perform-gas-test')
        ->name('gas-suggestion');
    Route::post('{permit}/approve', [PermitController::class, 'approve'])
        ->middleware('permission:approve-permit')
        ->name('approve');
    Route::post('{permit}/issue', [PermitController::class, 'issue'])
        ->middleware('permission:issue-permit')
        ->name('issue');
    Route::post('{permit}/suspend', [PermitController::class, 'suspend'])
        ->middleware('permission:issue-permit')
        ->name('suspend');
    Route::post('{permit}/resume', [PermitController::class, 'resume'])
        ->middleware('permission:issue-permit')
        ->name('resume');
    Route::post('{permit}/renew', [PermitController::class, 'renew'])
        ->middleware('permission:issue-permit')
        ->name('renew');
    Route::post('{permit}/cancel', [PermitController::class, 'cancel'])
        ->middleware('permission:issue-permit')
        ->name('cancel');
    Route::post('{permit}/close', [PermitController::class, 'close'])
        ->middleware('permission:issue-permit')
        ->name('close');
    Route::post('{permit}/reject', [PermitController::class, 'reject'])
        ->middleware('permission:issue-permit')
        ->name('reject');
});

Route::middleware('permission:view-permits')->prefix('workforce/work-orders')->name('work-orders.')->group(function (): void {
    Route::get('/', [WorkOrderController::class, 'index'])->name('index');
    Route::get('create', [WorkOrderController::class, 'create'])
        ->middleware('permission:request-permit')
        ->name('create');
    Route::post('/', [WorkOrderController::class, 'store'])
        ->middleware('permission:request-permit')
        ->name('store');
    Route::get('{workOrder}', [WorkOrderController::class, 'show'])->name('show');
});

Route::middleware('permission:manage-permit-catalogue')->prefix('workforce/permit-types')->name('settings.permit-types.')->group(function (): void {
    Route::get('/', [PermitTypeController::class, 'index'])->name('index');
    Route::post('/', [PermitTypeController::class, 'store'])->name('store');
    Route::get('{permitType}', [PermitTypeController::class, 'show'])->name('show');
    Route::put('{permitType}', [PermitTypeController::class, 'update'])->name('update');

    Route::post('{permitType}/checklist-items', [PermitTypeController::class, 'storeChecklistItem'])->name('checklist-items.store');
    Route::put('{permitType}/checklist-items/{checklistItem}', [PermitTypeController::class, 'updateChecklistItem'])->name('checklist-items.update');
    Route::delete('{permitType}/checklist-items/{checklistItem}', [PermitTypeController::class, 'destroyChecklistItem'])->name('checklist-items.destroy');

    Route::post('{permitType}/gas-channels', [PermitTypeController::class, 'storeGasChannel'])->name('gas-channels.store');
    Route::put('{permitType}/gas-channels/{gasChannel}', [PermitTypeController::class, 'updateGasChannel'])->name('gas-channels.update');
    Route::delete('{permitType}/gas-channels/{gasChannel}', [PermitTypeController::class, 'destroyGasChannel'])->name('gas-channels.destroy');

    Route::post('{permitType}/conflicts', [PermitTypeController::class, 'storeConflict'])->name('conflicts.store');
    Route::put('{permitType}/conflicts/{conflict}', [PermitTypeController::class, 'updateConflict'])->name('conflicts.update');
    Route::delete('{permitType}/conflicts/{conflict}', [PermitTypeController::class, 'destroyConflict'])->name('conflicts.destroy');

    Route::post('{permitType}/document-requirements', [PermitTypeController::class, 'storeDocumentRequirement'])->name('document-requirements.store');
    Route::put('{permitType}/document-requirements/{documentRequirement}', [PermitTypeController::class, 'updateDocumentRequirement'])->name('document-requirements.update');
    Route::delete('{permitType}/document-requirements/{documentRequirement}', [PermitTypeController::class, 'destroyDocumentRequirement'])->name('document-requirements.destroy');
});

Route::middleware('permission:manage-permit-catalogue')->prefix('workforce/worker-document-types')->name('settings.worker-document-types.')->group(function (): void {
    Route::get('/', [WorkerDocumentTypeController::class, 'index'])->name('index');
    Route::post('/', [WorkerDocumentTypeController::class, 'store'])->name('store');
    Route::put('{workerDocumentType}', [WorkerDocumentTypeController::class, 'update'])->name('update');
});

Route::middleware('permission:manage-permit-catalogue')->prefix('workforce/crew-roles')->name('settings.crew-roles.')->group(function (): void {
    Route::get('/', [CrewRoleController::class, 'index'])->name('index');
    Route::post('/', [CrewRoleController::class, 'store'])->name('store');
    Route::put('{crewRole}', [CrewRoleController::class, 'update'])->name('update');
    Route::delete('{crewRole}', [CrewRoleController::class, 'destroy'])->name('destroy');
});
