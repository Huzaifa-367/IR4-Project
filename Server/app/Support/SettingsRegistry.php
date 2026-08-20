<?php

namespace App\Support;

/**
 * Authoritative runtime settings catalogue (DOC-18 §4).
 *
 * @phpstan-type SettingDefinition array{
 *     default: mixed,
 *     type: 'bool'|'int'|'float'|'string'|'timezone'|'time'|'enum',
 *     group: string,
 *     permission: string,
 *     requires_confirm: bool,
 *     label: string,
 *     description?: string,
 *     min?: int|float,
 *     max?: int|float,
 *     options?: list<string>,
 *     editable?: bool,
 *     unit?: string,
 *     secret?: bool
 * }
 */
final class SettingsRegistry
{
    /**
     * @return array<string, SettingDefinition>
     */
    public static function definitions(): array
    {
        return [
            'general.timezone' => [
                'default' => 'Asia/Riyadh',
                'type' => 'timezone',
                'group' => 'general',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Timezone',
                'description' => 'Display, reports, and scheduler timezone.',
            ],
            'general.locale' => [
                'default' => 'en',
                'type' => 'string',
                'group' => 'general',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Locale',
                'options' => ['en'],
            ],
            'general.theme_default' => [
                'default' => 'dark',
                'type' => 'enum',
                'group' => 'general',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Default theme',
                'options' => ['dark', 'light'],
            ],
            'general.site_latitude' => [
                'default' => '',
                'type' => 'string',
                'group' => 'general',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Site latitude',
                'description' => 'SCC coordinates for OpenWeatherMap (decimal degrees). Use Refresh location or enter manually.',
            ],
            'general.site_longitude' => [
                'default' => '',
                'type' => 'string',
                'group' => 'general',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Site longitude',
                'description' => 'SCC coordinates for OpenWeatherMap (decimal degrees). Use Refresh location or enter manually.',
            ],

            'auth.session_timeout_minutes' => [
                'default' => 15,
                'type' => 'int',
                'group' => 'auth',
                'permission' => 'update-settings',
                'requires_confirm' => true,
                'label' => 'Idle session timeout',
                'unit' => 'minutes',
                'min' => 5,
                'max' => 240,
            ],
            'auth.login_max_per_min' => [
                'default' => 5,
                'type' => 'int',
                'group' => 'auth',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Login attempts per minute',
                'min' => 1,
                'max' => 60,
            ],
            'auth.lockout_threshold' => [
                'default' => 10,
                'type' => 'int',
                'group' => 'auth',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Lockout failure threshold',
                'min' => 3,
                'max' => 50,
            ],
            'auth.lockout_minutes' => [
                'default' => 15,
                'type' => 'int',
                'group' => 'auth',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Lockout duration',
                'unit' => 'minutes',
                'min' => 1,
                'max' => 1440,
            ],
            'auth.password_min_length' => [
                'default' => 12,
                'type' => 'int',
                'group' => 'auth',
                'permission' => 'update-settings',
                'requires_confirm' => true,
                'label' => 'Minimum password length',
                'min' => 8,
                'max' => 128,
            ],
            'auth.require_2fa_for_admins' => [
                'default' => false,
                'type' => 'bool',
                'group' => 'auth',
                'permission' => 'update-settings',
                'requires_confirm' => true,
                'label' => 'Require 2FA for admins',
            ],

            'alert.audible_enabled' => [
                'default' => true,
                'type' => 'bool',
                'group' => 'alerts',
                'permission' => 'update-alert-settings',
                'requires_confirm' => false,
                'label' => 'Audible alerts enabled',
            ],
            'alert.warning_toast_seconds' => [
                'default' => 10,
                'type' => 'int',
                'group' => 'alerts',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Warning toast dismiss',
                'unit' => 'seconds',
                'min' => 3,
                'max' => 120,
            ],

            'ingest.max_batch' => [
                'default' => 1000,
                'type' => 'int',
                'group' => 'ingest',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Max ingest batch size',
                'min' => 1,
                'max' => 5000,
            ],
            'ingest.rate_per_minute' => [
                'default' => 120,
                'type' => 'int',
                'group' => 'ingest',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Per-device ingest rate',
                'unit' => 'per minute',
                'min' => 1,
                'max' => 6000,
            ],
            'ingest.future_skew_seconds' => [
                'default' => 300,
                'type' => 'int',
                'group' => 'ingest',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Future clock skew clamp',
                'unit' => 'seconds',
                'min' => 0,
                'max' => 3600,
            ],
            'ingest.backfill_after_seconds' => [
                'default' => 600,
                'type' => 'int',
                'group' => 'ingest',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Backfill classification threshold',
                'unit' => 'seconds',
                'min' => 60,
                'max' => 86400,
            ],
            'realtime.headcount_throttle_seconds' => [
                'default' => 5,
                'type' => 'int',
                'group' => 'ingest',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Headcount broadcast throttle',
                'unit' => 'seconds',
                'min' => 1,
                'max' => 60,
            ],
            'realtime.positions_throttle_seconds' => [
                'default' => 5,
                'type' => 'int',
                'group' => 'ingest',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Positions broadcast throttle',
                'unit' => 'seconds',
                'min' => 1,
                'max' => 60,
            ],
            'realtime.gas_throttle_seconds' => [
                'default' => 5,
                'type' => 'int',
                'group' => 'ingest',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Gas broadcast throttle',
                'unit' => 'seconds',
                'min' => 1,
                'max' => 60,
            ],
            'realtime.poll_fallback_seconds' => [
                'default' => 30,
                'type' => 'int',
                'group' => 'ingest',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Poll fallback interval',
                'unit' => 'seconds',
                'min' => 5,
                'max' => 300,
            ],

            'health.reader_stale_minutes' => [
                'default' => 5,
                'type' => 'int',
                'group' => 'health',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'RFID reader stale after',
                'unit' => 'minutes',
                'min' => 1,
                'max' => 360,
            ],
            'health.gas_stale_minutes' => [
                'default' => 5,
                'type' => 'int',
                'group' => 'health',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Gas detector stale after',
                'unit' => 'minutes',
                'min' => 1,
                'max' => 360,
            ],
            'health.sensor_stale_minutes' => [
                'default' => 5,
                'type' => 'int',
                'group' => 'health',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Sensor/gateway stale after',
                'unit' => 'minutes',
                'min' => 1,
                'max' => 360,
            ],
            'health.edge_stale_minutes' => [
                'default' => 3,
                'type' => 'int',
                'group' => 'health',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Edge compute stale after',
                'unit' => 'minutes',
                'min' => 1,
                'max' => 360,
            ],
            'health.camera_stale_minutes' => [
                'default' => 3,
                'type' => 'int',
                'group' => 'health',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Camera stale after',
                'unit' => 'minutes',
                'min' => 1,
                'max' => 360,
            ],
            'health.gas_offline_escalate_minutes' => [
                'default' => 30,
                'type' => 'int',
                'group' => 'health',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Gas telemetry-lost escalation',
                'unit' => 'minutes',
                'min' => 5,
                'max' => 1440,
            ],

            'tracking.gate_debounce_seconds' => [
                'default' => 60,
                'type' => 'int',
                'group' => 'tracking',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Gate debounce',
                'unit' => 'seconds',
                'min' => 0,
                'max' => 600,
            ],
            'tracking.stationary_tag_minutes' => [
                'default' => 15,
                'type' => 'int',
                'group' => 'tracking',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Stationary tag threshold',
                'unit' => 'minutes',
                'min' => 1,
                'max' => 240,
            ],
            'tracking.tag_offsite_after_hours' => [
                'default' => 14,
                'type' => 'int',
                'group' => 'tracking',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Tag offsite after',
                'unit' => 'hours',
                'min' => 1,
                'max' => 168,
            ],
            'tracking.worker_down_window_minutes' => [
                'default' => 10,
                'type' => 'int',
                'group' => 'tracking',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Worker-down correlation window',
                'unit' => 'minutes',
                'min' => 1,
                'max' => 120,
            ],
            'tracking.headcount_cache_seconds' => [
                'default' => 5,
                'type' => 'int',
                'group' => 'tracking',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Headcount cache TTL',
                'unit' => 'seconds',
                'min' => 1,
                'max' => 60,
            ],

            'gas.hysteresis_margin_pct' => [
                'default' => 5.0,
                'type' => 'float',
                'group' => 'gas',
                'permission' => 'update-gas-thresholds',
                'requires_confirm' => true,
                'label' => 'Gas hysteresis margin',
                'unit' => '%',
                'min' => 0,
                'max' => 50,
            ],

            'env.thresholds_enabled' => [
                'default' => false,
                'type' => 'bool',
                'group' => 'environment',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Environmental thresholds enabled',
                'description' => 'Reserved — v1 has no environmental alarms (DOC-12).',
                'editable' => false,
            ],

            'weather.source' => [
                'default' => 'api',
                'type' => 'enum',
                'group' => 'weather',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Weather source',
                'description' => 'Exclusive: sensor (edge environmental_sensor) or api (OpenWeatherMap). No automatic fallback.',
                'options' => ['sensor', 'api'],
            ],
            'weather.provider' => [
                'default' => 'openweathermap',
                'type' => 'enum',
                'group' => 'weather',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Weather API provider',
                'options' => ['openweathermap'],
            ],
            'weather.api_base_url' => [
                'default' => 'https://api.openweathermap.org',
                'type' => 'string',
                'group' => 'weather',
                'permission' => 'update-settings',
                'requires_confirm' => true,
                'label' => 'Weather API base URL',
                'description' => 'OpenWeatherMap host (default free API).',
            ],
            'weather.api_key' => [
                'default' => '',
                'type' => 'string',
                'group' => 'weather',
                'permission' => 'update-settings',
                'requires_confirm' => true,
                'secret' => true,
                'label' => 'OpenWeatherMap API key',
                'description' => 'Required when weather source is api. Stored in settings DB — not .env.',
            ],
            'weather.refresh_minutes' => [
                'default' => 60,
                'type' => 'int',
                'group' => 'weather',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Weather API refresh interval',
                'unit' => 'minutes',
                'min' => 5,
                'max' => 1440,
                'description' => 'How often the scheduler polls OpenWeatherMap (manual artisan always fetches).',
            ],

            'equipment.public_rate_limit_per_min' => [
                'default' => 30,
                'type' => 'int',
                'group' => 'equipment',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Public QR rate limit',
                'unit' => 'per minute',
                'min' => 1,
                'max' => 600,
            ],
            'equipment.default_is_checkoutable' => [
                'default' => false,
                'type' => 'bool',
                'group' => 'equipment',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'New equipment checkoutable by default',
            ],
            'equipment.overdue_return_alerts' => [
                'default' => false,
                'type' => 'bool',
                'group' => 'equipment',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Raise alerts for overdue returns',
                'description' => 'When off, overdue returns are flagged in UI only.',
            ],

            'report.generation_day' => [
                'default' => 'sunday',
                'type' => 'enum',
                'group' => 'reports',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Report generation day',
                'options' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            ],
            'report.generation_time' => [
                'default' => '06:00',
                'type' => 'time',
                'group' => 'reports',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Report generation time',
            ],
            'report.auto_publish' => [
                'default' => false,
                'type' => 'bool',
                'group' => 'reports',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Auto-publish weekly reports',
            ],
            'report.week_start' => [
                'default' => 'sunday',
                'type' => 'enum',
                'group' => 'reports',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Reporting week start',
                'options' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            ],
            'report.completeness_threshold_pct' => [
                'default' => 20,
                'type' => 'int',
                'group' => 'reports',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Sensor outage note threshold',
                'unit' => '%',
                'min' => 1,
                'max' => 100,
            ],

            'retention.tag_readings_days' => [
                'default' => 90,
                'type' => 'int',
                'group' => 'retention',
                'permission' => 'update-settings',
                'requires_confirm' => true,
                'label' => 'Tag readings retention',
                'unit' => 'days',
                'min' => 7,
                'max' => 730,
            ],
            'retention.sensor_readings_days' => [
                'default' => 180,
                'type' => 'int',
                'group' => 'retention',
                'permission' => 'update-settings',
                'requires_confirm' => true,
                'label' => 'Sensor readings retention',
                'unit' => 'days',
                'min' => 7,
                'max' => 1095,
            ],
            'retention.exports_days' => [
                'default' => 7,
                'type' => 'int',
                'group' => 'retention',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Ad-hoc export file retention',
                'unit' => 'days',
                'min' => 1,
                'max' => 90,
            ],
            'dashboard.cache_seconds' => [
                'default' => 8,
                'type' => 'int',
                'group' => 'general',
                'permission' => 'update-settings',
                'requires_confirm' => false,
                'label' => 'Dashboard summary cache',
                'unit' => 'seconds',
                'min' => 1,
                'max' => 120,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::definitions() as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }

        return $defaults;
    }

    /**
     * @return SettingDefinition|null
     */
    public static function get(string $key): ?array
    {
        return self::definitions()[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return isset(self::definitions()[$key]);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Module-based editor sections. Labels align with {@see PermissionCatalogue::grouped()}
     * where the setting domain matches a RBAC module.
     *
     * @return array<string, string>
     */
    public static function groupLabels(): array
    {
        return [
            'general' => 'Administration — General',
            'auth' => 'Administration — Auth & session',
            'alerts' => 'Alerts',
            'ingest' => 'Administration — Ingestion & real-time',
            'health' => 'Administration — Hardware health',
            'tracking' => 'Tracking / RFID',
            'gas' => 'Gas',
            'environment' => 'Dashboard — Environment',
            'weather' => 'Dashboard — Weather',
            'equipment' => 'Equipment / QR',
            'reports' => 'Reports',
            'retention' => 'Administration — Retention & backup',
        ];
    }

    public static function isSecret(string $key): bool
    {
        $definition = self::get($key);

        return (bool) ($definition['secret'] ?? false);
    }

    public static function secretPlaceholder(): string
    {
        return '********';
    }
}
