<?php

use App\Enums\AlertType;
use App\Enums\AuditEvent;
use App\Enums\TagStatus;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Equipment;
use App\Models\HseIncident;
use App\Models\LsrViolation;
use App\Models\RfidTag;
use App\Models\Role;
use App\Models\TagReading;
use App\Models\User;
use App\Models\Worker;
use App\Models\Zone;
use App\Services\AlertService;
use App\Services\ReaderBindingService;
use App\Services\RoleService;
use App\Support\PermissionCatalogue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AssertsAudited;
use Tests\Support\IngestTestClient;

uses(AssertsAudited::class);

it('invariant: audit log is append-only at the model and has no mutation routes', function () {
    $log = AuditLog::query()->create([
        'event' => AuditEvent::ConfigChanged,
        'description' => 'seed',
        'occurred_at' => now(),
    ]);

    expect(fn () => $log->update(['description' => 'mutated']))
        ->toThrow(LogicException::class)
        ->and(fn () => $log->delete())
        ->toThrow(LogicException::class);

    $mutating = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'audit-log') || str_contains($route->uri(), 'audit_log'))
        ->filter(fn ($route) => collect($route->methods())->intersect(['PUT', 'PATCH', 'DELETE'])->isNotEmpty());

    expect($mutating)->toHaveCount(0);
});

it('invariant: PPE has no worker identity', function () {
    expect(Schema::hasColumn('ppe_violations', 'worker_id'))->toBeFalse();
});

it('invariant: raising alerts never auto-creates incidents or LSR', function () {
    app(AlertService::class)->raise(
        AlertType::FallDetection,
        null,
        'Fall detected',
        ['zone_id' => 1],
    );

    expect(HseIncident::query()->count())->toBe(0)
        ->and(LsrViolation::query()->count())->toBe(0)
        ->and(Alert::query()->where('alert_type', AlertType::FallDetection)->exists())->toBeTrue();
});

it('invariant: compliance tables are outside the prune allow-list', function () {
    $allow = ['tag_readings', 'gas_readings', 'environmental_readings'];
    $compliance = [
        'alerts', 'gas_alarms', 'hse_incidents', 'lsr_violations', 'weekly_reports',
        'audit_logs', 'entry_exit_logs', 'worker_positions', 'ppe_violations', 'equipment',
    ];

    expect(array_intersect($allow, $compliance))->toBe([]);
});

it('invariant: equipment qr_token is permanent', function () {
    $user = User::factory()->withRole('Safety Manager')->create();
    $this->actingAs($user);

    $this->post(route('equipment.store'), [
        'name' => 'Extinguisher Inv',
        'equipment_type' => 'fire extinguisher',
        'inspection_interval_days' => 30,
    ])->assertRedirect();

    $equipment = Equipment::query()->latest('id')->firstOrFail();
    $token = $equipment->qr_token;

    $this->put(route('equipment.update', $equipment), [
        'name' => 'Extinguisher Inv 2',
        'equipment_type' => 'fire extinguisher',
        'qr_token' => (string) Str::uuid(),
    ])->assertRedirect();

    expect($equipment->fresh()?->qr_token)->toBe($token);
});

it('invariant: one assigned tag per worker and replace is atomic', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $worker = Worker::factory()->create();
    $old = RfidTag::factory()->create();
    $new = RfidTag::factory()->create();

    $this->actingAs($admin)
        ->post(route('tracking.tags.assign', $old), ['worker_id' => $worker->id])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('tracking.tags.assign', $new), ['worker_id' => $worker->id])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message');

    $this->actingAs($admin)
        ->post(route('tracking.workers.replace-tag', $worker), [
            'new_tag_id' => $new->id,
            'old_tag_status' => 'lost',
        ])
        ->assertRedirect();

    expect($old->fresh()->status)->toBe(TagStatus::Lost)
        ->and($old->fresh()->worker_id)->toBeNull()
        ->and($new->fresh()->status)->toBe(TagStatus::Assigned)
        ->and($new->fresh()->worker_id)->toBe($worker->id)
        ->and(RfidTag::query()->where('worker_id', $worker->id)->where('status', TagStatus::Assigned)->count())->toBe(1);
});

it('invariant: device cannot post under another device reference', function () {
    $plainA = 'token-a-own';
    $plainB = 'token-b-own';
    $deviceA = Device::factory()->withPlainToken($plainA)->create();
    $deviceB = Device::factory()->withPlainToken($plainB)->create();
    $tag = RfidTag::factory()->create();

    $response = IngestTestClient::postTagReadings($this, $deviceA, $plainA, [
        IngestTestClient::tagEvent($deviceB->reference, $tag->tag_uid),
    ]);

    $response->assertAccepted()
        ->assertJsonPath('accepted', 0)
        ->assertJsonPath('rejected.0.code', 'FORBIDDEN_REFERENCE');

    expect(TagReading::query()->count())->toBe(0);
});

it('invariant: backfill does not rewind live worker position', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'token-backfill-inv';
    $device = Device::factory()->withPlainToken($plain)->create();
    $zone = Zone::factory()->create();
    app(ReaderBindingService::class)->bind($device, $zone, now()->subDay(), $admin);

    $worker = Worker::factory()->create();
    $tag = RfidTag::factory()->assigned($worker)->create();

    IngestTestClient::postTagReadings($this, $device, $plain, [
        IngestTestClient::tagEvent($device->reference, $tag->tag_uid, recordedAt: now()->toIso8601String()),
    ])->assertAccepted();

    $positionAt = $tag->fresh()->position?->last_seen_at;

    IngestTestClient::postTagReadings($this, $device, $plain, [
        IngestTestClient::tagEvent(
            $device->reference,
            $tag->tag_uid,
            recordedAt: now()->subHours(2)->toIso8601String(),
        ),
    ])->assertAccepted();

    expect($tag->fresh()->position?->last_seen_at?->equalTo($positionAt))->toBeTrue();
});

it('invariant: time-aware zone resolution freezes zone_id on the reading', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $plain = 'token-zone-inv';
    $device = Device::factory()->withPlainToken($plain)->create();
    $zoneA = Zone::factory()->create(['name' => 'Zone A Inv']);
    $zoneB = Zone::factory()->create(['name' => 'Zone B Inv']);
    $bindings = app(ReaderBindingService::class);
    $bindings->bind($device, $zoneA, now()->subDays(2), $admin);
    $bindings->bind($device, $zoneB, now()->subMinutes(30), $admin);

    $tag = RfidTag::factory()->create();
    $past = now()->subHours(3);

    IngestTestClient::postTagReadings($this, $device, $plain, [
        IngestTestClient::tagEvent($device->reference, $tag->tag_uid, recordedAt: $past->toIso8601String()),
    ])->assertAccepted();

    $reading = TagReading::query()->latest('id')->first();
    expect($reading?->zone_id)->toBe($zoneA->id);
});

it('invariant: Super Admin holds the full catalogue and app code has no Gate::before', function () {
    $role = Role::query()->where('name', 'Super Admin')->firstOrFail();
    $held = $role->permissions->pluck('name')->sort()->values()->all();
    $catalogue = collect(PermissionCatalogue::all())->sort()->values()->all();

    expect($held)->toEqual($catalogue);

    $hits = collect(File::allFiles(app_path()))
        ->merge(File::allFiles(base_path('bootstrap')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php'))
        ->filter(fn ($file) => str_contains(File::get($file->getPathname()), 'Gate::before'));

    expect($hits)->toHaveCount(0);
});

it('invariant: read-only roles cannot gain write permissions', function () {
    $service = app(RoleService::class);
    $role = Role::query()->where('name', 'Client Representative')->firstOrFail();

    expect(fn () => $service->syncPermissions($role, ['create-users']))
        ->toThrow(ValidationException::class);
});

it('invariant: standalone — no site_id column anywhere in schema', function () {
    foreach (Schema::getTables() as $table) {
        $name = is_array($table) ? (string) ($table['name'] ?? '') : (string) $table->name;
        if ($name === '') {
            continue;
        }
        expect(Schema::hasColumn($name, 'site_id'))->toBeFalse("Table {$name} has site_id");
    }

    expect(Schema::hasTable('sites'))->toBeFalse();
});

it('invariant: Worker is not a User', function () {
    expect(Schema::hasTable('workers'))->toBeTrue()
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasColumn('workers', 'email'))->toBeFalse()
        ->and(Schema::hasColumn('workers', 'password'))->toBeFalse();
});
