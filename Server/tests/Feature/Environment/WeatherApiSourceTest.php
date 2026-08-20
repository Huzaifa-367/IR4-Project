<?php

use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\Device;
use App\Models\EnvironmentalReading;
use App\Models\User;
use App\Services\EnvironmentalDataService;
use App\Services\SettingsService;
use App\Support\WeatherSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    $settings = app(SettingsService::class);
    $settings->set('weather.source', 'api', confirmed: true);
    $settings->set('general.site_latitude', '24.713600');
    $settings->set('general.site_longitude', '46.675300');
    $settings->set('weather.api_key', 'test-owm-key', confirmed: true);
});

it('maps openweathermap current weather into an environmental reading', function () {
    Http::fake([
        'api.openweathermap.org/data/2.5/weather*' => Http::response([
            'coord' => ['lon' => 46.6753, 'lat' => 24.7136],
            'weather' => [['id' => 800, 'main' => 'Clear', 'description' => 'clear sky', 'icon' => '01d']],
            'main' => [
                'temp' => 36.5,
                'feels_like' => 35.0,
                'pressure' => 1009,
                'humidity' => 12,
            ],
            'visibility' => 10000,
            'wind' => ['speed' => 3.2, 'deg' => 270],
            'clouds' => ['all' => 5],
            'dt' => 1_700_000_000,
            'name' => 'Riyadh',
            'cod' => 200,
        ], 200),
    ]);

    $this->artisan('ir4:fetch-weather-api')->assertSuccessful();

    $device = Device::query()->where('reference', WeatherSettings::DEVICE_REFERENCE)->first();
    expect($device)->not->toBeNull()
        ->and($device->device_type)->toBe(DeviceType::EnvironmentalSensor);

    $reading = EnvironmentalReading::query()->where('device_id', $device->id)->first();
    expect($reading)->not->toBeNull()
        ->and((float) $reading->temperature_c)->toBe(36.5)
        ->and((float) $reading->humidity_pct)->toBe(12.0)
        ->and((float) $reading->wind_speed_ms)->toBe(3.2)
        ->and($reading->extra)->toHaveKey('pressure_hpa')
        ->and($reading->extra)->toHaveKey('weather_code');

    $live = app(EnvironmentalDataService::class)->latest();
    expect($live)->toHaveCount(1)
        ->and($live[0]['temperature_c'])->toBe(36.5)
        ->and($live[0]['weather_source'] ?? null)->toBe(WeatherSettings::SOURCE_API);
});

it('skips the weather fetch when source is sensor', function () {
    app(SettingsService::class)->set('weather.source', 'sensor');
    Http::fake();

    $this->artisan('ir4:fetch-weather-api')->assertSuccessful();

    Http::assertNothingSent();
    expect(Device::query()->where('reference', WeatherSettings::DEVICE_REFERENCE)->exists())->toBeFalse();
});

it('keeps last api reading when openweathermap is unreachable', function () {
    $device = app(WeatherSettings::class)->systemDevice();
    EnvironmentalReading::query()->create([
        'device_id' => $device->id,
        'asset_id' => null,
        'recorded_at' => now()->subHour(),
        'received_at' => now()->subHour(),
        'temperature_c' => 33.0,
        'humidity_pct' => 20.0,
        'wind_speed_ms' => 1.5,
        'extra' => null,
        'is_backfill' => false,
        'clock_skew' => false,
        'event_uid' => 'owm:seed',
    ]);

    Http::fake([
        'api.openweathermap.org/*' => Http::response(['cod' => 401, 'message' => 'Invalid'], 401),
    ]);

    $this->artisan('ir4:fetch-weather-api')->assertSuccessful();

    $live = app(EnvironmentalDataService::class)->latest();
    expect($live)->toHaveCount(1)
        ->and($live[0]['temperature_c'])->toBe(33.0);

    $field = Device::factory()->create([
        'device_type' => DeviceType::EnvironmentalSensor,
        'status' => HardwareStatus::Online,
        'name' => 'Field Env',
        'reference' => 'DEV-ENV-FIELD',
    ]);
    EnvironmentalReading::query()->create([
        'device_id' => $field->id,
        'asset_id' => $field->asset_id,
        'recorded_at' => now(),
        'received_at' => now(),
        'temperature_c' => 99.0,
        'humidity_pct' => 50.0,
        'wind_speed_ms' => 9.0,
        'extra' => null,
        'is_backfill' => false,
        'clock_skew' => false,
        'event_uid' => 'field:1',
    ]);

    $live = app(EnvironmentalDataService::class)->latest();
    expect($live)->toHaveCount(1)
        ->and($live[0]['temperature_c'])->toBe(33.0)
        ->and($live[0]['device_ref'])->toBe(WeatherSettings::DEVICE_REFERENCE);
});

it('does not call openweathermap when live is read in sensor mode', function () {
    app(SettingsService::class)->set('weather.source', 'sensor');
    Http::fake();

    $device = Device::factory()->create([
        'device_type' => DeviceType::EnvironmentalSensor,
        'status' => HardwareStatus::Online,
    ]);
    EnvironmentalReading::query()->create([
        'device_id' => $device->id,
        'asset_id' => $device->asset_id,
        'recorded_at' => now(),
        'received_at' => now(),
        'temperature_c' => 28.0,
        'humidity_pct' => 40.0,
        'wind_speed_ms' => 2.0,
        'extra' => null,
        'is_backfill' => false,
        'clock_skew' => false,
        'event_uid' => 'sensor:1',
    ]);

    $live = app(EnvironmentalDataService::class)->latest();
    expect($live)->toHaveCount(1)
        ->and($live[0]['temperature_c'])->toBe(28.0);

    Http::assertNothingSent();
});

it('rejects enabling weather api without coordinates and key', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    $settings = app(SettingsService::class);
    $settings->set('weather.api_key', '', $admin, confirmed: true);
    $settings->set('general.site_latitude', '');
    $settings->set('general.site_longitude', '');

    $this->actingAs($admin)
        ->put(route('settings.general.update'), [
            'settings' => [
                'weather.source' => 'api',
            ],
            'confirmed' => [],
        ])
        ->assertSessionHasErrors(['general.site_latitude', 'weather.api_key']);
});

it('masks weather api key in the settings editor payload', function () {
    $admin = User::factory()->withRole('Super Admin')->create();
    app(SettingsService::class)->set('weather.api_key', 'super-secret-key', $admin, confirmed: true);

    $this->actingAs($admin)
        ->get(route('settings.general.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('groups')
            ->where('groups', function ($groups) {
                foreach ($groups as $group) {
                    foreach ($group['settings'] as $setting) {
                        if ($setting['key'] === 'weather.api_key') {
                            return $setting['value'] === '********'
                                && ($setting['secret'] ?? false) === true;
                        }
                    }
                }

                return false;
            }));
});

it('stores a new reading on every successful command call', function () {
    Http::fake([
        'api.openweathermap.org/data/2.5/weather*' => Http::response([
            'main' => ['temp' => 30.0, 'humidity' => 10],
            'wind' => ['speed' => 1.0],
            'dt' => time(),
            'cod' => 200,
        ], 200),
    ]);

    $this->artisan('ir4:fetch-weather-api')->assertSuccessful();
    $this->artisan('ir4:fetch-weather-api')->assertSuccessful();

    Http::assertSentCount(2);
    expect(EnvironmentalReading::query()->count())->toBe(2);
});
