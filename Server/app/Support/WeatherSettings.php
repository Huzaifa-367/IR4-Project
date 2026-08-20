<?php

namespace App\Support;

use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\Device;
use App\Services\SettingsService;
use Illuminate\Validation\ValidationException;

/**
 * Runtime weather configuration from the settings table (DOC-12 / DOC-18).
 * Not Laravel .env — exclusive sensor|api source for environmental live/trends/reports.
 */
final class WeatherSettings
{
    public const string SOURCE_SENSOR = 'sensor';

    public const string SOURCE_API = 'api';

    public const string DEVICE_REFERENCE = 'SYS-WEATHER-API';

    public const int REFRESH_MINUTES_MIN = 5;

    public const int REFRESH_MINUTES_MAX = 1440;

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function source(): string
    {
        $source = (string) $this->settings->get('weather.source', self::SOURCE_API);

        return in_array($source, [self::SOURCE_SENSOR, self::SOURCE_API], true)
            ? $source
            : self::SOURCE_API;
    }

    public function usesApi(): bool
    {
        return $this->source() === self::SOURCE_API;
    }

    public function usesSensor(): bool
    {
        return $this->source() === self::SOURCE_SENSOR;
    }

    public function latitude(): ?float
    {
        $raw = trim((string) $this->settings->get('general.site_latitude', ''));

        return $raw !== '' && is_numeric($raw) ? (float) $raw : null;
    }

    public function longitude(): ?float
    {
        $raw = trim((string) $this->settings->get('general.site_longitude', ''));

        return $raw !== '' && is_numeric($raw) ? (float) $raw : null;
    }

    public function apiKey(): string
    {
        return trim((string) $this->settings->get('weather.api_key', ''));
    }

    public function apiBaseUrl(): string
    {
        $base = trim((string) $this->settings->get('weather.api_base_url', 'https://api.openweathermap.org'));

        return $base !== '' ? rtrim($base, '/') : 'https://api.openweathermap.org';
    }

    public function refreshMinutes(): int
    {
        $minutes = (int) $this->settings->get('weather.refresh_minutes', 60);

        return max(self::REFRESH_MINUTES_MIN, min(self::REFRESH_MINUTES_MAX, $minutes));
    }

    public function staleMinutes(): int
    {
        return $this->refreshMinutes() * 2;
    }

    /**
     * @throws ValidationException
     */
    public function assertApiConfigComplete(): void
    {
        if (! $this->usesApi()) {
            return;
        }

        $errors = [];
        $lat = $this->latitude();
        $lon = $this->longitude();

        if ($lat === null || $lat < -90.0 || $lat > 90.0) {
            $errors['general.site_latitude'] = 'Site latitude is required for weather API (−90 to 90).';
        }
        if ($lon === null || $lon < -180.0 || $lon > 180.0) {
            $errors['general.site_longitude'] = 'Site longitude is required for weather API (−180 to 180).';
        }
        if ($this->apiKey() === '') {
            $errors['weather.api_key'] = 'OpenWeatherMap API key is required when weather source is api.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function systemDevice(): Device
    {
        return Device::query()->firstOrCreate(
            ['reference' => self::DEVICE_REFERENCE],
            [
                'asset_id' => null,
                'name' => 'OpenWeatherMap',
                'device_type' => DeviceType::EnvironmentalSensor,
                'status' => HardwareStatus::Online,
                'api_token_hash' => null,
                'token_issued_at' => null,
                'config' => ['system' => true, 'provider' => 'openweathermap'],
                'last_seen_at' => null,
            ],
        );
    }

    public function systemDeviceId(): ?int
    {
        $id = Device::query()->where('reference', self::DEVICE_REFERENCE)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
