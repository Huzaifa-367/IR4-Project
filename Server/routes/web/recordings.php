<?php

use App\Http\Controllers\Web\Recordings\RecordingBrowserController;
use App\Http\Middleware\AuditDataAccess;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

// Stream first so it is not swallowed by the browse {path?} catch-all.
Route::match(['get', 'head'], 'recordings/stream/{path}', [RecordingBrowserController::class, 'stream'])
    ->where('path', '.*')
    ->middleware('permission:view-recordings')
    ->withoutMiddleware([
        HandleAppearance::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
        EnforceIdleTimeout::class,
        EnsurePasswordIsChanged::class,
        AuditDataAccess::class,
    ])
    ->name('recordings.stream');

Route::get('recordings/{path?}', [RecordingBrowserController::class, 'index'])
    ->where('path', '.*')
    ->middleware('permission:view-recordings')
    ->name('recordings.index');
