<?php

namespace App\Services\Ingest;

use App\Enums\AlertType;
use App\Enums\IngestStream;
use App\Models\Device;
use App\Models\IngestEvent;
use App\Services\AlertService;
use App\Support\Ingest\IngestEventRejected;
use App\Support\Ingest\IngestTimestamps;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

final class IngestBatchProcessor
{
    public function __construct(
        private readonly IngestTimestamps $timestamps,
        private readonly AlertService $alerts,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  callable(array<string, mixed>, int): array{event_uid: string, recorded_at: \DateTimeInterface, payload: array<string, mixed>}  $normalize
     * @return array{accepted: int, duplicates: int, rejected: list<array{index: int, code: string}>}
     */
    public function process(Device $device, IngestStream $stream, array $events, callable $normalize): array
    {
        $accepted = 0;
        $duplicates = 0;
        /** @var list<array{index: int, code: string}> $rejected */
        $rejected = [];
        $sawClockSkew = false;

        foreach ($events as $index => $event) {
            if (! is_array($event)) {
                $rejected[] = ['index' => (int) $index, 'code' => 'VALIDATION_FAILED'];

                continue;
            }

            try {
                $mapped = $normalize($event, (int) $index);
                $normalized = $this->timestamps->normalize($mapped['recorded_at']);

                if ($normalized['clock_skew']) {
                    $sawClockSkew = true;
                }

                $uid = $mapped['event_uid'];

                if (IngestEvent::query()
                    ->where('device_id', $device->id)
                    ->where('event_uid', $uid)
                    ->exists()) {
                    $duplicates++;

                    continue;
                }

                try {
                    IngestEvent::query()->create([
                        'device_id' => $device->id,
                        'stream' => $stream,
                        'event_uid' => $uid,
                        'recorded_at' => $normalized['recorded_at'],
                        'received_at' => $normalized['received_at'],
                        'is_backfill' => $normalized['is_backfill'],
                        'clock_skew' => $normalized['clock_skew'],
                        'payload' => $mapped['payload'],
                    ]);
                } catch (QueryException $e) {
                    if ($this->isUniqueViolation($e)) {
                        $duplicates++;

                        continue;
                    }

                    throw $e;
                }

                $accepted++;
            } catch (IngestEventRejected $e) {
                $rejected[] = ['index' => (int) $index, 'code' => $e->rejectionCode];
            }
        }

        if ($sawClockSkew) {
            $this->raiseClockSkewAlert($device);
        }

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'rejected' => $rejected,
        ];
    }

    private function raiseClockSkewAlert(Device $device): void
    {
        $day = Carbon::now()->toDateString();

        $this->alerts->raise(
            type: AlertType::ClockSkew,
            title: "Clock skew on device {$device->name}",
            payload: [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'day' => $day,
            ],
            source: $device,
            dedupeKey: "clock_skew:{$device->id}:{$day}",
        );
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || str_contains($e->getMessage(), 'UNIQUE');
    }
}
