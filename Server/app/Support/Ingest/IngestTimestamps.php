<?php

namespace App\Support\Ingest;

use App\Services\SettingsService;
use Illuminate\Support\Carbon;

/**
 * @phpstan-type NormalizedTimestamps array{
 *     recorded_at: Carbon,
 *     received_at: Carbon,
 *     is_backfill: bool,
 *     clock_skew: bool
 * }
 */
final class IngestTimestamps
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return NormalizedTimestamps
     */
    public function normalize(\DateTimeInterface $recordedAt, ?\DateTimeInterface $receivedAt = null): array
    {
        $received = Carbon::instance($receivedAt ?? now());
        $recorded = Carbon::instance($recordedAt);
        $skewSeconds = (int) $this->settings->get('ingest.future_skew_seconds', 300);
        $backfillSeconds = (int) $this->settings->get('ingest.backfill_after_seconds', 600);

        $clockSkew = false;
        if ($recorded->greaterThan($received->copy()->addSeconds($skewSeconds))) {
            $recorded = $received->copy();
            $clockSkew = true;
        }

        $isBackfill = $recorded->lessThanOrEqualTo($received->copy()->subSeconds($backfillSeconds));

        return [
            'recorded_at' => $recorded,
            'received_at' => $received,
            'is_backfill' => $isBackfill,
            'clock_skew' => $clockSkew,
        ];
    }
}
