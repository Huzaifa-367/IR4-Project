<?php

namespace App\Services\Backup;

use InvalidArgumentException;

final class DatabaseDumperFactory
{
    public function forConnection(?string $connection = null): DatabaseDumper
    {
        $connection ??= (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        return match ($driver) {
            'sqlite' => new SqliteDumper($connection),
            'mysql', 'mariadb' => new MysqlDumper($connection),
            default => throw new InvalidArgumentException("Unsupported backup driver [{$driver}]."),
        };
    }
}
