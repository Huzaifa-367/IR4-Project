<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Portable MySQL backup through Laravel's existing PDO connection.
 * Every SQL statement occupies one line, so restore can stream without loading
 * the complete dump into memory or requiring mysql/mysqldump binaries.
 */
final class MysqlDumper
{
    public function dumpTo(string $absolutePath): void
    {
        $pdo = DB::connection('mysql')->getPdo();
        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Unable to create database dump: {$absolutePath}");
        }

        try {
            $tables = $this->tables($pdo);
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->beginTransaction();
            try {
                $this->write($handle, 'SET FOREIGN_KEY_CHECKS=0;');
                foreach ($tables as $table) {
                    $this->dumpTable($pdo, $handle, $table);
                }
                $this->write($handle, 'SET FOREIGN_KEY_CHECKS=1;');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        } finally {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            fclose($handle);
        }
    }

    public function restoreFrom(string $absolutePath, string $database): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException("Invalid MySQL database name: {$database}");
        }

        $pdo = DB::connection('mysql')->getPdo();
        $quotedDatabase = $this->identifier($database);
        $pdo->exec(
            "CREATE DATABASE IF NOT EXISTS {$quotedDatabase} "
            .'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $pdo->exec("USE {$quotedDatabase}");
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to read database dump: {$absolutePath}");
        }
        try {
            while (($line = fgets($handle)) !== false) {
                $statement = trim($line);
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<string>
     */
    private function tables(PDO $pdo): array
    {
        $rows = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
            ->fetchAll(PDO::FETCH_NUM);

        return array_map(
            static fn (array $row): string => (string) $row[0],
            $rows,
        );
    }

    /**
     * @param  resource  $handle
     */
    private function dumpTable(PDO $pdo, $handle, string $table): void
    {
        $quotedTable = $this->identifier($table);
        $create = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_ASSOC);
        $ddl = is_array($create) ? (string) ($create['Create Table'] ?? '') : '';
        if ($ddl === '') {
            throw new RuntimeException("Unable to read schema for table {$table}");
        }

        $this->write($handle, "DROP TABLE IF EXISTS {$quotedTable};");
        $this->write($handle, str_replace(["\r", "\n"], ' ', $ddl).';');

        $rows = $pdo->query("SELECT * FROM {$quotedTable}", PDO::FETCH_ASSOC);
        while ($rows !== false && ($row = $rows->fetch(PDO::FETCH_ASSOC))) {
            $columns = array_map(
                fn (string $column): string => $this->identifier($column),
                array_keys($row),
            );
            $values = array_map(
                fn (mixed $value): string => $this->value($value),
                array_values($row),
            );
            $this->write(
                $handle,
                "INSERT INTO {$quotedTable} (".implode(',', $columns).') VALUES ('.implode(',', $values).');',
            );
        }
    }

    private function value(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $binary = (string) $value;

        return $binary === '' ? "''" : '0x'.bin2hex($binary);
    }

    private function identifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    /**
     * @param  resource  $handle
     */
    private function write($handle, string $statement): void
    {
        if (fwrite($handle, $statement."\n") === false) {
            throw new RuntimeException('Unable to write database dump.');
        }
    }
}
