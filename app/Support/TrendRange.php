<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class TrendRange
{
    /**
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    public static function resolve(Request $request, string $default = 'day'): array
    {
        $range = $request->string('range', $default)->toString();
        $now = now();

        [$from, $to] = match ($range) {
            'week' => [$now->copy()->subDays(7), $now],
            'custom' => [
                Carbon::parse($request->string('from')->toString())->startOfDay(),
                Carbon::parse($request->string('to')->toString())->endOfDay(),
            ],
            default => [$now->copy()->subDay(), $now],
        };

        return [$range, $from, $to];
    }

    /**
     * Dashboard Control Room window: today / yesterday / 7d / custom.
     *
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    public static function resolveDashboard(Request $request, string $default = 'today'): array
    {
        $range = $request->string('range', $default)->toString();

        if (! in_array($range, ['today', 'yesterday', 'week', 'custom'], true)) {
            $range = $default;
        }

        $now = now();

        [$from, $to] = match ($range) {
            'yesterday' => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],
            'week' => [$now->copy()->subDays(7), $now],
            'custom' => [
                Carbon::parse($request->string('from', $now->toDateString())->toString())->startOfDay(),
                Carbon::parse($request->string('to', $now->toDateString())->toString())->endOfDay(),
            ],
            default => [$now->copy()->startOfDay(), $now],
        };

        return [$range, $from, $to];
    }
}
