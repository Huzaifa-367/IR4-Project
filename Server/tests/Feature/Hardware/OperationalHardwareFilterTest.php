<?php

use App\Enums\HardwareStatus;
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

    $camOnline = Device::factory()->camera()->create(['status' => HardwareStatus::Online]);
    Device::factory()->camera()->create(['status' => HardwareStatus::Retired]);
    Device::factory()->camera()->create(['status' => HardwareStatus::Maintenance]);

    expect(Device::query()->operational()->pluck('id')->all())
        ->toEqualCanonicalizing([$online->id, $offline->id])
        ->and(Device::query()->cameras()->operational()->pluck('id')->all())
        ->toBe([$camOnline->id]);
});

it('hides retired and maintenance cameras from the live wall', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view-live-cameras');

    $visible = Device::factory()->camera()->create([
        'name' => 'Visible Cam',
        'status' => HardwareStatus::Online,
    ]);
    Device::factory()->camera()->create([
        'name' => 'Retired Cam',
        'status' => HardwareStatus::Retired,
    ]);
    Device::factory()->camera()->create([
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
