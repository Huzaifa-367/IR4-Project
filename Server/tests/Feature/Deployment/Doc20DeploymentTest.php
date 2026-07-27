<?php

use App\Models\Equipment;
use App\Services\EquipmentLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;

uses(RefreshDatabase::class);

it('registers the DOC-19/20 scheduled job names that remain after backup removal', function () {
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
        'ir4:prune-raw-sensor-data',
        'ir4:prune-export-files',
        'ir4:generate-weekly-report',
    ] as $name) {
        expect($names)->toContain($name);
    }

    expect($names)->not->toContain('ir4:backup-database')
        ->and($names)->not->toContain('ir4:backup-gap-check')
        ->and($names)->not->toContain('ir4:check-disk-space');
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

it('falls back to zpl download when printer host is not configured', function () {
    config(['ir4.equipment.printer_host' => '']);
    $equipment = Equipment::factory()->create();

    $result = app(EquipmentLabelService::class)->printLabel($equipment);

    expect($result['sent'])->toBeFalse()
        ->and($result['printed'])->toBeFalse()
        ->and($result['zpl'])->toContain('^XA')
        ->and($result['error'])->toBe('Printer not configured.');
});
