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

    /** Shared app-volume handoff paths: Lerd and host PHP can both access these. */
    'staging_root' => env('BACKUP_STAGING_ROOT') ?: storage_path('app/backup-staging'),
    'restore_inbox' => env('BACKUP_RESTORE_INBOX') ?: storage_path('app/restore-inbox'),

    /** Filesystem-only publisher fallback; normal value is recorded from SettingsService. */
    'keep_count' => (int) env('BACKUP_KEEP_COUNT', 30),

    /** Absolute Laravel app root packed into server/. Defaults to base_path(). */
    'app_root' => env('BACKUP_APP_ROOT') ?: null,

    /** Directory names skipped while packing server/. */
    'exclude_directories' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'BACKUP_EXCLUDE_DIRECTORIES',
            'node_modules,.git,backup-staging,restore-inbox,tmp',
        )),
    ))),

    'disk_space_warn_pct' => (int) env('DISK_SPACE_WARN_PCT', 15),

    /** Restore target schema on the same MySQL connection; never the live DB by default. */
    'restore_database' => env('IR4_RESTORE_DATABASE', 'ir4_restore'),

    /** Hours without a successful zip before raising backup:missing. */
    'missing_backup_hours' => 36,
];
