<?php

use App\Enums\DeviceType;
use App\Enums\ZoneType;
use App\Models\Device;
use App\Models\ReaderZoneBinding;
use App\Models\User;
use App\Models\Worker;
use App\Models\Zone;
use App\Services\ReaderBindingService;
use App\Services\ZoneService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('creates zones and lists them for manage-zones', function () {
    $admin = User::factory()->withRole('Super Admin')->create();

    $this->actingAs($admin)
        ->post(route('settings.zones.store'), [
            'name' => 'Work Front A',
            'zone_type' => 'work',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('settings.zones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/zones/index')
            ->has('zones.data', 1));
});

it('binds a reader closing the prior open binding contiguously', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $reader = Device::factory()->create(['device_type' => DeviceType::RfidReader]);
    $zoneA = Zone::factory()->create(['name' => 'Zone A', 'created_by' => $admin->id]);
    $zoneB = Zone::factory()->create(['name' => 'Zone B', 'created_by' => $admin->id]);

    $t1 = Carbon::parse('2026-07-01 08:00:00');
    $t2 = Carbon::parse('2026-07-10 08:00:00');

    $service = app(ReaderBindingService::class);
    $first = $service->bind($reader, $zoneA, $t1, $admin);
    $second = $service->bind($reader, $zoneB, $t2, $admin, 'moved north');

    $old = ReaderZoneBinding::query()->findOrFail($first['binding']->id);
    $new = $second['binding'];

    expect($old->bound_until?->equalTo($t2))->toBeTrue()
        ->and($new->bound_from->equalTo($t2))->toBeTrue()
        ->and($new->bound_until)->toBeNull()
        ->and(ReaderZoneBinding::query()->where('device_id', $reader->id)->whereNull('bound_until')->count())->toBe(1);
});

it('resolveZoneAt uses recorded_at not current binding', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $reader = Device::factory()->create(['device_type' => DeviceType::RfidReader]);
    $zoneA = Zone::factory()->create(['created_by' => $admin->id]);
    $zoneB = Zone::factory()->create(['created_by' => $admin->id]);

    $service = app(ReaderBindingService::class);
    $t1 = Carbon::parse('2026-07-01 08:00:00');
    $t2 = Carbon::parse('2026-07-10 08:00:00');

    $service->bind($reader, $zoneA, $t1, $admin);
    $service->bind($reader, $zoneB, $t2, $admin);

    expect($service->resolveZoneAt($reader, Carbon::parse('2026-07-05 12:00:00'))?->id)->toBe($zoneA->id)
        ->and($service->resolveZoneAt($reader, $t2)?->id)->toBe($zoneB->id)
        ->and($service->resolveZoneAt($reader, Carbon::parse('2026-06-01')) )->toBeNull();
});

it('rejects binding a non-reader device', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $device = Device::factory()->create(['device_type' => DeviceType::GasDetector]);
    $zone = Zone::factory()->create(['created_by' => $admin->id]);

    expect(fn () => app(ReaderBindingService::class)->bind($device, $zone, now(), $admin))
        ->toThrow(ValidationException::class);
});

it('blocks deleting a zone that has bindings', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $zone = Zone::factory()->create(['created_by' => $admin->id]);
    $reader = Device::factory()->create(['device_type' => DeviceType::RfidReader]);
    app(ReaderBindingService::class)->bind($reader, $zone, now(), $admin);

    $this->actingAs($admin)
        ->delete(route('settings.zones.destroy', $zone))
        ->assertStatus(409);
});

it('allows deleting a never-bound zone', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $zone = Zone::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->delete(route('settings.zones.destroy', $zone))
        ->assertRedirect(route('settings.zones.index'));

    expect(Zone::query()->find($zone->id))->toBeNull();
});

it('warns when rebinding a gate reader', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $reader = Device::factory()->create(['device_type' => DeviceType::RfidReader]);
    $gate = Zone::factory()->gate()->create(['created_by' => $admin->id]);
    $work = Zone::factory()->create(['created_by' => $admin->id]);

    $service = app(ReaderBindingService::class);
    $service->bind($reader, $gate, now()->subHour(), $admin);
    $result = $service->bind($reader, $work, now(), $admin);

    expect($result['gate_warning'])->toBeTrue();
});

it('syncs zone access lists', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $zone = Zone::factory()->restricted()->create(['created_by' => $admin->id]);
    $workers = Worker::factory()->count(2)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->put(route('settings.zones.access-list', $zone), [
            'worker_ids' => $workers->pluck('id')->all(),
        ])
        ->assertRedirect();

    expect(app(ZoneService::class)->workerIsAuthorized($zone, $workers[0]->id))->toBeTrue()
        ->and(app(ZoneService::class)->workerIsAuthorized($zone, Worker::factory()->create(['created_by' => $admin->id])->id))->toBeFalse();
});

it('exposes coverage for view-tracking users', function () {
    $user = User::factory()->withRole('SCC Operator')->create();
    $reader = Device::factory()->create(['device_type' => DeviceType::RfidReader]);
    $zone = Zone::factory()->create(['created_by' => $user->id]);
    app(ReaderBindingService::class)->bind($reader, $zone, now(), $user);

    $this->actingAs($user)
        ->getJson(route('tracking.coverage'))
        ->assertOk()
        ->assertJsonPath('data.0.zone.id', $zone->id);
});

it('forbids zone settings without manage-zones', function () {
    $user = User::factory()->withRole('Project Manager')->create();

    $this->actingAs($user)
        ->get(route('settings.zones.index'))
        ->assertForbidden();
});
