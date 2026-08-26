<?php

/**
 * Mimic camera headcount ingest for local UI checks (DOC-09).
 *
 * Binds demo fixed cameras to work zones, sets live source to camera,
 * then POSTs absolute counts via /api/ingest/headcount-readings.
 *
 *   cd Server && php scripts/mimic_headcount_edge.php
 *   cd Server && php scripts/mimic_headcount_edge.php --loop
 */

declare(strict_types=1);

use App\Enums\HardwareStatus;
use App\Enums\HeadcountSource;
use App\Models\Camera;
use App\Models\CameraHeadcountReading;
use App\Models\Device;
use App\Models\User;
use App\Models\Zone;
use App\Services\Hardware\HardwareRegistryService;
use App\Services\Settings\SettingsService;
use App\Services\Tracking\CameraZoneBindingService;
use App\Services\Tracking\TrackingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$loop = in_array('--loop', $argv, true);
$base = rtrim((string) config('app.url'), '/');
if (str_contains($base, 'ir4.ispc') || $base === '') {
    $base = 'http://127.0.0.1:8000';
}

$admin = User::query()->role('Super Admin')->orderBy('id')->first()
    ?? User::query()->where('email', 'admin@gmail.com')->first();
if ($admin === null) {
    fwrite(STDERR, "No Super Admin user found.\n");
    exit(1);
}

/** @var list<array{device: string, camera: string, zone: string, count: int}> $targets */
$targets = [
    ['device' => 'DEV-CAM-FIXED-01', 'camera' => 'CAM-FIXED-01', 'zone' => 'Pole 01 Work', 'count' => 7],
    ['device' => 'DEV-CAM-FIXED-02', 'camera' => 'CAM-FIXED-02', 'zone' => 'Pole 02 Work', 'count' => 5],
    ['device' => 'DEV-CAM-FIXED-04', 'camera' => 'CAM-FIXED-04', 'zone' => 'Pole 04 Height Work', 'count' => 3],
];

$bindings = app(CameraZoneBindingService::class);
$settings = app(SettingsService::class);
$hardware = app(HardwareRegistryService::class);

$settings->set('tracking.headcount_source', HeadcountSource::Camera->value, $admin, confirmed: true);

$prepared = [];
foreach ($targets as $target) {
    $device = Device::query()->where('reference', $target['device'])->first();
    $camera = Camera::query()->where('reference', $target['camera'])->first();
    $zone = Zone::query()->where('name', $target['zone'])->first();

    if ($device === null || $camera === null || $zone === null) {
        fwrite(STDERR, "Missing {$target['device']} / {$target['camera']} / {$target['zone']} — run DemoSeeder first.\n");
        exit(1);
    }

    $camera->forceFill([
        'processed_by_device_id' => $device->id,
        'status' => HardwareStatus::Online,
        'last_frame_at' => now(),
        'ai_enabled' => true,
    ])->save();

    $currentZoneId = $camera->currentZoneBinding?->zone_id;
    if ($currentZoneId !== $zone->id) {
        $bindings->bind($camera, $zone, now(), $admin, 'mimic headcount bind');
    }

    $issued = $hardware->issueToken($device);
    $prepared[] = [
        'device' => $issued['device'],
        'token' => $issued['plain_token'],
        'camera' => $camera->fresh() ?? $camera,
        'zone' => $zone,
        'base_count' => $target['count'],
    ];
}

echo "Headcount source → camera\n";
echo "Base: {$base}\n";
echo "UI: {$base}/tracking\n";
echo "Records: {$base}/tracking/headcount-readings\n\n";

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

$tick = 0;
do {
    $tick++;
    $eventsByToken = [];

    foreach ($prepared as $row) {
        $jitter = random_int(-1, 2);
        $count = max(0, $row['base_count'] + $jitter + ($tick % 3));
        $token = $row['token'];
        $eventsByToken[$token] ??= [
            'device' => $row['device'],
            'events' => [],
        ];
        $eventsByToken[$token]['events'][] = [
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => now()->toIso8601String(),
            'count' => $count,
            'camera_ref' => $row['camera']->reference,
        ];
        echo sprintf(
            "  %s → %s count=%d\n",
            $row['camera']->reference,
            $row['zone']->name,
            $count,
        );
    }

    foreach ($eventsByToken as $token => $payload) {
        $device = $payload['device'];
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Device-Token: '.$token,
        ];
        $ingest = $httpJson('POST', "{$base}/api/ingest/headcount-readings", $headers, [
            'events' => $payload['events'],
        ]);
        echo sprintf(
            "POST /api/ingest/headcount-readings (%s) → HTTP %d %s\n",
            $device->reference,
            $ingest['status'],
            json_encode($ingest['json'] ?? ['error' => $ingest['error'], 'raw' => $ingest['raw']]),
        );
    }

    $snapshot = $httpJson('GET', "{$base}/tracking/api/headcount", [
        'Accept: application/json',
    ]);
    // session cookie not set — snapshot may 401; fall back to DB service
    if (($snapshot['status'] ?? 0) === 200) {
        echo 'Snapshot: '.json_encode($snapshot['json']['data'] ?? $snapshot['json'])."\n";
    } else {
        $live = app(TrackingService::class)->headcountSnapshot();
        echo 'Snapshot (local): '.json_encode($live)."\n";
    }

    echo 'DB samples: '.CameraHeadcountReading::query()->count()."\n";

    if ($loop) {
        echo "— loop tick {$tick}, sleep 8s (Ctrl-C to stop) —\n\n";
        sleep(8);
    }
} while ($loop);

echo "\nDone. Open Tracking → Latest headcount / Headcount records.\n";
