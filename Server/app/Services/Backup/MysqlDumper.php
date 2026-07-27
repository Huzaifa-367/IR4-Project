<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use PDO;
use RuntimeException;
use Throwable;

/**
 * MySQL 8 dump/restore for site backups (DOC-19).
 *
 * Prefer the Oracle MySQL client binaries when present. On many Dell images
 * `mysqldump` is a MariaDB shim that cannot load caching_sha2_password; in
 * that case we fall back to PDO using the same connection Laravel already uses.
 */
final class MysqlDumper implements DatabaseDumper
{
    private const int INSERT_BATCH = 200;

    public function __construct(
        private readonly string $connection,
    ) {}

    public function dumpTo(string $absolutePath): void
    {
        $cliError = null;

        try {
            $this->dumpViaCli($absolutePath);

            return;
        } catch (Throwable $e) {
            $cliError = $e->getMessage();
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        try {
            $this->dumpViaPdo($absolutePath);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'MySQL dump failed.'
                .($cliError !== null ? ' CLI: '.$cliError : '')
                .' PDO: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    public function restoreFrom(string $absolutePath, string $connectionName): void
    {
        $cliError = null;

        try {
            $this->restoreViaCli($absolutePath, $connectionName);

            return;
        } catch (Throwable $e) {
            $cliError = $e->getMessage();
        }

        try {
            $this->restoreViaPdo($absolutePath, $connectionName);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'MySQL restore failed.'
                .($cliError !== null ? ' CLI: '.$cliError : '')
                .' PDO: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    private function dumpViaCli(string $absolutePath): void
    {
        $config = $this->connectionConfig($this->connection);
        $binary = $this->resolveBinary(
            (string) config('backup.mysqldump_path', 'mysqldump'),
            ['mysqldump', 'mariadb-dump'],
        );
        $defaultsFile = $this->writeDefaultsFile($config);

        try {
            $result = Process::timeout(3600)->run([
                $binary,
                '--defaults-extra-file='.$defaultsFile,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--result-file='.$absolutePath,
                (string) ($config['database'] ?? ''),
            ]);
        } finally {
            @unlink($defaultsFile);
        }

        if (! $result->successful() || ! is_file($absolutePath) || filesize($absolutePath) === 0) {
            throw new RuntimeException(trim($result->errorOutput().' '.$result->output()) ?: 'CLI dump produced no file');
        }
    }

    private function restoreViaCli(string $absolutePath, string $connectionName): void
    {
        $config = $this->connectionConfig($connectionName);
        $binary = $this->resolveBinary(
            (string) config('backup.mysql_path', 'mysql'),
            ['mysql', 'mariadb'],
        );
        $defaultsFile = $this->writeDefaultsFile($config);

        try {
            $result = Process::timeout(3600)
                ->input((string) file_get_contents($absolutePath))
                ->run([
                    $binary,
                    '--defaults-extra-file='.$defaultsFile,
                    (string) ($config['database'] ?? ''),
                ]);
        } finally {
            @unlink($defaultsFile);
        }

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput().' '.$result->output()) ?: 'CLI restore failed');
        }
    }

    private function dumpViaPdo(string $absolutePath): void
    {
        $pdo = DB::connection($this->connection)->getPdo();
        $database = (string) ($this->connectionConfig($this->connection)['database'] ?? '');
        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open dump file for writing.');
        }

        try {
            $this->writeLine($handle, '-- IR4 MySQL dump via PDO (Laravel connection)');
            $this->writeLine($handle, 'SET NAMES utf8mb4;');
            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=0;');
            $this->writeLine($handle, 'SET SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\';');
            $this->writeLine($handle, '');

            $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
            foreach ($tables as $row) {
                $table = (string) $row[0];
                $this->dumpTable($pdo, $handle, $table);
            }

            $views = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'VIEW\'')->fetchAll(PDO::FETCH_NUM);
            foreach ($views as $row) {
                $view = (string) $row[0];
                $create = $pdo->query('SHOW CREATE VIEW '.$this->quoteIdent($view))->fetch(PDO::FETCH_ASSOC);
                if (is_array($create) && isset($create['Create View'])) {
                    $this->writeLine($handle, 'DROP VIEW IF EXISTS '.$this->quoteIdent($view).';');
                    $this->writeLine($handle, (string) $create['Create View'].';');
                    $this->writeLine($handle, '');
                }
            }

            $this->dumpRoutines($pdo, $handle, $database, 'PROCEDURE');
            $this->dumpRoutines($pdo, $handle, $database, 'FUNCTION');
            $this->dumpTriggers($pdo, $handle, $database);

            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=1;');
        } finally {
            fclose($handle);
        }
    }

    private function dumpTable(PDO $pdo, $handle, string $table): void
    {
        $quoted = $this->quoteIdent($table);
        $create = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_ASSOC);
        if (! is_array($create)) {
            throw new RuntimeException("SHOW CREATE TABLE failed for {$table}");
        }

        $ddl = (string) ($create['Create Table'] ?? '');
        $this->writeLine($handle, 'DROP TABLE IF EXISTS '.$quoted.';');
        $this->writeLine($handle, $ddl.';');
        $this->writeLine($handle, '');

        $statement = $pdo->query('SELECT * FROM '.$quoted, PDO::FETCH_ASSOC);
        if ($statement === false) {
            return;
        }

        $batch = [];
        $columns = null;

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = array_keys($row);
            }
            $batch[] = $row;
            if (count($batch) >= self::INSERT_BATCH) {
                $this->writeInsertBatch($handle, $quoted, $columns, $batch, $pdo);
                $batch = [];
            }
        }

        if ($columns !== null && $batch !== []) {
            $this->writeInsertBatch($handle, $quoted, $columns, $batch, $pdo);
        }

        $this->writeLine($handle, '');
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeInsertBatch($handle, string $quotedTable, array $columns, array $rows, PDO $pdo): void
    {
        $columnSql = implode(', ', array_map(fn (string $c): string => $this->quoteIdent($c), $columns));
        $valueGroups = [];

        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $this->sqlLiteral($pdo, $row[$column] ?? null);
            }
            $valueGroups[] = '('.implode(', ', $values).')';
        }

        $this->writeLine(
            $handle,
            'INSERT INTO '.$quotedTable.' ('.$columnSql.') VALUES '.implode(",\n", $valueGroups).';',
        );
    }

    private function dumpRoutines(PDO $pdo, $handle, string $database, string $type): void
    {
        $sql = 'SELECT ROUTINE_NAME FROM information_schema.ROUTINES '
            .'WHERE ROUTINE_SCHEMA = '.$pdo->quote($database)
            .' AND ROUTINE_TYPE = '.$pdo->quote($type);

        $names = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($names as $name) {
            $routine = (string) $name;
            $create = $pdo->query('SHOW CREATE '.$type.' '.$this->quoteIdent($routine))->fetch(PDO::FETCH_ASSOC);
            $key = $type === 'FUNCTION' ? 'Create Function' : 'Create Procedure';
            if (! is_array($create) || ! isset($create[$key])) {
                continue;
            }
            $this->writeLine($handle, 'DROP '.$type.' IF EXISTS '.$this->quoteIdent($routine).';');
            $this->writeLine($handle, 'DELIMITER ;;');
            $this->writeLine($handle, (string) $create[$key].' ;;');
            $this->writeLine($handle, 'DELIMITER ;');
            $this->writeLine($handle, '');
        }
    }

    private function dumpTriggers(PDO $pdo, $handle, string $database): void
    {
        $sql = 'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS '
            .'WHERE TRIGGER_SCHEMA = '.$pdo->quote($database);
        $names = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

        foreach ($names as $name) {
            $trigger = (string) $name;
            $create = $pdo->query('SHOW CREATE TRIGGER '.$this->quoteIdent($trigger))->fetch(PDO::FETCH_ASSOC);
            if (! is_array($create) || ! isset($create['SQL Original Statement'])) {
                continue;
            }
            $this->writeLine($handle, 'DROP TRIGGER IF EXISTS '.$this->quoteIdent($trigger).';');
            $this->writeLine($handle, 'DELIMITER ;;');
            $this->writeLine($handle, (string) $create['SQL Original Statement'].' ;;');
            $this->writeLine($handle, 'DELIMITER ;');
            $this->writeLine($handle, '');
        }
    }

    private function restoreViaPdo(string $absolutePath, string $connectionName): void
    {
        $pdo = DB::connection($connectionName)->getPdo();
        $sql = (string) file_get_contents($absolutePath);
        $statements = $this->splitSqlStatements($sql);

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($statements as $statement) {
                if ($statement === '') {
                    continue;
                }
                $pdo->exec($statement);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $delimiter = ';';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if (! $inSingle && ! $inDouble && ! $inBacktick && ($char === '-' && $next === '-')) {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            if (! $inSingle && ! $inDouble && ! $inBacktick && strcasecmp(substr($sql, $i, 9), 'DELIMITER') === 0) {
                $lineEnd = strpos($sql, "\n", $i);
                $line = $lineEnd === false ? substr($sql, $i) : substr($sql, $i, $lineEnd - $i);
                $parts = preg_split('/\s+/', trim($line)) ?: [];
                if (isset($parts[1])) {
                    $delimiter = $parts[1];
                }
                $i = $lineEnd === false ? $length : $lineEnd;

                continue;
            }

            if ($char === "'" && ! $inDouble && ! $inBacktick) {
                $inSingle = ! $inSingle;
            } elseif ($char === '"' && ! $inSingle && ! $inBacktick) {
                $inDouble = ! $inDouble;
            } elseif ($char === '`' && ! $inSingle && ! $inDouble) {
                $inBacktick = ! $inBacktick;
            }

            $buffer .= $char;

            if (! $inSingle && ! $inDouble && ! $inBacktick && str_ends_with($buffer, $delimiter)) {
                $statements[] = trim(substr($buffer, 0, -strlen($delimiter)));
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * @param  list<string>  $fallbacks
     */
    private function resolveBinary(string $configured, array $fallbacks): string
    {
        $candidates = [];
        if ($configured !== '') {
            $candidates[] = $configured;
        }
        foreach ($fallbacks as $fallback) {
            if (! in_array($fallback, $candidates, true)) {
                $candidates[] = $fallback;
            }
        }

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, '/') && is_executable($candidate)) {
                return $candidate;
            }

            $which = Process::run(['bash', '-lc', 'command -v '.escapeshellarg($candidate)]);
            if ($which->successful()) {
                $path = trim($which->output());
                if ($path !== '') {
                    return $path;
                }
            }
        }

        return $configured !== '' ? $configured : ($fallbacks[0] ?? 'mysqldump');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeDefaultsFile(array $config): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ir4-mysql-');
        if ($path === false) {
            throw new RuntimeException('Unable to create MySQL defaults file.');
        }

        $cnf = "[client]\n"
            .'host='.$this->cnfValue((string) ($config['host'] ?? '127.0.0.1'))."\n"
            .'port='.$this->cnfValue((string) ($config['port'] ?? '3306'))."\n"
            .'user='.$this->cnfValue((string) ($config['username'] ?? ''))."\n"
            .'password='.$this->cnfValue((string) ($config['password'] ?? ''))."\n";

        file_put_contents($path, $cnf);
        chmod($path, 0600);

        return $path;
    }

    private function cnfValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(string $connectionName): array
    {
        $config = config("database.connections.{$connectionName}");
        if (! is_array($config)) {
            throw new RuntimeException("Unknown database connection [{$connectionName}].");
        }

        return $config;
    }

    private function sqlLiteral(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }

    private function quoteIdent(string $ident): string
    {
        return '`'.str_replace('`', '``', $ident).'`';
    }

    /**
     * @param  resource  $handle
     */
    private function writeLine($handle, string $line): void
    {
        fwrite($handle, $line."\n");
    }
}
