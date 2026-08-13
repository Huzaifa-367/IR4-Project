<?php

use App\Enums\HardwareStatus;
use App\Models\Device;
use App\Support\HardwarePresence;
use Illuminate\Support\Carbon;

it('does not treat a never-seen device as online', function () {
    $device = Device::factory()->create([
        'last_seen_at' => null,
        'status' => HardwareStatus::Online,
    ]);

    expect(HardwarePresence::isDeviceOnline($device, 5))->toBeFalse();
});

it('is online only when last_seen is inside the stale window', function () {
    $now = Carbon::parse('2026-08-13 12:00:00');
    $device = Device::factory()->create([
        'last_seen_at' => $now->copy()->subMinute(),
        'status' => HardwareStatus::Online,
    ]);

    expect(HardwarePresence::isDeviceOnline($device, 5, $now))->toBeTrue();

    $device->forceFill(['last_seen_at' => $now->copy()->subMinutes(10)])->save();

    expect(HardwarePresence::isDeviceOnline($device->fresh(), 5, $now))->toBeFalse();
});
