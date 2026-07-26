<?php

use App\Http\Controllers\Web\Ppe\PpeTrendsController;
use App\Http\Controllers\Web\Ppe\PpeViolationController;
use Illuminate\Support\Facades\Route;

Route::prefix('ppe')->name('ppe.')->group(function (): void {
    Route::get('/', PpeTrendsController::class)
        ->middleware('permission:view-ppe')
        ->name('index');
    Route::get('violations', [PpeViolationController::class, 'index'])
        ->middleware('permission:view-ppe')
        ->name('violations.index');
    Route::post('violations/bulk-review', [PpeViolationController::class, 'bulkReview'])
        ->middleware('permission:update-ppe-violations')
        ->name('violations.bulk-review');
    Route::post('violations/export', [PpeViolationController::class, 'export'])
        ->middleware('permission:export-ppe-violations')
        ->name('violations.export');
    Route::get('violations/{violation}', [PpeViolationController::class, 'show'])
        ->middleware('permission:view-ppe')
        ->name('violations.show');
    Route::post('violations/{violation}/review', [PpeViolationController::class, 'review'])
        ->middleware('permission:update-ppe-violations')
        ->name('violations.review');
    Route::get('api/violations/summary', [PpeViolationController::class, 'summary'])
        ->middleware('permission:view-ppe')
        ->name('api.summary');
    Route::get('api/violations/recent', [PpeViolationController::class, 'recent'])
        ->middleware('permission:view-ppe')
        ->name('api.recent');
});
