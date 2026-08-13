<?php

namespace App\Support;

use App\Enums\HardwareStatus;
use App\Models\Camera;
use App\Models\Device;
use Illuminate\Support\Carbon;

/**
 * Shared online / telemetry-stale rules for operator UI (gas, environment, live wall, devices).
 *
 * - Online: last_seen / last_frame within the health stale window (grace: created_at when never seen).
 * - Telemetry stale: latest reading `recorded_at` older than the same window (Online + Stale is valid).
 */
final class HardwarePresence
{
    public static function isSeenRecently(
        ?\DateTimeInterface $lastSeenAt,
        int $staleMinutes,
        ?\DateTimeInterface $createdAt = null,
        ?\DateTimeInterface $now = null,
    ): bool {
        $now = Carbon::instance($now ?? now());
        $cutoff = $now->copy()->subMinutes(max(1, $staleMinutes));

        if ($lastSeenAt !== null) {
            return Carbon::instance($lastSeenAt)->greaterThan($cutoff);
        }

        return $createdAt !== null && Carbon::instance($createdAt)->greaterThan($cutoff);
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

        return self::isSeenRecently($device->last_seen_at, $staleMinutes, $device->created_at, $now);
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

        return self::isSeenRecently($camera->last_frame_at, $staleMinutes, $camera->created_at, $now);
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
