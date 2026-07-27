<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site backup (DOC-19) — deploy-fixed paths
    |--------------------------------------------------------------------------
    |
    | On-prem: app lives on /data2; daily full live snapshots go to /data
    | (BACKUP_DISK_ROOT). Each zip contains server/ + DB/ + manifest.json.
    | Encryption at rest is via LUKS on the backup volume (DOC-20).
    |
    */

    'disk' => env('BACKUP_DISK', 'backups'),

    /**
     * Absolute backup volume root (DOC-19/20).
     * On-prem default is /data/ir4-backups — never the app disk under /data2.
     * Override in .env for local machines without /data.
     */
    'disk_root' => env('BACKUP_DISK_ROOT') ?: '/data/ir4-backups',

    /** Absolute Laravel app root packed into server/. Defaults to base_path(). */
    'app_root' => env('BACKUP_APP_ROOT') ?: null,

    /** Directory names skipped while packing server/. */
    'exclude_directories' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BACKUP_EXCLUDE_DIRECTORIES', 'node_modules,.git')),
    ))),

    /** Prefer Oracle MySQL client paths on R360; MariaDB shims often fail on caching_sha2_password. */
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('BACKUP_MYSQL_PATH', 'mysql'),

    /** Used only when the default DB driver is sqlite (local/tests). Production is MySQL. */
    'sqlite_path' => env('BACKUP_SQLITE_PATH', 'sqlite3'),

    'disk_space_warn_pct' => (int) env('DISK_SPACE_WARN_PCT', 15),

    /** Staging connection for ir4:restore (never the live DB by default). */
    'restore_connection' => env('IR4_RESTORE_CONNECTION', 'ir4_restore'),

    /** Hours without a successful zip before raising backup:missing. */
    'missing_backup_hours' => 36,
];
