<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SqliteDumper implements DatabaseDumper
{
    public function __construct(
        private readonly string $connection,
    ) {}

    public function dumpTo(string $absolutePath): void
    {
        $pdo = DB::connection($this->connection)->getPdo();
        $statements = [];
        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $quoted = '"'.str_replace('"', '""', (string) $table).'"';
            $create = $pdo->query(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name=".$pdo->quote((string) $table),
            )->fetchColumn();
            if (is_string($create) && $create !== '') {
                $statements[] = $create.';';
            }

            $rows = $pdo->query('SELECT * FROM '.$quoted);
            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $cols = array_map(
                    fn ($c) => '"'.str_replace('"', '""', (string) $c).'"',
                    array_keys($row),
                );
                $vals = array_map(
                    fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    array_values($row),
                );
                $statements[] = 'INSERT INTO '.$quoted.' ('.implode(', ', $cols).') VALUES ('.implode(', ', $vals).');';
            }
        }

        if (file_put_contents($absolutePath, implode("\n", $statements)."\n") === false) {
            throw new RuntimeException('Unable to write SQLite dump.');
        }
    }

    public function restoreFrom(string $absolutePath, string $connectionName): void
    {
        $database = (string) config("database.connections.{$connectionName}.database");
        $sql = (string) file_get_contents($absolutePath);

        if ($database !== ':memory:') {
            $dir = dirname($database);
            if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                throw new RuntimeException("Unable to create {$dir}");
            }
            if (is_file($database)) {
                unlink($database);
            }
            if (! touch($database)) {
                throw new RuntimeException('Unable to create SQLite target.');
            }
        }

        DB::connection($connectionName)->unprepared($sql);
    }
}
