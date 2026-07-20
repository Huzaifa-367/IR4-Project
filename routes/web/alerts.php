<?php

use App\Http\Controllers\Web\AlertController;
use Illuminate\Support\Facades\Route;

Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
Route::get('api/alerts/open', [AlertController::class, 'open'])->name('alerts.open');
Route::post('alerts/acknowledge-bulk', [AlertController::class, 'acknowledgeBulk'])
    ->middleware('permission:acknowledge-alerts')
    ->name('alerts.acknowledge-bulk');
Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])
    ->middleware('permission:acknowledge-alerts')
    ->name('alerts.acknowledge');
Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])
    ->middleware('permission:configure-alerts')
    ->name('alerts.resolve');
