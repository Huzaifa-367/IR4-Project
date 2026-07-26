<?php

use App\Http\Controllers\Public\EquipmentPublicController;
use App\Http\Controllers\Web\MapTileController;
use App\Http\Middleware\AuditDataAccess;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes (Surface A + public Surface C)
|--------------------------------------------------------------------------
|
| Domain modules live under routes/web/*.php and are loaded inside the
| authenticated middleware stack below. Keep route names stable.
|
*/

Route::inertia('/', 'welcome')->name('home');

Route::get('maps/{filename}', MapTileController::class)
    ->where('filename', '[a-z0-9_-]+\.pmtiles')
    ->name('maps.tiles');

Route::middleware('throttle:equipment.public')->group(function (): void {
    Route::get('e/{qrToken}', [EquipmentPublicController::class, 'show'])
        ->whereUuid('qrToken')
        ->name('public.equipment.show');
    Route::match(['post', 'put', 'patch', 'delete'], 'e/{qrToken}', [EquipmentPublicController::class, 'rejectWrite'])
        ->whereUuid('qrToken');
});

Route::middleware([
    'auth',
    EnsureUserIsActive::class,
    EnforceIdleTimeout::class,
    EnsurePasswordIsChanged::class,
    AuditDataAccess::class,
])->group(function (): void {
    require __DIR__.'/web/dashboard.php';
    require __DIR__.'/web/alerts.php';
    require __DIR__.'/web/gas.php';
    require __DIR__.'/web/ppe.php';
    require __DIR__.'/web/tracking.php';
    require __DIR__.'/web/hardware.php';
    require __DIR__.'/web/workforce.php';
    require __DIR__.'/web/permits.php';
    require __DIR__.'/web/equipment.php';
    require __DIR__.'/web/hse.php';
    require __DIR__.'/web/reports.php';
    require __DIR__.'/web/access.php';
    require __DIR__.'/web/settings.php';
    require __DIR__.'/web/session.php';
});
