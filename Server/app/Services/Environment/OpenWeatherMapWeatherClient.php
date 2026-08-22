<?php

namespace App\Services\Environment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenWeatherMap Free Current Weather API (data/2.5/weather).
 *
 * Useful fields (units=metric): main.temp (°C), main.humidity (%), wind.speed (m/s),
 * plus main.feels_like, main.pressure, wind.deg, clouds.all, visibility, weather[].
 */
final class OpenWeatherMapWeatherClient
{
    /**
     * @return array{
     *     ok: true,
     *     recorded_at_unix: int,
     *     temperature_c: float|null,
     *     humidity_pct: float|null,
     *     wind_speed_ms: float|null,
     *     extra: array<string, float|int>
     * }|array{ok: false, error: string}
     */
    public function fetchCurrent(
        float $latitude,
        float $longitude,
        string $apiKey,
        string $baseUrl = 'https://api.openweathermap.org',
    ): array {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl.'/data/2.5/weather';

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->get($url, [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'units' => 'metric',
                    'appid' => $apiKey,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('openweathermap.connection_failed', [
                'message' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'connection_failed'];
        }

        if (! $response->successful()) {
            Log::warning('openweathermap.http_error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['ok' => false, 'error' => 'http_'.$response->status()];
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $main = is_array($payload['main'] ?? null) ? $payload['main'] : [];
        $wind = is_array($payload['wind'] ?? null) ? $payload['wind'] : [];
        $clouds = is_array($payload['clouds'] ?? null) ? $payload['clouds'] : [];
        $weather = is_array($payload['weather'][0] ?? null) ? $payload['weather'][0] : [];

        $extra = [];
        if (isset($main['feels_like']) && is_numeric($main['feels_like'])) {
            $extra['feels_like_c'] = (float) $main['feels_like'];
        }
        if (isset($main['pressure']) && is_numeric($main['pressure'])) {
            $extra['pressure_hpa'] = (float) $main['pressure'];
        }
        if (isset($wind['deg']) && is_numeric($wind['deg'])) {
            $extra['wind_deg'] = (float) $wind['deg'];
        }
        if (isset($wind['gust']) && is_numeric($wind['gust'])) {
            $extra['wind_gust_ms'] = (float) $wind['gust'];
        }
        if (isset($clouds['all']) && is_numeric($clouds['all'])) {
            $extra['clouds_pct'] = (float) $clouds['all'];
        }
        if (isset($payload['visibility']) && is_numeric($payload['visibility'])) {
            $extra['visibility_m'] = (float) $payload['visibility'];
        }
        if (isset($weather['id']) && is_numeric($weather['id'])) {
            $extra['weather_code'] = (int) $weather['id'];
        }

        $dt = isset($payload['dt']) && is_numeric($payload['dt'])
            ? (int) $payload['dt']
            : time();

        return [
            'ok' => true,
            'recorded_at_unix' => $dt,
            'temperature_c' => isset($main['temp']) && is_numeric($main['temp']) ? (float) $main['temp'] : null,
            'humidity_pct' => isset($main['humidity']) && is_numeric($main['humidity']) ? (float) $main['humidity'] : null,
            'wind_speed_ms' => isset($wind['speed']) && is_numeric($wind['speed']) ? (float) $wind['speed'] : null,
            'extra' => $extra,
        ];
    }
}
