<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DisplayController;
use App\Http\Controllers\Web\EnvironmentController;
use App\Http\Controllers\Web\Ppe\LiveWallController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view-dashboard')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('api/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
    Route::get('display', DisplayController::class)->name('display');
    Route::get('environment', [EnvironmentController::class, 'trends'])->name('environment.index');
    Route::get('api/environment/live', [EnvironmentController::class, 'live'])->name('environment.live');
    Route::get('api/environment/trends', [EnvironmentController::class, 'trends'])->name('environment.trends');
});

Route::middleware('permission:view-live-cameras')->group(function (): void {
    Route::get('live', LiveWallController::class)->name('live.index');
    Route::get('api/live/violations', [LiveWallController::class, 'snapshot'])->name('live.violations');
});
