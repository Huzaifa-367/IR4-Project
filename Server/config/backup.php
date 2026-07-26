<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup & handover (DOC-19) — deploy-fixed, not runtime settings
    |--------------------------------------------------------------------------
    |
    | Production DB is MySQL 8 only. SQLite remains available for local/tests.
    |
    */

    'disk' => env('BACKUP_DISK', 'backups'),
    'exports_disk' => env('EXPORT_DISK', 'exports'),

    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('BACKUP_MYSQL_PATH', 'mysql'),
    'sqlite_path' => env('BACKUP_SQLITE_PATH', 'sqlite3'),

    'disk_space_warn_pct' => (int) env('DISK_SPACE_WARN_PCT', 15),

    /*
    | wipe modes: crypto_erase (default) | overwrite
    */
    'wipe_mode' => env('IR4_WIPE_MODE', 'crypto_erase'),

    /*
    | Connection names (config/database.php):
    | - backup_connection: read-only dump account
    | - restore_connection: staging restore target (never the live DB)
    | - wipe_connection: privileged maintenance account for secure-wipe
    */
    'backup_connection' => env('IR4_BACKUP_CONNECTION', 'ir4_backup'),
    'restore_connection' => env('IR4_RESTORE_CONNECTION', 'ir4_restore'),
    'wipe_connection' => env('IR4_WIPE_CONNECTION', 'ir4_wipe'),

    'missing_backup_hours' => 36,
];
