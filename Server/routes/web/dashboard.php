<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EnvironmentController;
use App\Http\Controllers\Web\Ppe\HlsProxyController;
use App\Http\Controllers\Web\Ppe\LiveWallController;
use App\Http\Middleware\AuditDataAccess;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware('permission:view-dashboard')
    ->name('dashboard');
Route::get('api/dashboard/summary', [DashboardController::class, 'summary'])
    ->middleware('permission:view-dashboard')
    ->name('dashboard.summary');
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
Route::any('hls/{path?}', HlsProxyController::class)
    ->where('path', '.*')
    ->middleware('permission:view-live-cameras')
    ->withoutMiddleware([
        // hls.js fetches playlist + init + segments in parallel per camera.
        // Inertia share / idle timeout would hit sessions+permissions+settings
        // on every .mp4 (duplicate-query alerts) and keep the idle clock alive.
        HandleAppearance::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
        EnforceIdleTimeout::class,
        EnsurePasswordIsChanged::class,
        AuditDataAccess::class,
    ])
    ->name('live.hls');
