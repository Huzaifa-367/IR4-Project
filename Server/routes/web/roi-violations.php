<?php

use App\Http\Controllers\Web\RoiViolation\RoiViolationController;
use Illuminate\Support\Facades\Route;

Route::prefix('roi-violations')->name('roi-violations.')->group(function (): void {
    Route::get('/', [RoiViolationController::class, 'index'])
        ->middleware('permission:view-roi-violations')
        ->name('index');
    Route::post('/bulk-review', [RoiViolationController::class, 'bulkReview'])
        ->middleware('permission:update-roi-violations')
        ->name('bulk-review');
    Route::get('/{violation:uuid}', [RoiViolationController::class, 'show'])
        ->middleware('permission:view-roi-violations')
        ->name('show');
    Route::post('/{violation:uuid}/review', [RoiViolationController::class, 'review'])
        ->middleware('permission:update-roi-violations')
        ->name('review');
});
