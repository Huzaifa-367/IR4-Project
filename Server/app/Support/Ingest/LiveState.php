<?php

namespace App\Support\Ingest;

use Illuminate\Support\Carbon;

final class LiveState
{
    /**
     * Live state advances only forward (DOC-08 §3.4).
     */
    public static function shouldAdvance(
        ?\DateTimeInterface $currentTimestamp,
        \DateTimeInterface $incomingRecordedAt,
    ): bool {
        if ($currentTimestamp === null) {
            return true;
        }

        return Carbon::instance($incomingRecordedAt)
            ->greaterThan(Carbon::instance($currentTimestamp));
    }
}
