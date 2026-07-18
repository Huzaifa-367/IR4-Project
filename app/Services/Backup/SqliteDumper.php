<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SqliteDumper implements DatabaseDumper
{
    public function __construct(
        private readonly string $connection = 'sqlite',
    ) {}

    public function dumpTo(string $absolutePath): void
    {
        $database = (string) config("database.connections.{$this->connection}.database");
        if ($database === '' || $database === ':memory:') {
            $this->dumpViaSql($absolutePath);

            return;
        }

        if (! is_file($database)) {
            throw new RuntimeException("SQLite database not found at {$database}");
        }

        if (! copy($database, $absolutePath)) {
            throw new RuntimeException("Failed to copy SQLite database to {$absolutePath}");
        }
    }

    public function restoreFrom(string $absolutePath, string $connectionName): void
    {
        $database = (string) config("database.connections.{$connectionName}.database");
        if ($database === '' || $database === ':memory:') {
            $sql = file_get_contents($absolutePath);
            if ($sql === false) {
                throw new RuntimeException("Unable to read dump {$absolutePath}");
            }
            $this->runSql($connectionName, $sql);

            return;
        }

        $dir = dirname($database);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Unable to create directory {$dir}");
        }

        // File dump (sqlite binary) vs SQL dump.
        $header = (string) file_get_contents($absolutePath, false, null, 0, 16);
        if (str_starts_with($header, 'SQLite format 3')) {
            if (! copy($absolutePath, $database)) {
                throw new RuntimeException('Failed to restore SQLite file.');
            }

            return;
        }

        $sql = file_get_contents($absolutePath);
        if ($sql === false) {
            throw new RuntimeException("Unable to read dump {$absolutePath}");
        }
        if (is_file($database)) {
            unlink($database);
        }
        touch($database);
        $this->runSql($connectionName, $sql);
    }

    private function runSql(string $connectionName, string $sql): void
    {
        $pdo = DB::connection($connectionName)->getPdo();
        if ($pdo->exec($sql) === false) {
            throw new RuntimeException('SQLite restore failed.');
        }
    }

    public function driver(): string
    {
        return 'sqlite';
    }

    private function dumpViaSql(string $absolutePath): void
    {
        $pdo = DB::connection($this->connection)->getPdo();
        $tables = DB::connection($this->connection)->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
        );

        $sql = "PRAGMA foreign_keys=OFF;\nBEGIN;\n";
        foreach ($tables as $table) {
            $name = $table->name;
            $create = DB::connection($this->connection)->selectOne(
                'SELECT sql FROM sqlite_master WHERE type = ? AND name = ?',
                ['table', $name],
            );
            if ($create?->sql) {
                $sql .= $create->sql.";\n";
            }

            $rows = DB::connection($this->connection)->table($name)->get();
            foreach ($rows as $row) {
                $values = array_map(function (mixed $value) use ($pdo): string {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return $pdo->quote((string) $value);
                }, (array) $row);
                $columns = implode(', ', array_map(
                    fn (string $column): string => '"'.$column.'"',
                    array_keys((array) $row),
                ));
                $sql .= "INSERT INTO \"{$name}\" ({$columns}) VALUES (".implode(', ', $values).");\n";
            }
        }
        $sql .= "COMMIT;\nPRAGMA foreign_keys=ON;\n";

        if (file_put_contents($absolutePath, $sql) === false) {
            throw new RuntimeException("Unable to write SQL dump to {$absolutePath}");
        }
    }
}
