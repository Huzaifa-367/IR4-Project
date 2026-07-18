<?php

use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\IngestTestClient;

function scenarioIngestTag(
    Device $device,
    string $plainToken,
    string $tagUid,
    ?\DateTimeInterface $at = null,
): void {
    test()->postJson(
        route('api.ingest.tag-readings'),
        [
            'events' => [
                IngestTestClient::tagEvent(
                    $device->reference,
                    $tagUid,
                    recordedAt: Carbon::instance($at ?? now())->toIso8601String(),
                ),
            ],
        ],
        IngestTestClient::headers($plainToken),
    )->assertAccepted();
}

/**
 * @return array<string, mixed>
 */
function scenarioPpeEvent(
    string $cameraRef,
    string $type = 'missing_helmet',
    ?string $detectedAt = null,
): array {
    return [
        'event_uid' => (string) Str::uuid(),
        'camera_ref' => $cameraRef,
        'event_type' => $type,
        'detected_at' => $detectedAt ?? now()->toIso8601String(),
        'confidence' => 0.91,
        'worker_count' => 1,
        'snapshot' => base64_encode('fake-jpeg'),
    ];
}

/**
 * @param  array<string, mixed>  $channels
 * @return array<string, mixed>
 */
function scenarioGasEvent(
    array $channels,
    string $deviceRef,
    ?string $recordedAt = null,
): array {
    return array_filter([
        'event_uid' => (string) Str::uuid(),
        'device_ref' => $deviceRef,
        'recorded_at' => $recordedAt ?? now()->toIso8601String(),
        ...$channels,
    ], fn ($value) => $value !== null);
}

function scenarioGasIngest(
    Device $device,
    string $plainToken,
    array $channels,
    ?string $recordedAt = null,
): void {
    test()->postJson(
        route('api.ingest.gas-readings'),
        ['events' => [scenarioGasEvent($channels, $device->reference, $recordedAt)]],
        IngestTestClient::headers($plainToken),
    )->assertAccepted();
}
