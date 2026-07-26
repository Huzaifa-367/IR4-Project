<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DisplayController;
use App\Http\Controllers\Web\EnvironmentController;
use App\Http\Controllers\Web\Ppe\LiveWallController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware('permission:view-dashboard')
    ->name('dashboard');
Route::get('api/dashboard/summary', [DashboardController::class, 'summary'])
    ->middleware('permission:view-dashboard')
    ->name('dashboard.summary');
Route::get('display', DisplayController::class)
    ->middleware('permission:view-dashboard')
    ->name('display');
Route::get('environment', [EnvironmentController::class, 'trends'])
    ->middleware('permission:view-dashboard')
    ->name('environment.index');
Route::get('api/environment/live', [EnvironmentController::class, 'live'])
    ->middleware('permission:view-dashboard')
    ->name('environment.live');
Route::get('api/environment/trends', [EnvironmentController::class, 'trends'])
    ->middleware('permission:view-dashboard')
    ->name('environment.trends');

Route::get('live', LiveWallController::class)
    ->middleware('permission:view-live-cameras')
    ->name('live.index');
Route::get('api/live/violations', [LiveWallController::class, 'snapshot'])
    ->middleware('permission:view-live-cameras')
    ->name('live.violations');
