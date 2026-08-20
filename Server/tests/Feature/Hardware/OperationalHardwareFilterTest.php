<?php

use App\Enums\HardwareStatus;
use App\Models\Camera;
use App\Models\Device;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('scopes devices and cameras to operational statuses only', function () {
    $online = Device::factory()->create(['status' => HardwareStatus::Online]);
    $offline = Device::factory()->create(['status' => HardwareStatus::Offline]);
    Device::factory()->create(['status' => HardwareStatus::Maintenance]);
    Device::factory()->create(['status' => HardwareStatus::Retired]);

    $camOnline = Camera::factory()->create(['status' => HardwareStatus::Online]);
    Camera::factory()->create(['status' => HardwareStatus::Retired]);
    Camera::factory()->create(['status' => HardwareStatus::Maintenance]);

    expect(Device::query()->operational()->pluck('id')->all())
        ->toEqualCanonicalizing([$online->id, $offline->id])
        ->and(Camera::query()->operational()->pluck('id')->all())
        ->toBe([$camOnline->id]);
});

it('hides retired and maintenance cameras from the live wall', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view-live-cameras');

    $visible = Camera::factory()->create([
        'name' => 'Visible Cam',
        'status' => HardwareStatus::Online,
    ]);
    Camera::factory()->create([
        'name' => 'Retired Cam',
        'status' => HardwareStatus::Retired,
    ]);
    Camera::factory()->create([
        'name' => 'Maintenance Cam',
        'status' => HardwareStatus::Maintenance,
    ]);

    $this->actingAs($user)
        ->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('live/index')
            ->has('cameras', 1)
            ->where('cameras.0.id', $visible->id)
            ->where('cameras.0.name', 'Visible Cam'));
});
