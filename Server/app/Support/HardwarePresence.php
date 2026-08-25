<?php

namespace App\Support;

use App\Enums\HardwareStatus;
use App\Models\Device;
use Illuminate\Support\Carbon;

/**
 * Shared online rules for operator UI (gas, environment, live wall, hardware).
 *
 * Online = recent heartbeat / frame, never created_at:
 * - Devices: last_seen_at (DOC-05 device heartbeat)
 * - Cameras: last_frame_at (PPE frame or MediaMTX ready refresh)
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

    public static function isCameraOnline(Device $camera, int $staleMinutes, ?\DateTimeInterface $now = null): bool
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

    /**
     * Operator pill label: keep maintenance / retired / fault / degraded;
     * otherwise show live presence (online / offline).
     */
    public static function displayStatus(HardwareStatus $status, bool $isOnline): string
    {
        return match ($status) {
            HardwareStatus::Maintenance,
            HardwareStatus::Retired,
            HardwareStatus::Fault,
            HardwareStatus::Degraded => $status->value,
            default => $isOnline ? HardwareStatus::Online->value : HardwareStatus::Offline->value,
        };
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
