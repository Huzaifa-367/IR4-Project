<?php

namespace App\Support;

use App\Enums\TagProximity;

/**
 * What a UHF Gen2 inventory read can tell us.
 *
 * FXR90 does not report tag distance. RSSI is backscatter strength, not a
 * ranging measurement — orientation, body, metal, and TX power swing it by
 * tens of dB. Use proximity bands, never metres.
 */
final class RfidSignal
{
    public const NEAR_RSSI_DBM = -40;

    public const MID_RSSI_DBM = -60;

    public static function proximity(?int $rssi): ?TagProximity
    {
        if ($rssi === null) {
            return null;
        }

        return match (true) {
            $rssi >= self::NEAR_RSSI_DBM => TagProximity::Near,
            $rssi >= self::MID_RSSI_DBM => TagProximity::Mid,
            default => TagProximity::Far,
        };
    }
}
