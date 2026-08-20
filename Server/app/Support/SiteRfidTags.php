<?php

namespace App\Support;

use RuntimeException;

/**
 * Physical RFID EPCs for the site registry (Server/database/data/rfid_tags.php).
 * DemoSeeder assigns the first three to starter workers; ir4:s r uses 1-based indexes.
 */
final class SiteRfidTags
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        /** @var list<string> $rows */
        $rows = require database_path('data/rfid_tags.php');

        if ($rows === []) {
            throw new RuntimeException('rfid_tags.php is empty.');
        }

        return array_values(array_unique($rows));
    }

    /** 1-based index; unknown indexes return the first EPC. */
    public static function at(int $oneBasedIndex): string
    {
        $all = self::all();
        $i = $oneBasedIndex - 1;

        return $all[$i] ?? $all[0];
    }
}
