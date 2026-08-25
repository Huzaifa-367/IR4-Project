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
