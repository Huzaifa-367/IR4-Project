<?php

namespace App\Support;

use App\Enums\HardwareStatus;
use App\Models\Camera;
use App\Models\Device;
use Illuminate\Support\Carbon;

/**
 * Shared online / telemetry-stale rules for operator UI (gas, environment, live wall, devices).
 *
 * Online requires a real last_seen / last_frame from the device. Never treat
 * created_at as presence — no live data means not online.
 */
final class HardwarePresence
{
    public static function isSeenRecently(
        ?\DateTimeInterface $lastSeenAt,
        int $staleMinutes,
        ?\DateTimeInterface $now = null,
    ): bool {
        if ($lastSeenAt === null) {
            return false;
        }

        $now = Carbon::instance($now ?? now());
        $cutoff = $now->copy()->subMinutes(max(1, $staleMinutes));

        return Carbon::instance($lastSeenAt)->greaterThan($cutoff);
    }

    public static function isDeviceOnline(Device $device, int $staleMinutes, ?\DateTimeInterface $now = null): bool
    {
        if (in_array($device->status, [
            HardwareStatus::Retired,
            HardwareStatus::Fault,
            HardwareStatus::Maintenance,
        ], true)) {
            return false;
        }

        return self::isSeenRecently($device->last_seen_at, $staleMinutes, $now);
    }

    public static function isCameraOnline(Camera $camera, int $staleMinutes, ?\DateTimeInterface $now = null): bool
    {
        if (in_array($camera->status, [
            HardwareStatus::Retired,
            HardwareStatus::Fault,
            HardwareStatus::Maintenance,
        ], true)) {
            return false;
        }

        return self::isSeenRecently($camera->last_frame_at, $staleMinutes, $now);
    }

    public static function isTelemetryStale(
        ?\DateTimeInterface $recordedAt,
        int $staleMinutes,
        ?\DateTimeInterface $now = null,
    ): bool {
        $now = Carbon::instance($now ?? now());

        return $recordedAt === null
            || Carbon::instance($recordedAt)->lessThan($now->copy()->subMinutes(max(1, $staleMinutes)));
    }
}
