<?php

namespace App\Services\Backup;

interface DatabaseDumper
{
    public function dumpTo(string $absolutePath): void;

    public function restoreFrom(string $absolutePath, string $connectionName): void;

    public function driver(): string;
}
