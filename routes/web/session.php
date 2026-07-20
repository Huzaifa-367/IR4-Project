<?php

use App\Http\Controllers\Web\ForcePasswordController;
use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('session/heartbeat', [SessionController::class, 'heartbeat'])
    ->name('session.heartbeat');

Route::get('force-password', [ForcePasswordController::class, 'edit'])
    ->name('force-password.edit');
Route::post('force-password', [ForcePasswordController::class, 'update'])
    ->name('force-password.update');
