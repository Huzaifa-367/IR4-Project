<?php

use App\Http\Controllers\Web\Ppe\PpeTrendsController;
use App\Http\Controllers\Web\Ppe\PpeViolationController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-ppe')->prefix('ppe')->name('ppe.')->group(function (): void {
    Route::get('/', PpeTrendsController::class)->name('index');
    Route::get('violations', [PpeViolationController::class, 'index'])->name('violations.index');
    Route::post('violations/bulk-review', [PpeViolationController::class, 'bulkReview'])
        ->middleware('permission:review-ppe')
        ->name('violations.bulk-review');
    Route::post('violations/export', [PpeViolationController::class, 'export'])
        ->middleware('permission:export-ppe-reports')
        ->name('violations.export');
    Route::get('violations/{violation}', [PpeViolationController::class, 'show'])->name('violations.show');
    Route::post('violations/{violation}/review', [PpeViolationController::class, 'review'])
        ->middleware('permission:review-ppe')
        ->name('violations.review');
    Route::get('api/violations/summary', [PpeViolationController::class, 'summary'])->name('api.summary');
    Route::get('api/violations/recent', [PpeViolationController::class, 'recent'])->name('api.recent');
});
