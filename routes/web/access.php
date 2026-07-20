<?php

use App\Http\Controllers\Web\Settings\RoleController;
use App\Http\Controllers\Web\Settings\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:manage-roles')->prefix('access/roles')->name('settings.roles.')->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::put('{role}', [RoleController::class, 'update'])->name('update');
    Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
});

Route::middleware('permission:manage-users')->prefix('access/users')->name('settings.users.')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::put('{user}', [UserController::class, 'update'])->name('update');
});
