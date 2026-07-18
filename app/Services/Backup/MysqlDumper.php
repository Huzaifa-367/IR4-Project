<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class MysqlDumper implements DatabaseDumper
{
    public function __construct(
        private readonly string $connection = 'mysql',
    ) {}

    public function dumpTo(string $absolutePath): void
    {
        $config = config("database.connections.{$this->connection}");
        $binary = (string) config('backup.mysqldump_path', 'mysqldump');
        $result = Process::env([
            'MYSQL_PWD' => (string) ($config['password'] ?? ''),
        ])->timeout(600)->run([
            $binary,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--hex-blob',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-P', (string) ($config['port'] ?? '3306'),
            '-u', (string) ($config['username'] ?? 'root'),
            (string) ($config['database'] ?? ''),
            '--result-file='.$absolutePath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('mysqldump failed: '.$result->errorOutput());
        }
    }

    public function restoreFrom(string $absolutePath, string $connectionName): void
    {
        $config = config("database.connections.{$connectionName}");
        $binary = (string) config('backup.mysql_path', 'mysql');
        $result = Process::env([
            'MYSQL_PWD' => (string) ($config['password'] ?? ''),
        ])->timeout(600)->input((string) file_get_contents($absolutePath))->run([
            $binary,
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-P', (string) ($config['port'] ?? '3306'),
            '-u', (string) ($config['username'] ?? 'root'),
            (string) ($config['database'] ?? ''),
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('mysql restore failed: '.$result->errorOutput());
        }
    }

    public function driver(): string
    {
        return 'mysql';
    }
}
