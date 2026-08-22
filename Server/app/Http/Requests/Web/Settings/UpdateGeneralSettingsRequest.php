<?php

namespace App\Http\Requests\Web\Settings;

use App\Services\Settings\SettingsService;
use App\Support\SettingsRegistry;
use App\Support\WeatherSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->can('update-settings')
            || $user->can('update-alert-settings')
            || $user->can('update-gas-thresholds')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
            'confirmed' => ['sometimes', 'array'],
            'confirmed.*' => ['string', 'in:'.implode(',', SettingsRegistry::keys())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<string, mixed> $values */
            $values = $this->input('settings', []);
            $weatherKeys = [
                'weather.source',
                'weather.api_key',
                'weather.api_base_url',
                'general.site_latitude',
                'general.site_longitude',
            ];
            if (array_intersect(array_keys($values), $weatherKeys) === []) {
                return;
            }

            // Preview merged settings for validation (secrets already applied later by SettingsService).
            $weather = app(WeatherSettings::class);
            $settings = app(SettingsService::class);
            $pendingSource = array_key_exists('weather.source', $values)
                ? (string) $values['weather.source']
                : $weather->source();

            if ($pendingSource !== WeatherSettings::SOURCE_API) {
                return;
            }

            $lat = array_key_exists('general.site_latitude', $values)
                ? trim((string) $values['general.site_latitude'])
                : trim((string) $settings->get('general.site_latitude', ''));
            $lon = array_key_exists('general.site_longitude', $values)
                ? trim((string) $values['general.site_longitude'])
                : trim((string) $settings->get('general.site_longitude', ''));
            $key = array_key_exists('weather.api_key', $values)
                ? trim((string) $values['weather.api_key'])
                : $weather->apiKey();

            if ($key === SettingsRegistry::secretPlaceholder()) {
                $key = $weather->apiKey();
            }

            if ($lat === '' || ! is_numeric($lat) || (float) $lat < -90.0 || (float) $lat > 90.0) {
                $validator->errors()->add('general.site_latitude', 'Site latitude is required for weather API (−90 to 90).');
            }
            if ($lon === '' || ! is_numeric($lon) || (float) $lon < -180.0 || (float) $lon > 180.0) {
                $validator->errors()->add('general.site_longitude', 'Site longitude is required for weather API (−180 to 180).');
            }
            if ($key === '') {
                $validator->errors()->add('weather.api_key', 'OpenWeatherMap API key is required when weather source is api.');
            }
        });
    }
}
