<?php

use App\Http\Controllers\Api\EquipmentLookupController;
use App\Http\Controllers\Public\EquipmentPublicController;
use App\Http\Controllers\Web\AlertController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DisplayController;
use App\Http\Controllers\Web\EnvironmentController;
use App\Http\Controllers\Web\Equipment\EquipmentController;
use App\Http\Controllers\Web\ForcePasswordController;
use App\Http\Controllers\Web\Gas\GasDashboardController;
use App\Http\Controllers\Web\Hse\IncidentController;
use App\Http\Controllers\Web\Hse\LsrController;
use App\Http\Controllers\Web\MapTileController;
use App\Http\Controllers\Web\Permit\CrewRoleController;
use App\Http\Controllers\Web\Permit\PermitController;
use App\Http\Controllers\Web\Permit\PermitTypeController;
use App\Http\Controllers\Web\Permit\WorkerDocumentController;
use App\Http\Controllers\Web\Ppe\LiveWallController;
use App\Http\Controllers\Web\Ppe\PpeTrendsController;
use App\Http\Controllers\Web\Ppe\PpeViolationController;
use App\Http\Controllers\Web\Reports\VehicleViolationController;
use App\Http\Controllers\Web\Reports\WeeklyReportController;
use App\Http\Controllers\Web\SessionController;
use App\Http\Controllers\Web\Tracking\CoverageController;
use App\Http\Controllers\Web\Tracking\EntryExitController;
use App\Http\Controllers\Web\Tracking\EvacuationController;
use App\Http\Controllers\Web\Tracking\PortableDeviceController;
use App\Http\Controllers\Web\Tracking\TagController;
use App\Http\Controllers\Web\Tracking\TrackingApiController;
use App\Http\Controllers\Web\Tracking\TrackingDashboardController;
use App\Http\Controllers\Web\Tracking\WorkerController;
use App\Http\Middleware\AuditDataAccess;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

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

$authStack = [
    'auth',
    EnsureUserIsActive::class,
    EnforceIdleTimeout::class,
    EnsurePasswordIsChanged::class,
    AuditDataAccess::class,
];

Route::middleware($authStack)->group(function () {
    Route::middleware('permission:view-dashboard')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('api/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
        Route::get('display', DisplayController::class)->name('display');
        Route::get('environment', [EnvironmentController::class, 'trends'])->name('environment.index');
        Route::get('api/environment/live', [EnvironmentController::class, 'live'])->name('environment.live');
        Route::get('api/environment/trends', [EnvironmentController::class, 'trends'])->name('environment.trends');
    });

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

    Route::middleware('permission:view-gas')->prefix('gas')->name('gas.')->group(function () {
        Route::get('/', [GasDashboardController::class, 'index'])->name('index');
        Route::get('api/live', [GasDashboardController::class, 'live'])->name('api.live');
        Route::get('trends', [GasDashboardController::class, 'trends'])->name('trends.index');
        Route::get('alarms', [GasDashboardController::class, 'alarms'])->name('alarms.index');
        Route::post('alarms/{alarm}/acknowledge', [GasDashboardController::class, 'acknowledge'])
            ->middleware('permission:acknowledge-alerts')
            ->name('alarms.acknowledge');
    });

    Route::get('settings/gas-thresholds', [GasDashboardController::class, 'thresholds'])
        ->middleware('permission:view-gas')
        ->name('gas.thresholds.index');
    Route::put('settings/gas-thresholds', [GasDashboardController::class, 'updateThresholds'])
        ->middleware('permission:manage-gas-thresholds')
        ->name('gas.thresholds.update');

    Route::middleware('permission:view-live-cameras')->group(function () {
        Route::get('live', LiveWallController::class)->name('live.index');
        Route::get('api/live/violations', [LiveWallController::class, 'snapshot'])->name('live.violations');
    });

    Route::middleware('permission:view-ppe')->prefix('ppe')->name('ppe.')->group(function () {
        Route::get('violations', [PpeViolationController::class, 'index'])->name('violations.index');
        Route::post('violations/bulk-review', [PpeViolationController::class, 'bulkReview'])
            ->middleware('permission:review-ppe')
            ->name('violations.bulk-review');
        Route::post('violations/export', [PpeViolationController::class, 'export'])
            ->middleware('permission:export-ppe-reports')
            ->name('violations.export');
        Route::get('violations/{violation}', [PpeViolationController::class, 'show'])->name('violations.show');
        Route::post('violations/{violation}/review', [PpeViolationController::class, 'review'])
            ->middleware('permission:review-ppe')
            ->name('violations.review');
        Route::get('trends', PpeTrendsController::class)->name('trends.index');
        Route::get('api/violations/summary', [PpeViolationController::class, 'summary'])->name('api.summary');
        Route::get('api/violations/recent', [PpeViolationController::class, 'recent'])->name('api.recent');
    });

    Route::middleware('permission:view-tracking')->prefix('tracking')->name('tracking.')->group(function () {
        Route::get('/', TrackingDashboardController::class)->name('index');
        Route::get('coverage', CoverageController::class)->name('coverage');
        Route::get('api/headcount', [TrackingApiController::class, 'headcount'])->name('api.headcount');
        Route::get('api/positions', [TrackingApiController::class, 'positions'])->name('api.positions');

        Route::get('entry-exit', [EntryExitController::class, 'index'])
            ->middleware('permission:view-entry-exit')
            ->name('entry-exit.index');
        Route::get('entry-exit/export', [EntryExitController::class, 'export'])
            ->middleware('permission:view-entry-exit')
            ->name('entry-exit.export');
        Route::post('entry-exit/corrections', [EntryExitController::class, 'correct'])
            ->middleware('permission:manage-workers')
            ->name('entry-exit.corrections');

        Route::get('evacuation', [EvacuationController::class, 'index'])->name('evacuation.index');
        Route::post('evacuation', [EvacuationController::class, 'store'])
            ->middleware('permission:trigger-evacuation')
            ->name('evacuation.store');
        Route::get('evacuation/{evacuation}', [EvacuationController::class, 'show'])->name('evacuation.show');
        Route::post('evacuation/{evacuation}/close', [EvacuationController::class, 'close'])
            ->middleware('permission:manage-evacuation')
            ->name('evacuation.close');
        Route::post('evacuation/{evacuation}/entries/{entry}', [EvacuationController::class, 'account'])
            ->middleware('permission:manage-evacuation')
            ->name('evacuation.account');
        Route::get('evacuation/{evacuation}/download', [EvacuationController::class, 'download'])
            ->name('evacuation.download');
    });

    Route::middleware('permission:view-tracking')->prefix('hardware/tags')->name('tracking.tags.')->group(function () {
        Route::get('/', [TagController::class, 'index'])->name('index');
        Route::post('/', [TagController::class, 'store'])
            ->middleware('permission:manage-tags')
            ->name('store');
        Route::post('{tag}/assign', [TagController::class, 'assign'])
            ->middleware('permission:manage-tags')
            ->name('assign');
        Route::post('{tag}/unassign', [TagController::class, 'unassign'])
            ->middleware('permission:manage-tags')
            ->name('unassign');
    });

    Route::middleware('permission:view-tracking')->prefix('workforce/workers')->name('tracking.workers.')->group(function () {
        Route::get('/', [WorkerController::class, 'index'])->name('index');
        Route::get('create', [WorkerController::class, 'create'])
            ->middleware('permission:manage-workers')
            ->name('create');
        Route::post('/', [WorkerController::class, 'store'])
            ->middleware('permission:manage-workers')
            ->name('store');
        Route::get('import', [WorkerController::class, 'importForm'])
            ->middleware('permission:manage-workers')
            ->name('import');
        Route::post('import', [WorkerController::class, 'import'])
            ->middleware('permission:manage-workers')
            ->name('import.store');
        Route::get('import/template', [WorkerController::class, 'template'])
            ->middleware('permission:manage-workers')
            ->name('import.template');
        Route::get('{worker}', [WorkerController::class, 'show'])->name('show');
        Route::get('{worker}/edit', [WorkerController::class, 'edit'])
            ->middleware('permission:manage-workers')
            ->name('edit');
        Route::put('{worker}', [WorkerController::class, 'update'])
            ->middleware('permission:manage-workers')
            ->name('update');
        Route::post('{worker}/deactivate', [WorkerController::class, 'deactivate'])
            ->middleware('permission:manage-workers')
            ->name('deactivate');
        Route::post('{worker}/reactivate', [WorkerController::class, 'reactivate'])
            ->middleware('permission:manage-workers')
            ->name('reactivate');
        Route::post('{worker}/offboard', [WorkerController::class, 'offboard'])
            ->middleware('permission:manage-workers')
            ->name('offboard');
        Route::delete('{worker}', [WorkerController::class, 'destroy'])
            ->middleware('permission:manage-workers')
            ->name('destroy');
        Route::post('{worker}/replace-tag', [TagController::class, 'replace'])
            ->middleware('permission:manage-tags')
            ->name('replace-tag');
    });

    Route::middleware('permission:manage-portable-devices')->prefix('workforce/portable-devices')->name('tracking.portable-devices.')->group(function () {
        Route::get('/', [PortableDeviceController::class, 'index'])->name('index');
        Route::post('/', [PortableDeviceController::class, 'store'])->name('store');
        Route::post('{portableDevice}/revoke', [PortableDeviceController::class, 'revoke'])->name('revoke');
    });

    Route::middleware('permission:view-permits')->prefix('workforce/permits')->name('permits.')->group(function (): void {
        Route::get('/', [PermitController::class, 'index'])->name('index');
        Route::get('create', [PermitController::class, 'create'])
            ->middleware('permission:request-permit')
            ->name('create');
        Route::post('/', [PermitController::class, 'store'])
            ->middleware('permission:request-permit')
            ->name('store');
        Route::get('{permit}', [PermitController::class, 'show'])->name('show');
        Route::post('{permit}/submit', [PermitController::class, 'submit'])
            ->middleware('permission:request-permit')
            ->name('submit');
        Route::post('{permit}/inspection', [PermitController::class, 'inspect'])
            ->middleware('permission:issue-permit')
            ->name('inspect');
        Route::post('{permit}/gas-tests', [PermitController::class, 'storeGasTest'])
            ->middleware('permission:perform-gas-test')
            ->name('gas-tests.store');
        Route::get('{permit}/gas-suggestion', [PermitController::class, 'gasSuggestion'])
            ->middleware('permission:perform-gas-test')
            ->name('gas-suggestion');
        Route::post('{permit}/approve', [PermitController::class, 'approve'])
            ->middleware('permission:approve-permit')
            ->name('approve');
        Route::post('{permit}/issue', [PermitController::class, 'issue'])
            ->middleware('permission:issue-permit')
            ->name('issue');
        Route::post('{permit}/suspend', [PermitController::class, 'suspend'])
            ->middleware('permission:issue-permit')
            ->name('suspend');
        Route::post('{permit}/resume', [PermitController::class, 'resume'])
            ->middleware('permission:issue-permit')
            ->name('resume');
        Route::post('{permit}/renew', [PermitController::class, 'renew'])
            ->middleware('permission:issue-permit')
            ->name('renew');
        Route::post('{permit}/cancel', [PermitController::class, 'cancel'])
            ->middleware('permission:issue-permit')
            ->name('cancel');
        Route::post('{permit}/close', [PermitController::class, 'close'])
            ->middleware('permission:issue-permit')
            ->name('close');
        Route::post('{permit}/reject', [PermitController::class, 'reject'])
            ->middleware('permission:issue-permit')
            ->name('reject');
    });

    Route::middleware('permission:manage-permit-catalogue')->prefix('workforce/permit-types')->name('settings.permit-types.')->group(function (): void {
        Route::get('/', [PermitTypeController::class, 'index'])->name('index');
        Route::post('/', [PermitTypeController::class, 'store'])->name('store');
        Route::get('{permitType}', [PermitTypeController::class, 'show'])->name('show');
        Route::put('{permitType}', [PermitTypeController::class, 'update'])->name('update');
    });

    Route::middleware('permission:manage-permit-catalogue')->prefix('access/crew-roles')->name('settings.crew-roles.')->group(function (): void {
        Route::get('/', [CrewRoleController::class, 'index'])->name('index');
        Route::post('/', [CrewRoleController::class, 'store'])->name('store');
        Route::put('{crewRole}', [CrewRoleController::class, 'update'])->name('update');
        Route::delete('{crewRole}', [CrewRoleController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('permission:manage-worker-documents')->prefix('workforce/workers/{worker}/documents')->name('workers.documents.')->group(function (): void {
        Route::get('/', [WorkerDocumentController::class, 'index'])->name('index');
        Route::post('/', [WorkerDocumentController::class, 'store'])->name('store');
        Route::post('{document}/verify', [WorkerDocumentController::class, 'verify'])->name('verify');
        Route::delete('{document}', [WorkerDocumentController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('permission:view-equipment')->prefix('equipment')->name('equipment.')->group(function (): void {
        Route::get('/', [EquipmentController::class, 'index'])->name('index');
        Route::get('create', [EquipmentController::class, 'create'])
            ->middleware('permission:manage-equipment')
            ->name('create');
        Route::post('/', [EquipmentController::class, 'store'])
            ->middleware('permission:manage-equipment')
            ->name('store');
        Route::get('checkouts', [EquipmentController::class, 'checkoutsIndex'])->name('checkouts.index');
        Route::get('import', [EquipmentController::class, 'importForm'])
            ->middleware('permission:manage-equipment')
            ->name('import');
        Route::post('import', [EquipmentController::class, 'import'])
            ->middleware('permission:manage-equipment')
            ->name('import.store');
        Route::get('import/template', [EquipmentController::class, 'importTemplate'])
            ->middleware('permission:manage-equipment')
            ->name('import.template');
        Route::post('print-labels', [EquipmentController::class, 'printLabels'])
            ->middleware('permission:manage-equipment')
            ->name('print-labels');
        Route::post('labels', [EquipmentController::class, 'bulkLabels'])
            ->middleware('permission:manage-equipment')
            ->name('labels');
        Route::post('checkouts/{checkout}/return', [EquipmentController::class, 'returnCheckout'])
            ->middleware('permission:manage-equipment')
            ->name('checkouts.return');
        Route::get('{equipment}', [EquipmentController::class, 'show'])->name('show');
        Route::put('{equipment}', [EquipmentController::class, 'update'])
            ->middleware('permission:manage-equipment')
            ->name('update');
        Route::post('{equipment}/retire', [EquipmentController::class, 'retire'])
            ->middleware('permission:manage-equipment')
            ->name('retire');
        Route::delete('{equipment}', [EquipmentController::class, 'destroy'])
            ->middleware('permission:manage-equipment')
            ->name('destroy');
        Route::post('{equipment}/inspections', [EquipmentController::class, 'storeInspection'])
            ->middleware('permission:manage-equipment')
            ->name('inspections.store');
        Route::post('{equipment}/maintenances', [EquipmentController::class, 'storeMaintenance'])
            ->middleware('permission:manage-equipment')
            ->name('maintenances.store');
        Route::put('{equipment}/schedules', [EquipmentController::class, 'syncSchedules'])
            ->middleware('permission:manage-equipment')
            ->name('schedules.sync');
        Route::post('{equipment}/documents', [EquipmentController::class, 'storeDocument'])
            ->middleware('permission:manage-equipment')
            ->name('documents.store');
        Route::delete('{equipment}/documents/{document}', [EquipmentController::class, 'destroyDocument'])
            ->middleware('permission:manage-equipment')
            ->name('documents.destroy');
        Route::post('{equipment}/checkout', [EquipmentController::class, 'checkout'])
            ->middleware('permission:manage-equipment')
            ->name('checkout');
        Route::get('{equipment}/qr', [EquipmentController::class, 'qr'])->name('qr');
        Route::post('{equipment}/print-label', [EquipmentController::class, 'printLabel'])->name('print-label');
        Route::get('{equipment}/checkouts', [EquipmentController::class, 'show'])->name('checkouts.show');
    });

    Route::get('api/equipment/by-token/{qrToken}', [EquipmentLookupController::class, 'show'])
        ->middleware('permission:view-equipment')
        ->whereUuid('qrToken')
        ->name('api.equipment.by-token');

    Route::middleware('permission:view-incidents')->prefix('incidents')->name('hse.incidents.')->group(function (): void {
        Route::get('/', [IncidentController::class, 'index'])->name('index');
        Route::get('create', [IncidentController::class, 'create'])
            ->middleware('permission:log-incidents')
            ->name('create');
        Route::post('/', [IncidentController::class, 'store'])
            ->middleware('permission:log-incidents')
            ->name('store');
        Route::get('{incident}', [IncidentController::class, 'show'])->name('show');
        Route::put('{incident}/classify', [IncidentController::class, 'classify'])
            ->middleware('permission:classify-incidents')
            ->name('classify');
        Route::post('{incident}/reopen', [IncidentController::class, 'reopen'])
            ->middleware('permission:classify-incidents')
            ->name('reopen');
        Route::post('{incident}/close', [IncidentController::class, 'close'])
            ->middleware('permission:classify-incidents')
            ->name('close');
        Route::post('{incident}/evidence', [IncidentController::class, 'storeEvidence'])
            ->middleware('permission:log-incidents')
            ->name('evidence.store');
    });

    Route::middleware('permission:view-lsr')->prefix('lsr-violations')->name('hse.lsr.')->group(function (): void {
        Route::get('/', [LsrController::class, 'index'])->name('index');
        Route::get('create', [LsrController::class, 'createForm'])
            ->middleware('permission:log-lsr')
            ->name('create');
        Route::get('summary', [LsrController::class, 'summary'])->name('summary');
        Route::get('api/summary', [LsrController::class, 'apiSummary'])->name('api.summary');
        Route::post('/', [LsrController::class, 'store'])
            ->middleware('permission:log-lsr')
            ->name('store');
        Route::post('close-bulk', [LsrController::class, 'closeBulk'])
            ->middleware('permission:close-lsr')
            ->name('close-bulk');
        Route::get('{lsr}', [LsrController::class, 'show'])->name('show');
        Route::post('{lsr}/close', [LsrController::class, 'close'])
            ->middleware('permission:close-lsr')
            ->name('close');
    });

    Route::middleware('permission:view-reports')->prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/', [WeeklyReportController::class, 'index'])->name('index');
        Route::get('settings', [WeeklyReportController::class, 'settings'])
            ->middleware('permission:manage-settings')
            ->name('settings');
        Route::put('settings', [WeeklyReportController::class, 'updateSettings'])
            ->middleware('permission:manage-settings')
            ->name('settings.update');
        Route::get('vehicle-violations', [VehicleViolationController::class, 'index'])
            ->middleware('permission:log-vehicle-violations')
            ->name('vehicle-violations.index');
        Route::post('vehicle-violations', [VehicleViolationController::class, 'store'])
            ->middleware('permission:log-vehicle-violations')
            ->name('vehicle-violations.store');
        Route::delete('vehicle-violations/{vehicleViolation}', [VehicleViolationController::class, 'destroy'])
            ->middleware('permission:log-vehicle-violations')
            ->name('vehicle-violations.destroy');
        Route::get('{report}', [WeeklyReportController::class, 'show'])->name('show');
    });

    Route::middleware('permission:generate-reports')->post('weekly-reports/generate', [WeeklyReportController::class, 'generate'])
        ->name('weekly-reports.generate');
    Route::middleware('permission:publish-reports')->post('weekly-reports/{report}/publish', [WeeklyReportController::class, 'publish'])
        ->name('weekly-reports.publish');
    Route::middleware('permission:view-reports')->get('weekly-reports/{report}/download', [WeeklyReportController::class, 'download'])
        ->name('weekly-reports.download');

    Route::post('session/heartbeat', [SessionController::class, 'heartbeat'])
        ->name('session.heartbeat');

    Route::get('force-password', [ForcePasswordController::class, 'edit'])
        ->name('force-password.edit');
    Route::post('force-password', [ForcePasswordController::class, 'update'])
        ->name('force-password.update');
});

require __DIR__.'/settings.php';
