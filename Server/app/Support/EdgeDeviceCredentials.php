<?php

namespace App\Support;

use App\Models\Device;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Default device UUID + plaintext tokens (Server/database/data/device_credentials.php).
 * Same rows as EdgeCompute/credentials.md — do not rotate them here.
 */
final class EdgeDeviceCredentials
{
    /**
     * @return list<array{ref: string, uuid: string, token: string, type: string, notes: string}>
     */
    public static function all(): array
    {
        /** @var list<array{ref: string, uuid: string, token: string, type: string, notes: string}> $rows */
        $rows = require database_path('data/device_credentials.php');

        if ($rows === []) {
            throw new RuntimeException('device_credentials.php is empty.');
        }

        return $rows;
    }

    /**
     * @return array{ref: string, uuid: string, token: string, type: string, notes: string}|null
     */
    public static function find(string $ref): ?array
    {
        foreach (self::all() as $row) {
            if ($row['ref'] === $ref) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Stamp committed UUID + token hash onto matching device references.
     * UUID is written via query builder because HasPublicUuid blocks Eloquent updates.
     *
     * @return array{updated: int, missing: int, aligned: int}
     */
    public static function applyToDevices(bool $dryRun = false): array
    {
        $updated = 0;
        $missing = 0;
        $aligned = 0;

        foreach (self::all() as $row) {
            $device = Device::query()->where('reference', $row['ref'])->first();
            if ($device === null) {
                $missing++;

                continue;
            }

            $hash = hash('sha256', $row['token']);
            if ($device->uuid === $row['uuid'] && $device->api_token_hash === $hash) {
                $aligned++;

                continue;
            }

            if (! $dryRun) {
                DB::table('devices')->where('id', $device->id)->update([
                    'uuid' => $row['uuid'],
                    'api_token_hash' => $hash,
                    'token_issued_at' => now(),
                ]);
            }
            $updated++;
        }

        return [
            'updated' => $updated,
            'missing' => $missing,
            'aligned' => $aligned,
        ];
    }
}
