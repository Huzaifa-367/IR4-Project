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

];
