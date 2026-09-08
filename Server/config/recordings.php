<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recordings archive root (DOC-24)
    |--------------------------------------------------------------------------
    |
    | Absolute path on the SCC (separate drive). Deploy config — not SettingsRegistry.
    | Must be mounted read-only into the PHP container so list/realpath match nginx.
    |
    */

    'root' => env('RECORDINGS_ROOT', '/data2/video'),

    /*
    |--------------------------------------------------------------------------
    | X-Accel-Redirect (production streaming)
    |--------------------------------------------------------------------------
    |
    | When true, the stream route authorizes then returns X-Accel-Redirect so
    | nginx serves Range bytes from disk. Set false for local/dev without the
    | internal location (BinaryFileResponse fallback).
    |
    */

    'use_x_accel' => filter_var(env('RECORDINGS_X_ACCEL', true), FILTER_VALIDATE_BOOLEAN),

    'x_accel_prefix' => env('RECORDINGS_X_ACCEL_PREFIX', '/internal-recordings/'),

    'allowed_extensions' => [
        'mp4',
        'm4v',
        'webm',
        'mkv',
        'mov',
        'ts',
    ],

    'max_list_entries' => (int) env('RECORDINGS_MAX_LIST', 2000),

];
