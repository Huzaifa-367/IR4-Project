<?php

use App\Http\Controllers\Web\Hse\IncidentController;
use App\Http\Controllers\Web\Hse\LsrController;
use Illuminate\Support\Facades\Route;

Route::prefix('incidents')->name('hse.incidents.')->group(function (): void {
    Route::get('/', [IncidentController::class, 'index'])
        ->middleware('permission:view-incidents')
        ->name('index');
    Route::get('create', [IncidentController::class, 'create'])
        ->middleware('permission:create-incidents')
        ->name('create');
    Route::post('/', [IncidentController::class, 'store'])
        ->middleware('permission:create-incidents')
        ->name('store');
    Route::get('{incident}', [IncidentController::class, 'show'])
        ->middleware('permission:view-incidents')
        ->name('show');
    Route::put('{incident}/classify', [IncidentController::class, 'classify'])
        ->middleware('permission:update-incidents')
        ->name('classify');
    Route::post('{incident}/reopen', [IncidentController::class, 'reopen'])
        ->middleware('permission:update-incidents')
        ->name('reopen');
    Route::post('{incident}/close', [IncidentController::class, 'close'])
        ->middleware('permission:update-incidents')
        ->name('close');
    Route::post('{incident}/evidence', [IncidentController::class, 'storeEvidence'])
        ->middleware('permission:create-incidents')
        ->name('evidence.store');
});

Route::prefix('lsr-violations')->name('hse.lsr.')->group(function (): void {
    Route::get('/', [LsrController::class, 'index'])
        ->middleware('permission:view-lsr')
        ->name('index');
    Route::get('create', [LsrController::class, 'createForm'])
        ->middleware('permission:create-lsr')
        ->name('create');
    Route::get('summary', [LsrController::class, 'summary'])
        ->middleware('permission:view-lsr')
        ->name('summary');
    Route::get('api/summary', [LsrController::class, 'apiSummary'])
        ->middleware('permission:view-lsr')
        ->name('api.summary');
    Route::post('/', [LsrController::class, 'store'])
        ->middleware('permission:create-lsr')
        ->name('store');
    Route::post('close-bulk', [LsrController::class, 'closeBulk'])
        ->middleware('permission:update-lsr')
        ->name('close-bulk');
    Route::get('{lsr}', [LsrController::class, 'show'])
        ->middleware('permission:view-lsr')
        ->name('show');
    Route::post('{lsr}/close', [LsrController::class, 'close'])
        ->middleware('permission:update-lsr')
        ->name('close');
});
