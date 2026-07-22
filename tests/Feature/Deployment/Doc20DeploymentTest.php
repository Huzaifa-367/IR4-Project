<?php

use App\Models\Equipment;
use App\Services\EquipmentLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

uses(RefreshDatabase::class);

it('ships production deploy artifacts required by DOC-20', function () {
    $required = [
        'deploy/README.md',
        'deploy/env/ir4.production.env.example',
        'deploy/nginx/ir4.conf',
        'deploy/supervisor/ir4.conf',
        'deploy/php-fpm/ir4.conf',
        'deploy/firewall/nftables.conf',
        'deploy/database/mysql-grants.sql',
        'deploy/logrotate/ir4',
        'deploy/scripts/preflight.sh',
        'deploy/scripts/deploy.sh',
        'deploy/scripts/verify-network-fences.sh',
        'deploy/operations.md',
        'deploy/offsite-backup.md',
        'deploy/commissioning-signoff.md',
    ];

    foreach ($required as $path) {
        expect(File::exists(base_path($path)))->toBeTrue($path.' missing');
    }
});

it('documents mysql-only least-privilege grants for lifecycle users', function () {
    $sql = File::get(base_path('deploy/database/mysql-grants.sql'));

    expect($sql)
        ->toContain("CREATE USER IF NOT EXISTS 'ir4_app'@'localhost'")
        ->toContain("CREATE USER IF NOT EXISTS 'ir4_backup'@'localhost'")
        ->toContain("CREATE USER IF NOT EXISTS 'ir4_restore'@'localhost'")
        ->toContain("CREATE USER IF NOT EXISTS 'ir4_wipe'@'localhost'")
        ->toContain('REVOKE UPDATE, DELETE ON `ir4`.`audit_logs` FROM')
        ->not->toContain('pgsql')
        ->not->toContain('postgresql');
});

it('production env template uses redis queue/cache and lifecycle connections', function () {
    $env = File::get(base_path('deploy/env/ir4.production.env.example'));

    expect($env)
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('DB_CONNECTION=mysql')
        ->toContain('QUEUE_CONNECTION=redis')
        ->toContain('CACHE_STORE=redis')
        ->toContain('LOG_STACK=daily')
        ->toContain('EQUIPMENT_PRINTER_HOST=')
        ->toContain('IR4_BACKUP_CONNECTION=ir4_backup')
        ->toContain('IR4_WIPE_CONNECTION=ir4_wipe')
        ->toContain('IR4_RESTORE_CONNECTION=ir4_restore')
        ->not->toContain('BACKUP_PG_DUMP_PATH');
});

it('registers the DOC-19/20 scheduled job names', function () {
    $names = collect(Schedule::events())
        ->map(fn ($event) => (string) ($event->description ?? ''))
        ->filter()
        ->values()
        ->all();

    foreach ([
        'ir4:asset-health-mark-stale',
        'ir4:tracking-stationary-tags',
        'ir4:tracking-absence-sweep',
        'ir4:flag-overdue-equipment',
        'ir4:prune-raw-sensor-data',
        'ir4:prune-export-files',
        'ir4:backup-database',
        'ir4:check-disk-space',
        'ir4:backup-gap-check',
        'ir4:generate-weekly-report',
    ] as $name) {
        expect($names)->toContain($name);
    }
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

it('configures distinct backup restore and wipe connection names', function () {
    expect(config('backup.backup_connection'))->toBe('sqlite') // phpunit override
        ->and(config('backup.wipe_connection'))->toBe('sqlite')
        ->and(config('backup.restore_connection'))->toBe('ir4_restore')
        ->and(config('database.connections.ir4_backup'))->toBeArray()
        ->and(config('database.connections.ir4_wipe'))->toBeArray()
        ->and(config('database.connections.ir4_restore'))->toBeArray()
        ->and(config('backup.pg_dump_path'))->toBeNull();
});
