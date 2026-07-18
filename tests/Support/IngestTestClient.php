<?php

namespace Tests\Support;

use App\Models\Device;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IngestTestClient
{
    /**
     * @return array<string, string>
     */
    public static function headers(string $plainToken): array
    {
        return ['X-Device-Token' => $plainToken];
    }

    /**
     * @return array{event_uid: string, reader_ref: string, tag_uid: string, recorded_at: string}
     */
    public static function tagEvent(
        string $readerRef,
        string $tagUid,
        ?string $uid = null,
        ?string $recordedAt = null,
    ): array {
        return [
            'event_uid' => $uid ?? (string) Str::uuid(),
            'reader_ref' => $readerRef,
            'tag_uid' => $tagUid,
            'recorded_at' => $recordedAt ?? now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    public static function postTagReadings(TestCase $test, Device $device, string $plain, array $events): mixed
    {
        return $test->postJson(
            route('api.ingest.tag-readings'),
            ['events' => $events],
            self::headers($plain),
        );
    }

    /**
     * @return array{event_uid: string, camera_ref: string, type: string, detected_at: string}
     */
    public static function ppeEvent(
        string $cameraRef,
        string $type = 'missing_helmet',
        ?string $uid = null,
        ?string $detectedAt = null,
    ): array {
        return [
            'event_uid' => $uid ?? (string) Str::uuid(),
            'camera_ref' => $cameraRef,
            'type' => $type,
            'detected_at' => $detectedAt ?? now()->toIso8601String(),
        ];
    }
}
