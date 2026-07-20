<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Web\Settings\AssetController;
use App\Http\Controllers\Web\Settings\AuditLogController;
use App\Http\Controllers\Web\Settings\CameraController;
use App\Http\Controllers\Web\Settings\DeviceController;
use App\Http\Controllers\Web\Settings\GeneralSettingsController;
use App\Http\Controllers\Web\Settings\ReaderBindingController;
use App\Http\Controllers\Web\Settings\RepositioningController;
use App\Http\Controllers\Web\Settings\RoleController;
use App\Http\Controllers\Web\Settings\UserController;
use App\Http\Controllers\Web\Settings\ZoneController;
use App\Http\Middleware\AuditDataAccess;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

$authStack = [
    'auth',
    EnsureUserIsActive::class,
    EnforceIdleTimeout::class,
    EnsurePasswordIsChanged::class,
    AuditDataAccess::class,
];

Route::middleware($authStack)->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/general', [GeneralSettingsController::class, 'edit'])
        ->name('settings.general.edit');
    Route::put('settings/general', [GeneralSettingsController::class, 'update'])
        ->name('settings.general.update');

    Route::middleware('permission:view-audit-log')->group(function (): void {
        Route::get('settings/audit-log', [AuditLogController::class, 'index'])->name('settings.audit-log.index');
        Route::get('settings/audit-log/export', [AuditLogController::class, 'export'])->name('settings.audit-log.export');
    });

    // Access — user RBAC
    Route::middleware('permission:manage-roles')->prefix('access/roles')->name('settings.roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('permission:manage-users')->prefix('access/users')->name('settings.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('{user}', [UserController::class, 'update'])->name('update');
    });

    Route::middleware('permission:manage-devices')->group(function () {
        Route::prefix('hardware/assets')->name('settings.assets.')->group(function () {
            Route::get('/', [AssetController::class, 'index'])->name('index');
            Route::post('/', [AssetController::class, 'store'])->name('store');
            Route::get('{asset}', [AssetController::class, 'show'])->name('show');
            Route::put('{asset}', [AssetController::class, 'update'])->name('update');
            Route::delete('{asset}', [AssetController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('hardware/cameras')->name('settings.cameras.')->group(function () {
            Route::get('/', [CameraController::class, 'index'])->name('index');
            Route::post('/', [CameraController::class, 'store'])->name('store');
            Route::put('{camera}', [CameraController::class, 'update'])->name('update');
            Route::patch('{camera}/status', [CameraController::class, 'setStatus'])->name('status');
            Route::patch('{camera}/ai', [CameraController::class, 'toggleAi'])->name('toggle-ai');
        });

        Route::prefix('hardware/devices')->name('settings.devices.')->group(function () {
            Route::get('/', [DeviceController::class, 'index'])->name('index');
            Route::post('/', [DeviceController::class, 'store'])->name('store');
            Route::put('{device}', [DeviceController::class, 'update'])->name('update');
            Route::patch('{device}/status', [DeviceController::class, 'setStatus'])->name('status');
            Route::post('{device}/token', [DeviceController::class, 'regenerateToken'])->name('token');
        });
    });

    Route::middleware('permission:manage-zones')->group(function () {
        Route::get('settings/zones', [ZoneController::class, 'index'])->name('settings.zones.index');
        Route::post('settings/zones', [ZoneController::class, 'store'])->name('settings.zones.store');
        Route::get('settings/zones/{zone}', [ZoneController::class, 'show'])->name('settings.zones.show');
        Route::put('settings/zones/{zone}', [ZoneController::class, 'update'])->name('settings.zones.update');
        Route::post('settings/zones/{zone}/deactivate', [ZoneController::class, 'deactivate'])->name('settings.zones.deactivate');
        Route::delete('settings/zones/{zone}', [ZoneController::class, 'destroy'])->name('settings.zones.destroy');
        Route::put('settings/zones/{zone}/access-list', [ZoneController::class, 'updateAccessList'])->name('settings.zones.access-list');
        Route::patch('settings/zones/{zone}/map-position', [ZoneController::class, 'setMapPosition'])->name('settings.zones.map-position');

        Route::get('settings/repositioning', [RepositioningController::class, 'index'])->name('settings.repositioning');
        Route::post('settings/readers/{device}/rebind', [ReaderBindingController::class, 'store'])->name('settings.readers.rebind');
        Route::get('settings/readers/{device}/bindings', [ReaderBindingController::class, 'history'])->name('settings.readers.bindings');
    });
});
