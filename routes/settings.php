<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Web\Settings\AuditLogController;
use App\Http\Controllers\Web\Settings\AssetController;
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

    Route::middleware('permission:manage-roles')->group(function () {
        Route::get('settings/roles', [RoleController::class, 'index'])->name('settings.roles.index');
        Route::post('settings/roles', [RoleController::class, 'store'])->name('settings.roles.store');
        Route::put('settings/roles/{role}', [RoleController::class, 'update'])->name('settings.roles.update');
        Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('settings.roles.destroy');
    });

    Route::middleware('permission:manage-users')->group(function () {
        Route::get('settings/users', [UserController::class, 'index'])->name('settings.users.index');
        Route::post('settings/users', [UserController::class, 'store'])->name('settings.users.store');
        Route::put('settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
    });

    Route::middleware('permission:manage-devices')->group(function () {
        Route::get('settings/assets', [AssetController::class, 'index'])->name('settings.assets.index');
        Route::post('settings/assets', [AssetController::class, 'store'])->name('settings.assets.store');
        Route::get('settings/assets/{asset}', [AssetController::class, 'show'])->name('settings.assets.show');
        Route::put('settings/assets/{asset}', [AssetController::class, 'update'])->name('settings.assets.update');
        Route::delete('settings/assets/{asset}', [AssetController::class, 'destroy'])->name('settings.assets.destroy');

        Route::get('settings/cameras', [CameraController::class, 'index'])->name('settings.cameras.index');
        Route::post('settings/cameras', [CameraController::class, 'store'])->name('settings.cameras.store');
        Route::patch('settings/cameras/{camera}/ai', [CameraController::class, 'toggleAi'])->name('settings.cameras.toggle-ai');

        Route::get('settings/devices', [DeviceController::class, 'index'])->name('settings.devices.index');
        Route::post('settings/devices', [DeviceController::class, 'store'])->name('settings.devices.store');
        Route::put('settings/devices/{device}', [DeviceController::class, 'update'])->name('settings.devices.update');
        Route::patch('settings/devices/{device}/status', [DeviceController::class, 'setStatus'])->name('settings.devices.status');
        Route::post('settings/devices/{device}/token', [DeviceController::class, 'regenerateToken'])->name('settings.devices.token');
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
