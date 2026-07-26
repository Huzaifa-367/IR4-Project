<?php

use App\Http\Controllers\Web\Settings\RoleController;
use App\Http\Controllers\Web\Settings\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('access/roles')->name('settings.roles.')->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])
        ->middleware('permission:view-roles')
        ->name('index');
    Route::post('/', [RoleController::class, 'store'])
        ->middleware('permission:create-roles')
        ->name('store');
    Route::put('{role}', [RoleController::class, 'update'])
        ->middleware('permission:update-roles')
        ->name('update');
    Route::delete('{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:delete-roles')
        ->name('destroy');
});

Route::prefix('access/users')->name('settings.users.')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])
        ->middleware('permission:view-users')
        ->name('index');
    Route::post('/', [UserController::class, 'store'])
        ->middleware('permission:create-users')
        ->name('store');
    Route::put('{user}', [UserController::class, 'update'])
        ->middleware('permission:update-users')
        ->name('update');
});
