<?php

namespace App\Services;

use App\Models\EnvironmentalReading;
use App\Models\EnvironmentalRollup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Log;

final class SensorRollupService
{
    private const LOOKBACK_HOURS = 72;

    /**
     * Recompute completed hours (idempotent) for environmental readings.
     * Gas trends/reports aggregate raw `gas_readings` on read (DOC-11) — no gas rollup table.
     *
     * @return array{gas_buckets: int, env_buckets: int}
     */
    public function buildCompletedHours(?\DateTimeInterface $now = null): array
    {
        $now = Carbon::instance($now ?? now());
        $horizon = $now->copy()->startOfHour();
        $from = $horizon->copy()->subHours(self::LOOKBACK_HOURS);

        $envBuckets = $this->rebuildEnvWindow($from, $horizon);

        Log::info('ir4.rollups.built', [
            'gas_buckets' => 0,
            'env_buckets' => $envBuckets,
            'from' => $from->toIso8601String(),
            'horizon' => $horizon->toIso8601String(),
        ]);

        return [
            'gas_buckets' => 0,
            'env_buckets' => $envBuckets,
        ];
    }

    public function rebuildEnvBucket(int $deviceId, \DateTimeInterface $bucketStart): void
    {
        $bucket = Carbon::instance($bucketStart)->startOfHour();
        $rows = EnvironmentalReading::query()
            ->where('device_id', $deviceId)
            ->whereBetween('recorded_at', [$bucket, $bucket->copy()->endOfHour()])
            ->get();

        if ($rows->isEmpty()) {
            EnvironmentalRollup::query()
                ->where('device_id', $deviceId)
                ->where('bucket_start', $bucket)
                ->delete();

            return;
        }

        EnvironmentalRollup::query()->updateOrCreate(
            ['device_id' => $deviceId, 'bucket_start' => $bucket],
            [
                ...$this->channelStats($rows, 'temperature_c', 'temp'),
                ...$this->channelStats($rows, 'humidity_pct', 'humidity'),
                ...$this->channelStats($rows, 'wind_speed_ms', 'wind'),
                'extra_stats' => ($extra = $this->extraStats($rows)) !== [] ? $extra : null,
                'sample_count' => $rows->count(),
            ],
        );
    }

    /**
     * Rebuild environmental hourly rollups for every bucket that has raw readings in range.
     */
    public function rebuildEnvRange(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        $from = Carbon::instance($from)->startOfHour();
        $to = Carbon::instance($to)->copy()->endOfHour()->addSecond();

        return $this->rebuildEnvWindow($from, $to);
    }

    private function rebuildEnvWindow(\DateTimeInterface $from, \DateTimeInterface $horizon): int
    {
        $pairs = EnvironmentalReading::query()
            ->where('recorded_at', '>=', Carbon::instance($from))
            ->where('recorded_at', '<', Carbon::instance($horizon))
            ->get(['device_id', 'recorded_at'])
            ->map(fn (EnvironmentalReading $row): array => [
                'device_id' => $row->device_id,
                'bucket' => $row->recorded_at->copy()->startOfHour()->toDateTimeString(),
            ])
            ->unique(fn (array $row): string => $row['device_id'].'|'.$row['bucket'])
            ->values();

        foreach ($pairs as $pair) {
            $this->rebuildEnvBucket((int) $pair['device_id'], Carbon::parse($pair['bucket']));
        }

        return $pairs->count();
    }

    /**
     * @param  Enumerable<int, mixed>  $rows
     * @return array<string, float|null>
     */
    private function channelStats(Enumerable $rows, string $column, string $prefix): array
    {
        $values = $rows->pluck($column)->filter(fn (mixed $value): bool => $value !== null)->map(
            fn (mixed $value): float => (float) $value,
        );

        return [
            "{$prefix}_min" => $values->isEmpty() ? null : $values->min(),
            "{$prefix}_avg" => $values->isEmpty() ? null : $values->avg(),
            "{$prefix}_max" => $values->isEmpty() ? null : $values->max(),
        ];
    }

    /**
     * @param  Enumerable<int, EnvironmentalReading>  $rows
     * @return array<string, array{min: float, avg: float, max: float}>
     */
    private function extraStats(Enumerable $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            foreach (($row->extra ?? []) as $key => $value) {
                $values[$key][] = (float) $value;
            }
        }

        $stats = [];
        foreach ($values as $key => $list) {
            $stats[$key] = [
                'min' => min($list),
                'avg' => array_sum($list) / count($list),
                'max' => max($list),
            ];
        }

        return $stats;
    }
}
