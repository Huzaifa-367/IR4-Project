<?php

namespace App\Services\Environment;

use App\Enums\HardwareStatus;
use App\Models\EnvironmentalReading;
use App\Support\WeatherSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Path ② weather ingest: OpenWeatherMap Current Weather → environmental_readings.
 * Each successful API call writes one DB row. No cache, no interval skip.
 */
final class WeatherIngestService
{
    public function __construct(
        private readonly WeatherSettings $weather,
        private readonly OpenWeatherMapWeatherClient $client,
    ) {}

    /**
     * @return array{status: 'skipped'|'stored'|'failed', detail?: string}
     */
    public function fetchSnapshot(): array
    {
        if (! $this->weather->usesApi()) {
            return ['status' => 'skipped', 'detail' => 'weather.source is not api'];
        }

        $lat = $this->weather->latitude();
        $lon = $this->weather->longitude();
        $apiKey = $this->weather->apiKey();

        if ($lat === null || $lon === null || $apiKey === '') {
            Log::warning('weather.api.skip_incomplete_settings');

            return ['status' => 'skipped', 'detail' => 'missing lat/lon/api key'];
        }

        $result = $this->client->fetchCurrent(
            $lat,
            $lon,
            $apiKey,
            $this->weather->apiBaseUrl(),
        );

        if (! ($result['ok'] ?? false)) {
            return ['status' => 'failed', 'detail' => (string) ($result['error'] ?? 'unknown')];
        }

        $device = $this->weather->systemDevice();
        $receivedAt = now();
        $extra = $result['extra'];
        $extra['owm_dt'] = (int) $result['recorded_at_unix'];

        EnvironmentalReading::query()->create([
            'device_id' => $device->id,
            'asset_id' => $device->asset_id,
            // Poll time — OWM Current Weather `dt` can stay unchanged across calls.
            'recorded_at' => $receivedAt,
            'received_at' => $receivedAt,
            'temperature_c' => $result['temperature_c'],
            'humidity_pct' => $result['humidity_pct'],
            'wind_speed_ms' => $result['wind_speed_ms'],
            'extra' => $extra !== [] ? $extra : null,
            'is_backfill' => false,
            'clock_skew' => false,
            'event_uid' => (string) Str::uuid(),
        ]);

        $device->forceFill([
            'last_seen_at' => $receivedAt,
            'status' => HardwareStatus::Online,
        ])->save();

        return ['status' => 'stored', 'detail' => $receivedAt->toIso8601String()];
    }
}
