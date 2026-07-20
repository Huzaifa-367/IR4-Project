<?php

use App\Http\Controllers\Web\Hse\IncidentController;
use App\Http\Controllers\Web\Hse\LsrController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-incidents')->prefix('incidents')->name('hse.incidents.')->group(function (): void {
    Route::get('/', [IncidentController::class, 'index'])->name('index');
    Route::get('create', [IncidentController::class, 'create'])
        ->middleware('permission:log-incidents')
        ->name('create');
    Route::post('/', [IncidentController::class, 'store'])
        ->middleware('permission:log-incidents')
        ->name('store');
    Route::get('{incident}', [IncidentController::class, 'show'])->name('show');
    Route::put('{incident}/classify', [IncidentController::class, 'classify'])
        ->middleware('permission:classify-incidents')
        ->name('classify');
    Route::post('{incident}/reopen', [IncidentController::class, 'reopen'])
        ->middleware('permission:classify-incidents')
        ->name('reopen');
    Route::post('{incident}/close', [IncidentController::class, 'close'])
        ->middleware('permission:classify-incidents')
        ->name('close');
    Route::post('{incident}/evidence', [IncidentController::class, 'storeEvidence'])
        ->middleware('permission:log-incidents')
        ->name('evidence.store');
});

Route::middleware('permission:view-lsr')->prefix('lsr-violations')->name('hse.lsr.')->group(function (): void {
    Route::get('/', [LsrController::class, 'index'])->name('index');
    Route::get('create', [LsrController::class, 'createForm'])
        ->middleware('permission:log-lsr')
        ->name('create');
    Route::get('summary', [LsrController::class, 'summary'])->name('summary');
    Route::get('api/summary', [LsrController::class, 'apiSummary'])->name('api.summary');
    Route::post('/', [LsrController::class, 'store'])
        ->middleware('permission:log-lsr')
        ->name('store');
    Route::post('close-bulk', [LsrController::class, 'closeBulk'])
        ->middleware('permission:close-lsr')
        ->name('close-bulk');
    Route::get('{lsr}', [LsrController::class, 'show'])->name('show');
    Route::post('{lsr}/close', [LsrController::class, 'close'])
        ->middleware('permission:close-lsr')
        ->name('close');
});
