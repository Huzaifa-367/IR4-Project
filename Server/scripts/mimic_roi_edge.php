<?php

/**
 * Mimic Jetson edge AI: publish ROIs, pull, ingest violations for UI testing.
 *
 *   cd Server && php scripts/mimic_roi_edge.php
 */

declare(strict_types=1);

use App\Enums\HardwareStatus;
use App\Models\Camera;
use App\Models\Device;
use App\Models\RoiViolation;
use App\Models\User;
use App\Services\Camera\CameraRoiService;
use App\Services\Hardware\HardwareRegistryService;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$base = rtrim((string) config('app.url'), '/');
$deviceRef = 'DEV-CAM-FIXED-01';
$cameraRef = 'CAM-FIXED-01';
$roiRef = 'roi_bay_floor';

$device = Device::query()->where('reference', $deviceRef)->first();
$camera = Camera::query()->where('reference', $cameraRef)->first();
if ($device === null || $camera === null) {
    fwrite(STDERR, "Missing {$deviceRef} / {$cameraRef} — run DemoSeeder first.\n");
    exit(1);
}

$camera->forceFill([
    'processed_by_device_id' => $device->id,
    'status' => HardwareStatus::Online,
    'last_frame_at' => now(),
    'ai_enabled' => true,
])->save();

$admin = User::query()->role('Super Admin')->orderBy('id')->first()
    ?? User::query()->where('email', 'admin@gmail.com')->first();
if ($admin === null) {
    fwrite(STDERR, "No Super Admin user found.\n");
    exit(1);
}

$issued = app(HardwareRegistryService::class)->issueToken($device);
$plain = $issued['plain_token'];
$device = $issued['device'];

app(CameraRoiService::class)->publish($camera->fresh() ?? $camera, [
    [
        'name' => 'Bay floor',
        'reference' => $roiRef,
        'polygon' => [
            ['x' => 0.12, 'y' => 0.30],
            ['x' => 0.45, 'y' => 0.28],
            ['x' => 0.40, 'y' => 0.61],
            ['x' => 0.15, 'y' => 0.58],
        ],
        'color' => '#22d3ee',
        'sort_order' => 0,
        'is_enabled' => true,
    ],
    [
        'name' => 'Walkway',
        'reference' => 'roi_walkway',
        'polygon' => [
            ['x' => 0.55, 'y' => 0.20],
            ['x' => 0.90, 'y' => 0.22],
            ['x' => 0.88, 'y' => 0.75],
            ['x' => 0.52, 'y' => 0.70],
        ],
        'color' => '#a78bfa',
        'sort_order' => 1,
        'is_enabled' => true,
    ],
], $admin);

echo "Published ROIs on {$cameraRef}\n";
echo "Device UUID: {$device->uuid}\n";
echo "Token: {$plain}\n";
echo "Base: {$base}\n\n";

$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'X-Device-Token: '.$plain,
];

$httpJson = static function (string $method, string $url, array $headers, ?array $body = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $status,
        'error' => $raw === false ? $err : null,
        'json' => is_string($raw) ? json_decode($raw, true) : null,
        'raw' => is_string($raw) ? $raw : null,
    ];
};

$pull = $httpJson('GET', "{$base}/api/devices/{$device->uuid}/camera-rois", $headers);
echo "GET /api/devices/{uuid}/camera-rois → HTTP {$pull['status']}\n";
$roiCount = is_array($pull['json']['data']['rois'] ?? null) ? count($pull['json']['data']['rois']) : 0;
echo "  rois={$roiCount} fingerprint=".($pull['json']['data']['view_fingerprint'] ?? 'null')."\n";

$jpeg1x1 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQL/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Av/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAT8hf//Z';

$events = [
    [
        'event_uid' => (string) Str::uuid(),
        'camera_ref' => $cameraRef,
        'roi_reference' => $roiRef,
        'event_type' => 'roi_intrusion',
        'detected_at' => now()->subMinutes(2)->toIso8601String(),
        'confidence' => 0.91,
        'snapshot' => $jpeg1x1,
    ],
    [
        'event_uid' => (string) Str::uuid(),
        'camera_ref' => $cameraRef,
        'roi_reference' => 'roi_walkway',
        'event_type' => 'roi_intrusion',
        'detected_at' => now()->subMinute()->toIso8601String(),
        'confidence' => 0.84,
    ],
    [
        'event_uid' => (string) Str::uuid(),
        'camera_ref' => $cameraRef,
        'roi_reference' => $roiRef,
        'event_type' => 'roi_intrusion',
        'detected_at' => now()->toIso8601String(),
        'confidence' => 0.77,
    ],
];

$ingest = $httpJson('POST', "{$base}/api/ingest/roi-violations", $headers, ['events' => $events]);
echo "\nPOST /api/ingest/roi-violations → HTTP {$ingest['status']}\n";
echo '  '.json_encode($ingest['json'] ?? ['error' => $ingest['error'], 'raw' => $ingest['raw']])."\n";

$dup = $httpJson('POST', "{$base}/api/ingest/roi-violations", $headers, [
    'events' => [$events[0]],
]);
echo 'POST duplicate → '.json_encode($dup['json'] ?? [])."\n";

echo "\nroi_violations DB count: ".RoiViolation::query()->count()."\n";
echo "UI: {$base}/roi-violations\n";
echo "ROIs: {$base}/settings/camera-rois\n";
