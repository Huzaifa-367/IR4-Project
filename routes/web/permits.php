<?php

use App\Http\Controllers\Web\Permit\CrewRoleController;
use App\Http\Controllers\Web\Permit\PermitController;
use App\Http\Controllers\Web\Permit\PermitTypeController;
use App\Http\Controllers\Web\Permit\WorkerDocumentTypeController;
use App\Http\Controllers\Web\Permit\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('workforce/permits')->name('permits.')->group(function (): void {
    Route::get('/', [PermitController::class, 'index'])
        ->middleware('permission:view-permits')
        ->name('index');
    Route::get('create', [PermitController::class, 'create'])
        ->middleware('permission:create-permits')
        ->name('create');
    Route::post('/', [PermitController::class, 'store'])
        ->middleware('permission:create-permits')
        ->name('store');
    Route::get('{permit}', [PermitController::class, 'show'])
        ->middleware('permission:view-permits')
        ->name('show');
    Route::put('{permit}', [PermitController::class, 'update'])
        ->middleware('permission:create-permits')
        ->name('update');
    Route::post('{permit}/submit', [PermitController::class, 'submit'])
        ->middleware('permission:create-permits')
        ->name('submit');
    Route::post('{permit}/inspection', [PermitController::class, 'inspect'])
        ->middleware('permission:update-permits|create-permits')
        ->name('inspect');
    Route::post('{permit}/gas-tests', [PermitController::class, 'storeGasTest'])
        ->middleware('permission:create-permit-gas-tests')
        ->name('gas-tests.store');
    Route::get('{permit}/gas-suggestion', [PermitController::class, 'gasSuggestion'])
        ->middleware('permission:create-permit-gas-tests')
        ->name('gas-suggestion');
    Route::post('{permit}/approve', [PermitController::class, 'approve'])
        ->middleware('permission:update-permits')
        ->name('approve');
    Route::post('{permit}/issue', [PermitController::class, 'issue'])
        ->middleware('permission:update-permits')
        ->name('issue');
    Route::post('{permit}/suspend', [PermitController::class, 'suspend'])
        ->middleware('permission:update-permits')
        ->name('suspend');
    Route::post('{permit}/resume', [PermitController::class, 'resume'])
        ->middleware('permission:update-permits')
        ->name('resume');
    Route::post('{permit}/renew', [PermitController::class, 'renew'])
        ->middleware('permission:update-permits')
        ->name('renew');
    Route::post('{permit}/cancel', [PermitController::class, 'cancel'])
        ->middleware('permission:update-permits')
        ->name('cancel');
    Route::post('{permit}/close', [PermitController::class, 'close'])
        ->middleware('permission:update-permits')
        ->name('close');
    Route::post('{permit}/reject', [PermitController::class, 'reject'])
        ->middleware('permission:update-permits')
        ->name('reject');
});

Route::prefix('workforce/work-orders')->name('work-orders.')->group(function (): void {
    Route::get('/', [WorkOrderController::class, 'index'])
        ->middleware('permission:view-permits')
        ->name('index');
    Route::get('create', [WorkOrderController::class, 'create'])
        ->middleware('permission:create-permits')
        ->name('create');
    Route::post('/', [WorkOrderController::class, 'store'])
        ->middleware('permission:create-permits')
        ->name('store');
    Route::get('{workOrder}', [WorkOrderController::class, 'show'])
        ->middleware('permission:view-permits')
        ->name('show');
});

Route::prefix('workforce/permit-types')->name('settings.permit-types.')->group(function (): void {
    Route::get('/', [PermitTypeController::class, 'index'])
        ->middleware('permission:view-permit-catalogue')
        ->name('index');
    Route::post('/', [PermitTypeController::class, 'store'])
        ->middleware('permission:create-permit-catalogue')
        ->name('store');
    Route::get('{permitType}', [PermitTypeController::class, 'show'])
        ->middleware('permission:view-permit-catalogue')
        ->name('show');
    Route::put('{permitType}', [PermitTypeController::class, 'update'])
        ->middleware('permission:update-permit-catalogue')
        ->name('update');

    Route::post('{permitType}/checklist-items', [PermitTypeController::class, 'storeChecklistItem'])
        ->middleware('permission:create-permit-catalogue')
        ->name('checklist-items.store');
    Route::put('{permitType}/checklist-items/{checklistItem}', [PermitTypeController::class, 'updateChecklistItem'])
        ->middleware('permission:update-permit-catalogue')
        ->name('checklist-items.update');
    Route::delete('{permitType}/checklist-items/{checklistItem}', [PermitTypeController::class, 'destroyChecklistItem'])
        ->middleware('permission:delete-permit-catalogue')
        ->name('checklist-items.destroy');

    Route::post('{permitType}/gas-channels', [PermitTypeController::class, 'storeGasChannel'])
        ->middleware('permission:create-permit-catalogue')
        ->name('gas-channels.store');
    Route::put('{permitType}/gas-channels/{gasChannel}', [PermitTypeController::class, 'updateGasChannel'])
        ->middleware('permission:update-permit-catalogue')
        ->name('gas-channels.update');
    Route::delete('{permitType}/gas-channels/{gasChannel}', [PermitTypeController::class, 'destroyGasChannel'])
        ->middleware('permission:delete-permit-catalogue')
        ->name('gas-channels.destroy');

    Route::post('{permitType}/conflicts', [PermitTypeController::class, 'storeConflict'])
        ->middleware('permission:create-permit-catalogue')
        ->name('conflicts.store');
    Route::put('{permitType}/conflicts/{conflict}', [PermitTypeController::class, 'updateConflict'])
        ->middleware('permission:update-permit-catalogue')
        ->name('conflicts.update');
    Route::delete('{permitType}/conflicts/{conflict}', [PermitTypeController::class, 'destroyConflict'])
        ->middleware('permission:delete-permit-catalogue')
        ->name('conflicts.destroy');

    Route::post('{permitType}/document-requirements', [PermitTypeController::class, 'storeDocumentRequirement'])
        ->middleware('permission:create-permit-catalogue')
        ->name('document-requirements.store');
    Route::put('{permitType}/document-requirements/{documentRequirement}', [PermitTypeController::class, 'updateDocumentRequirement'])
        ->middleware('permission:update-permit-catalogue')
        ->name('document-requirements.update');
    Route::delete('{permitType}/document-requirements/{documentRequirement}', [PermitTypeController::class, 'destroyDocumentRequirement'])
        ->middleware('permission:delete-permit-catalogue')
        ->name('document-requirements.destroy');
});

Route::prefix('workforce/worker-document-types')->name('settings.worker-document-types.')->group(function (): void {
    Route::get('/', [WorkerDocumentTypeController::class, 'index'])
        ->middleware('permission:view-permit-catalogue')
        ->name('index');
    Route::post('/', [WorkerDocumentTypeController::class, 'store'])
        ->middleware('permission:create-permit-catalogue')
        ->name('store');
    Route::put('{workerDocumentType}', [WorkerDocumentTypeController::class, 'update'])
        ->middleware('permission:update-permit-catalogue')
        ->name('update');
});

Route::prefix('workforce/crew-roles')->name('settings.crew-roles.')->group(function (): void {
    Route::get('/', [CrewRoleController::class, 'index'])
        ->middleware('permission:view-permit-catalogue')
        ->name('index');
    Route::post('/', [CrewRoleController::class, 'store'])
        ->middleware('permission:create-permit-catalogue')
        ->name('store');
    Route::put('{crewRole}', [CrewRoleController::class, 'update'])
        ->middleware('permission:update-permit-catalogue')
        ->name('update');
    Route::delete('{crewRole}', [CrewRoleController::class, 'destroy'])
        ->middleware('permission:delete-permit-catalogue')
        ->name('destroy');
});
