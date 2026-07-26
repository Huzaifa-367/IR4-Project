<?php

namespace App\Services\Backup;

use InvalidArgumentException;

final class DatabaseDumperFactory
{
    public function forConnection(?string $connection = null): DatabaseDumper
    {
        $connection ??= (string) config('backup.backup_connection', config('database.default'));
        $driver = (string) config("database.connections.{$connection}.driver");

        return match ($driver) {
            'sqlite' => new SqliteDumper($connection),
            'mysql', 'mariadb' => new MysqlDumper($connection),
            default => throw new InvalidArgumentException(
                "Unsupported database driver [{$driver}]. Production supports MySQL 8 only; SQLite is for local/tests.",
            ),
        };
    }

    public function forDefaultConnection(): DatabaseDumper
    {
        return $this->forConnection((string) config('database.default'));
    }
}
