<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cross-driver SQL expressions for truncating timestamps to hour/day buckets.
 * Used for on-the-fly aggregates over raw sensor rows (no materialised rollups).
 */
final class SqlTimeBucket
{
    public static function hour(string $column = 'recorded_at'): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d %H:00:00', {$column})",
            'pgsql' => "date_trunc('hour', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d %H:00:00')",
        };
    }

    public static function day(string $column = 'recorded_at'): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
            'pgsql' => "(date_trunc('day', {$column}))::date",
            default => "DATE({$column})",
        };
    }
}
