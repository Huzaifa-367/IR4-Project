<?php

use App\Enums\HardwareStatus;
use App\Models\Camera;
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

it('treats camera online from recent last_frame_at heartbeat', function () {
    $now = Carbon::parse('2026-08-13 12:00:00');
    $fresh = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'last_frame_at' => $now->copy()->subMinute(),
    ]);
    $stale = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'last_frame_at' => $now->copy()->subMinutes(10),
    ]);
    $never = Camera::factory()->create([
        'status' => HardwareStatus::Online,
        'last_frame_at' => null,
    ]);

    expect(HardwarePresence::isCameraOnline($fresh, 5, $now))->toBeTrue()
        ->and(HardwarePresence::isCameraOnline($stale, 5, $now))->toBeFalse()
        ->and(HardwarePresence::isCameraOnline($never, 5, $now))->toBeFalse();
});

it('keeps operator-held statuses in displayStatus', function () {
    expect(HardwarePresence::displayStatus(HardwareStatus::Maintenance, true))->toBe('maintenance')
        ->and(HardwarePresence::displayStatus(HardwareStatus::Online, true))->toBe('online')
        ->and(HardwarePresence::displayStatus(HardwareStatus::Online, false))->toBe('offline')
        ->and(HardwarePresence::displayStatus(HardwareStatus::Degraded, false))->toBe('degraded');
});
