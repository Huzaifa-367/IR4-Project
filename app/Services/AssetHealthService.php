<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\Asset;
use App\Models\Camera;
use App\Models\Device;
use Illuminate\Support\Carbon;

final class AssetHealthService
{
    public function __construct(
        private readonly AlertService $alerts,
        private readonly SettingsService $settings,
    ) {}

    public function markStale(?\DateTimeInterface $now = null): void
    {
        $now = Carbon::instance($now ?? now());

        Device::query()
            ->whereNotIn('status', [
                HardwareStatus::Maintenance->value,
                HardwareStatus::Retired->value,
            ])
            ->whereHas('asset', fn ($q) => $q->where('status', '!=', AssetStatus::Maintenance->value))
            ->each(function (Device $device) use ($now): void {
                if (! $device->device_type->isHealthCritical()) {
                    return;
                }

                $threshold = $this->deviceThresholdMinutes($device->device_type);
                $staleBefore = $now->copy()->subMinutes($threshold);

                if ($device->last_seen_at !== null && $device->last_seen_at->greaterThan($staleBefore)) {
                    return;
                }

                if ($device->last_seen_at === null && $device->created_at?->greaterThan($staleBefore)) {
                    return;
                }

                if ($device->status !== HardwareStatus::Offline) {
                    $device->forceFill(['status' => HardwareStatus::Offline])->save();
                }

                $this->alerts->raise(
                    type: AlertType::DeviceOffline,
                    title: "Device offline: {$device->name}",
                    payload: ['device_id' => $device->id, 'device_name' => $device->name],
                    source: $device,
                    dedupeKey: "device_offline:{$device->id}",
                );

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
                }
            });

        Camera::query()
            ->whereNotIn('status', [
                HardwareStatus::Maintenance->value,
                HardwareStatus::Retired->value,
            ])
            ->each(function (Camera $camera) use ($now): void {
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
                }

                $this->alerts->raise(
                    type: AlertType::CameraOffline,
                    title: "Camera offline: {$camera->name}",
                    payload: ['camera_id' => $camera->id, 'camera_name' => $camera->name],
                    source: $camera,
                    dedupeKey: "camera_offline:{$camera->id}",
                );
            });
    }

    public function systemHealthSnapshot(): array
    {
        return Asset::query()
            ->with(['devices', 'cameras'])
            ->where('status', '!=', AssetStatus::Offline->value)
            ->orderBy('name')
            ->get()
            ->map(function (Asset $asset): array {
                $offline = [];
                foreach ($asset->devices as $device) {
                    if (in_array($device->status, [HardwareStatus::Offline, HardwareStatus::Fault], true)) {
                        $offline[] = $device->name;
                    }
                }
                foreach ($asset->cameras as $camera) {
                    if (in_array($camera->status, [HardwareStatus::Offline, HardwareStatus::Fault], true)) {
                        $offline[] = $camera->name;
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

    private function deviceThresholdMinutes(DeviceType $type): int
    {
        $key = match ($type) {
            DeviceType::RfidReader => 'health.reader_stale_minutes',
            DeviceType::GasDetector => 'health.gas_stale_minutes',
            DeviceType::EdgeCompute => 'health.edge_stale_minutes',
            DeviceType::Co2Sensor,
            DeviceType::EnvironmentalSensor,
            DeviceType::WifiGateway,
            DeviceType::Rs485Interface,
            DeviceType::Other => 'health.sensor_stale_minutes',
        };

        $default = match ($type) {
            DeviceType::EdgeCompute => 3,
            default => 5,
        };

        return (int) $this->settings->get($key, $default);
    }
}
