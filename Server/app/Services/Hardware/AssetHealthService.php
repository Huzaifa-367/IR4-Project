<?php

namespace App\Services\Hardware;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\AssetStatus;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Events\DeviceStatusChanged;
use App\Models\Asset;
use App\Models\Device;
use App\Services\Alert\AlertService;
use App\Services\Camera\CameraStreamGatewayService;
use App\Services\Platform\TechTeamNotifier;
use App\Services\Settings\SettingsService;
use App\Support\HardwarePresence;
use Illuminate\Support\Carbon;

final class AssetHealthService
{
    public function __construct(
        private readonly AlertService $alerts,
        private readonly SettingsService $settings,
        private readonly CameraStreamGatewayService $cameraStreams,
        private readonly HardwareRegistryService $hardware,
        private readonly TechTeamNotifier $techTeam,
    ) {}

    public function markStale(?\DateTimeInterface $now = null): void
    {
        $now = Carbon::instance($now ?? now());

        $this->refreshCamerasFromMediaMtx($now);

        Device::query()
            ->healthMonitored()
            ->each(function (Device $device) use ($now): void {
                $threshold = $this->staleMinutesForDevice($device->device_type);
                $staleBefore = $now->copy()->subMinutes($threshold);

                if ($device->last_seen_at !== null && $device->last_seen_at->greaterThan($staleBefore)) {
                    return;
                }

                if ($device->last_seen_at === null && $device->created_at?->greaterThan($staleBefore)) {
                    return;
                }

                if ($device->status !== HardwareStatus::Offline) {
                    $device->forceFill(['status' => HardwareStatus::Offline])->save();
                    broadcast(new DeviceStatusChanged(
                        $device->id,
                        HardwareStatus::Offline->value,
                        $device->device_type->value,
                        $device->name,
                        $device->asset_id,
                    ));
                }

                $this->alerts->raise(
                    type: AlertType::DeviceOffline,
                    title: "Device offline: {$device->name}",
                    payload: ['device_id' => $device->id, 'device_name' => $device->name],
                    source: $device,
                    dedupeKey: "device_offline:{$device->id}",
                );

                if ($device->device_type === DeviceType::Camera) {
                    $this->techTeam->notify(
                        dedupeKey: "device_offline:{$device->id}",
                        subject: "Camera AI offline: {$device->name}",
                        context: [
                            'category' => 'Camera AI',
                            'severity' => 'Critical for plant ops',
                            'summary' => "Camera AI host \"{$device->name}\" has stopped heartbeating. Ingest and ROI push on that pole may be degraded until it returns.",
                            'suggested_action' => 'Check power, LAN link, and the edge agent process on the pole. Confirm heartbeat resumes in Hardware → Devices, then verify live camera and ingest feeds.',
                            'details' => [
                                'Device' => $device->name,
                                'Device ID' => $device->id,
                                'Type' => $device->device_type->value,
                                'Asset ID' => $device->asset_id ?? '—',
                                'Last seen' => $device->last_seen_at?->timezone((string) config('app.timezone'))->toDateTimeString() ?? 'never',
                                'Stale threshold (min)' => $threshold,
                            ],
                        ],
                    );
                }

                $gasEscalate = (int) $this->settings->get('health.gas_offline_escalate_minutes', 30);
                if (
                    $device->device_type === DeviceType::GasDetector
                    && $device->last_seen_at !== null
                    && $device->last_seen_at->lessThanOrEqualTo($now->copy()->subMinutes($gasEscalate))
                ) {
                    $this->alerts->raise(
                        type: AlertType::System,
                        severity: AlertSeverity::Critical,
                        title: "Gas telemetry lost on device {$device->name}",
                        payload: ['device_id' => $device->id, 'device_name' => $device->name],
                        source: $device,
                        audible: true,
                        dedupeKey: "gas_telemetry_lost:{$device->id}",
                    );
                    $this->techTeam->notify(
                        dedupeKey: "gas_telemetry_lost:{$device->id}",
                        subject: "Gas telemetry lost: {$device->name}",
                        context: [
                            'category' => 'Gas detector',
                            'severity' => 'Critical — prolonged silence',
                            'summary' => "Gas detector \"{$device->name}\" has been offline longer than the escalate window ({$gasEscalate} min). Operators already see a critical system alert; this mail is for tech recovery.",
                            'suggested_action' => 'Inspect detector power/RS-485/gateway path, confirm the edge pole is online, and watch Hardware heartbeats until gas readings resume.',
                            'details' => [
                                'Device' => $device->name,
                                'Device ID' => $device->id,
                                'Last seen' => $device->last_seen_at->timezone((string) config('app.timezone'))->toDateTimeString(),
                                'Escalate after (min)' => $gasEscalate,
                                'Asset ID' => $device->asset_id ?? '—',
                            ],
                        ],
                    );
                }
            });

        Device::query()->cameras()
            ->healthMonitored()
            ->each(function (Device $camera) use ($now): void {
                $threshold = (int) $this->settings->get('health.camera_stale_minutes', 3);
                $staleBefore = $now->copy()->subMinutes($threshold);

                if ($camera->last_frame_at !== null && $camera->last_frame_at->greaterThan($staleBefore)) {
                    return;
                }

                if ($camera->last_frame_at === null && $camera->created_at?->greaterThan($staleBefore)) {
                    return;
                }

                if ($camera->status !== HardwareStatus::Offline) {
                    $camera->forceFill(['status' => HardwareStatus::Offline])->save();
                    broadcast(new DeviceStatusChanged(
                        $camera->id,
                        HardwareStatus::Offline->value,
                        'camera',
                        $camera->name,
                        $camera->asset_id,
                    ));
                }

                $this->alerts->raise(
                    type: AlertType::CameraOffline,
                    title: "Camera offline: {$camera->name}",
                    payload: ['camera_id' => $camera->id, 'camera_name' => $camera->name],
                    source: $camera,
                    dedupeKey: "camera_offline:{$camera->id}",
                );
                $this->techTeam->notify(
                    dedupeKey: "camera_offline:{$camera->id}",
                    subject: "Camera feed offline: {$camera->name}",
                    context: [
                        'category' => 'Camera',
                        'severity' => 'Feed unavailable',
                        'summary' => "Camera \"{$camera->name}\" has not delivered a fresh frame within the stale window ({$threshold} min). The live wall will drop this feed until frames return.",
                        'suggested_action' => 'Check camera power/PoE, RTSP URL, and MediaMTX path readiness. Confirm last_frame_at updates under Hardware → Devices, then verify the live wall tile remounts.',
                        'details' => [
                            'Camera' => $camera->name,
                            'Camera ID' => $camera->id,
                            'Reference' => $camera->reference,
                            'Asset ID' => $camera->asset_id ?? '—',
                            'Last frame' => $camera->last_frame_at?->timezone((string) config('app.timezone'))->toDateTimeString() ?? 'never',
                            'Stale threshold (min)' => $threshold,
                            'Status' => $camera->status->value,
                        ],
                    ],
                );
            });
    }

    /**
     * MediaMTX ready/online paths count as live frames (DOC-05 last_frame_at).
     * Keeps Live Wall in sync when PPE ingest is quiet but RTSP is flowing.
     */
    private function refreshCamerasFromMediaMtx(Carbon $now): void
    {
        $ready = $this->cameraStreams->readyPathNames();
        if ($ready === []) {
            return;
        }

        $readySet = array_fill_keys($ready, true);

        Device::query()->cameras()
            ->healthMonitored()
            ->each(function (Device $camera) use ($readySet, $now): void {
                $path = $this->cameraStreams->pathName($camera->reference);
                if (! isset($readySet[$path])) {
                    return;
                }

                $this->hardware->touchCameraPresence($camera, $now);
            });
    }

    public function systemHealthSnapshot(): array
    {
        return Asset::query()
            ->with(['devices' => fn ($query) => $query->healthMonitored()])
            ->where('status', '!=', AssetStatus::Offline->value)
            ->orderBy('name')
            ->get()
            ->map(function (Asset $asset): array {
                $offline = [];
                foreach ($asset->devices as $device) {
                    if (! HardwarePresence::isDeviceOnline($device, $this->staleMinutesForDevice($device->device_type))) {
                        $offline[] = $device->name;
                    }
                }

                $status = 'green';
                if ($offline !== []) {
                    $status = count($offline) >= 2 || $asset->status === AssetStatus::Offline ? 'red' : 'amber';
                } elseif ($asset->status === AssetStatus::Maintenance) {
                    $status = 'amber';
                }

                return [
                    'asset' => $asset->name,
                    'asset_id' => $asset->id,
                    'status' => $status,
                    'offline_components' => $offline,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Sidebar / dashboard online ratio — count individual devices, not poles.
     *
     * @return array{online: int, total: int}
     */
    public function devicePresenceCounts(): array
    {
        $devices = Device::query()
            ->healthMonitored()
            ->get(['id', 'status', 'device_type', 'last_seen_at']);

        $total = $devices->count();
        $online = $devices
            ->filter(fn (Device $device): bool => HardwarePresence::isDeviceOnline(
                $device,
                $this->staleMinutesForDevice($device->device_type),
            ))
            ->count();

        return [
            'online' => $online,
            'total' => $total,
        ];
    }

    public function staleMinutesForDevice(DeviceType $type): int
    {
        return (int) $this->settings->get(
            $type->staleMinutesKey(),
            $type->defaultStaleMinutes(),
        );
    }

    public function staleMinutesForCamera(): int
    {
        return (int) $this->settings->get('health.camera_stale_minutes', 3);
    }
}
