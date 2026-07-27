<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class MysqlDumper implements DatabaseDumper
{
    public function __construct(
        private readonly string $connection,
    ) {}

    public function dumpTo(string $absolutePath): void
    {
        $config = config("database.connections.{$this->connection}");
        $binary = (string) config('backup.mysqldump_path', 'mysqldump');

        $result = Process::timeout(3600)->run([
            $binary,
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '3306'),
            '--user='.(string) ($config['username'] ?? ''),
            '--password='.(string) ($config['password'] ?? ''),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--result-file='.$absolutePath,
            (string) ($config['database'] ?? ''),
        ]);

        if (! $result->successful() || ! is_file($absolutePath)) {
            throw new RuntimeException('mysqldump failed: '.$result->errorOutput());
        }
    }

    public function restoreFrom(string $absolutePath, string $connectionName): void
    {
        $config = config("database.connections.{$connectionName}");
        $binary = (string) config('backup.mysql_path', 'mysql');

        $result = Process::timeout(3600)
            ->input((string) file_get_contents($absolutePath))
            ->run([
                $binary,
                '--host='.(string) ($config['host'] ?? '127.0.0.1'),
                '--port='.(string) ($config['port'] ?? '3306'),
                '--user='.(string) ($config['username'] ?? ''),
                '--password='.(string) ($config['password'] ?? ''),
                (string) ($config['database'] ?? ''),
            ]);

        if (! $result->successful()) {
            throw new RuntimeException('mysql restore failed: '.$result->errorOutput());
        }
    }
}
