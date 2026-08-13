<?php

use App\Models\Equipment;
use App\Services\EquipmentLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Spatie\Backup\Commands\CleanupCommand;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;

uses(RefreshDatabase::class);

it('registers DOC-19/20 scheduled jobs including backup-before-prune', function () {
    $names = collect(Schedule::events())
        ->map(fn ($event) => (string) ($event->description ?? ''))
        ->filter()
        ->values()
        ->all();

    foreach ([
        'ir4:asset-health-mark-stale',
        'ir4:tracking-stationary-tags',
        'ir4:permits-tick',
        'ir4:tracking-absence-sweep',
        'ir4:flag-overdue-equipment',
        'ir4:backup-clean',
        'ir4:backup-run',
        'ir4:backup-monitor',
        'ir4:prune-raw-sensor-data',
        'ir4:prune-export-files',
        'ir4:prune-expired-cache',
        'ir4:check-disk-space',
        'ir4:generate-weekly-report',
    ] as $name) {
        expect($names)->toContain($name);
    }

    $cleanAt = collect(Schedule::events())
        ->first(fn ($event) => ($event->description ?? '') === 'ir4:backup-clean');
    $backupAt = collect(Schedule::events())
        ->first(fn ($event) => ($event->description ?? '') === 'ir4:backup-run');
    $pruneAt = collect(Schedule::events())
        ->first(fn ($event) => ($event->description ?? '') === 'ir4:prune-raw-sensor-data');

    expect($cleanAt)->not->toBeNull()
        ->and($backupAt)->not->toBeNull()
        ->and($pruneAt)->not->toBeNull()
        ->and($cleanAt->expression)->toBe('0 1 * * *')
        ->and($backupAt->expression)->toBe('30 1 * * *')
        ->and($pruneAt->expression)->toBe('15 3 * * *');
});

it('resolves Spatie backup cleanup command after config cache', function () {
    expect(config('backup.cleanup.strategy'))->toBe(
        DefaultStrategy::class,
    );

    $command = app(CleanupCommand::class);

    expect($command)->toBeInstanceOf(CleanupCommand::class);
});

it('exposes health and classifies unauthenticated surfaces', function () {
    $this->get('/up')->assertOk();

    $router = app('router');
    $unauthenticatedApi = collect($router->getRoutes())
        ->filter(function ($route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') && ! str_starts_with($uri, 'e/')) {
                return false;
            }
            $middleware = collect($route->gatherMiddleware());

            return ! $middleware->contains('auth')
                && ! $middleware->contains('auth:sanctum')
                && ! $middleware->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth:'));
        })
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    foreach ($unauthenticatedApi as $uri) {
        $allowed = str_starts_with($uri, 'api/ingest')
            || str_starts_with($uri, 'api/devices')
            || str_starts_with($uri, 'api/health')
            || str_starts_with($uri, 'api/mobile/login')
            || str_starts_with($uri, 'e/');

        expect($allowed)->toBeTrue("Unexpected unauthenticated surface: {$uri}");
    }
});

it('keeps Inertia SSR disabled for the on-prem CSR-only runtime', function () {
    expect(config('inertia.ssr.enabled'))->toBeFalse();

    $config = file_get_contents(base_path('config/inertia.php'));

    expect($config)->toBeString()
        ->and($config)->toContain("'enabled' => false");
});

it('falls back to zpl download when printer host is not configured', function () {
    config(['ir4.equipment.printer_host' => '']);
    $equipment = Equipment::factory()->create();

    $result = app(EquipmentLabelService::class)->printLabel($equipment);

    expect($result['sent'])->toBeFalse()
        ->and($result['printed'])->toBeFalse()
        ->and($result['zpl'])->toContain('^XA')
        ->and($result['error'])->toBe('Printer not configured.');
});
