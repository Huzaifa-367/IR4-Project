<?php

use App\Support\SettingsRegistry;

return [

    /*
    |--------------------------------------------------------------------------
    | Runtime settings defaults (DOC-18)
    |--------------------------------------------------------------------------
    |
    | Authoritative catalogue: App\Support\SettingsRegistry.
    | SettingsSeeder writes missing keys only; SettingsService falls back here.
    |
    */

    'settings' => SettingsRegistry::defaults(),

    /*
    |--------------------------------------------------------------------------
    | PPE ingest controls
    |--------------------------------------------------------------------------
    |
    | Disabled event types are acknowledged and discarded before any camera
    | lookup, snapshot storage, database write, or alert creation. This keeps
    | the EdgeCompute outage buffer from retrying intentionally ignored data.
    |
    */

    'ppe_ingest' => [
        'enabled' => filter_var(env('IR4_PPE_INGEST_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'types' => [
            'missing_helmet' => filter_var(env('IR4_PPE_INGEST_MISSING_HELMET', true), FILTER_VALIDATE_BOOLEAN),
            'missing_vest' => filter_var(env('IR4_PPE_INGEST_MISSING_VEST', true), FILTER_VALIDATE_BOOLEAN),
            'missing_harness' => filter_var(env('IR4_PPE_INGEST_MISSING_HARNESS', true), FILTER_VALIDATE_BOOLEAN),
            'missing_mask' => filter_var(env('IR4_PPE_INGEST_MISSING_MASK', true), FILTER_VALIDATE_BOOLEAN),
            'fall' => filter_var(env('IR4_PPE_INGEST_FALL', true), FILTER_VALIDATE_BOOLEAN),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deploy-fixed equipment printer (DOC-18 / DOC-20)
    |--------------------------------------------------------------------------
    */

    'equipment' => [
        'printer_host' => env('EQUIPMENT_PRINTER_HOST', ''),
        'printer_port' => (int) env('EQUIPMENT_PRINTER_PORT', 9100),
    ],

    'infrastructure' => [
        'disk_space_warn_pct' => (int) env('DISK_SPACE_WARN_PCT', 15),
        // Tech-team SMTP only (cameras / servers / backups / disk). Not operator alerts.
        'tech_mail_to' => array_values(array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(',', (string) env('MAIL_TECH_TO', '')),
        ))),
    ],

];
