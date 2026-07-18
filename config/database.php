<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'ir4'),
            'username' => env('DB_USERNAME', 'ir4_app'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        /*
        | Read-only dump account for daily encrypted backups (DOC-19/20).
        | In local/tests the driver follows DB_CONNECTION (often sqlite).
        */
        'ir4_backup' => [
            'driver' => env('IR4_BACKUP_DB_DRIVER', env('DB_CONNECTION', 'sqlite')),
            'url' => env('IR4_BACKUP_DB_URL'),
            'host' => env('IR4_BACKUP_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('IR4_BACKUP_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('IR4_BACKUP_DB_DATABASE', env('DB_DATABASE', database_path('database.sqlite'))),
            'username' => env('IR4_BACKUP_DB_USERNAME', env('DB_USERNAME', 'ir4_backup')),
            'password' => env('IR4_BACKUP_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        /*
        | Staging connection for ir4:restore drills (DOC-19). Never point this at
        | the live database name in production.
        */
        'ir4_restore' => [
            'driver' => env('IR4_RESTORE_DB_DRIVER', env('DB_CONNECTION', 'sqlite')),
            'url' => env('IR4_RESTORE_DB_URL'),
            'host' => env('IR4_RESTORE_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('IR4_RESTORE_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('IR4_RESTORE_DB_DATABASE', database_path('ir4_restore.sqlite')),
            'username' => env('IR4_RESTORE_DB_USERNAME', 'ir4_restore'),
            'password' => env('IR4_RESTORE_DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        /*
        | Privileged maintenance account for ir4:secure-wipe (DOC-19/20).
        | Must be able to DELETE from audit_logs; the app user must not.
        */
        'ir4_wipe' => [
            'driver' => env('IR4_WIPE_DB_DRIVER', env('DB_CONNECTION', 'sqlite')),
            'url' => env('IR4_WIPE_DB_URL'),
            'host' => env('IR4_WIPE_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('IR4_WIPE_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('IR4_WIPE_DB_DATABASE', env('DB_DATABASE', database_path('database.sqlite'))),
            'username' => env('IR4_WIPE_DB_USERNAME', 'ir4_wipe'),
            'password' => env('IR4_WIPE_DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
