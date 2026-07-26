<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Web\Reports\WeeklyReportController;
use App\Http\Controllers\Web\Settings\AuditLogController;
use App\Http\Controllers\Web\Settings\GeneralSettingsController;
use App\Http\Controllers\Web\Settings\ReaderBindingController;
use App\Http\Controllers\Web\Settings\RepositioningController;
use App\Http\Controllers\Web\Settings\ZoneController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

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
    ->middleware('permission:view-settings|update-settings|update-alert-settings|view-gas-thresholds|update-gas-thresholds')
    ->name('settings.general.edit');
Route::put('settings/general', [GeneralSettingsController::class, 'update'])
    ->middleware('permission:update-settings|update-alert-settings|update-gas-thresholds')
    ->name('settings.general.update');

Route::get('settings/reports', [WeeklyReportController::class, 'settings'])
    ->middleware('permission:view-settings|update-settings')
    ->name('settings.reports.edit');
Route::put('settings/reports', [WeeklyReportController::class, 'updateSettings'])
    ->middleware('permission:update-settings')
    ->name('settings.reports.update');

Route::get('settings/audit-log', [AuditLogController::class, 'index'])
    ->middleware('permission:view-audit-log')
    ->name('settings.audit-log.index');
Route::get('settings/audit-log/export', [AuditLogController::class, 'export'])
    ->middleware('permission:view-audit-log')
    ->name('settings.audit-log.export');

Route::get('settings/zones', [ZoneController::class, 'index'])
    ->middleware('permission:view-zones')
    ->name('settings.zones.index');
Route::post('settings/zones', [ZoneController::class, 'store'])
    ->middleware('permission:create-zones')
    ->name('settings.zones.store');
Route::get('settings/zones/{zone}', [ZoneController::class, 'show'])
    ->middleware('permission:view-zones')
    ->name('settings.zones.show');
Route::put('settings/zones/{zone}', [ZoneController::class, 'update'])
    ->middleware('permission:update-zones')
    ->name('settings.zones.update');
Route::post('settings/zones/{zone}/deactivate', [ZoneController::class, 'deactivate'])
    ->middleware('permission:update-zones')
    ->name('settings.zones.deactivate');
Route::delete('settings/zones/{zone}', [ZoneController::class, 'destroy'])
    ->middleware('permission:delete-zones')
    ->name('settings.zones.destroy');
Route::put('settings/zones/{zone}/access-list', [ZoneController::class, 'updateAccessList'])
    ->middleware('permission:update-zones')
    ->name('settings.zones.access-list');
Route::patch('settings/zones/{zone}/map-position', [ZoneController::class, 'setMapPosition'])
    ->middleware('permission:update-zones')
    ->name('settings.zones.map-position');

Route::get('settings/repositioning', [RepositioningController::class, 'index'])
    ->middleware('permission:view-zones')
    ->name('settings.repositioning');
Route::post('settings/readers/{device}/rebind', [ReaderBindingController::class, 'store'])
    ->middleware('permission:update-zones')
    ->name('settings.readers.rebind');
Route::get('settings/readers/{device}/bindings', [ReaderBindingController::class, 'history'])
    ->middleware('permission:view-zones')
    ->name('settings.readers.bindings');
