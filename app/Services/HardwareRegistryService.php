<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Camera;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class HardwareRegistryService
{
    public function __construct(
        private readonly AlertService $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAsset(array $data): Asset
    {
        return Asset::query()->create([
            'asset_type' => $data['asset_type'],
            'name' => $data['name'],
            'identifier' => $data['identifier'],
            'status' => $data['status'] ?? AssetStatus::Active,
            'is_mobile' => (bool) ($data['is_mobile'] ?? false),
            'current_location_label' => $data['current_location_label'] ?? null,
            'meta' => $data['meta'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAsset(Asset $asset, array $data): Asset
    {
        $asset->fill($data)->save();

        return $asset->fresh() ?? $asset;
    }

    public function destroyAsset(Asset $asset): void
    {
        if ($asset->cameras()->exists() || $asset->devices()->exists()) {
            throw new HttpException(409, 'Remove or reassign cameras and devices before deleting this asset.');
        }

        $asset->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCamera(array $data): Camera
    {
        return Camera::query()->create([
            'asset_id' => $data['asset_id'],
            'name' => $data['name'],
            'reference' => $data['reference'],
            'camera_type' => $data['camera_type'],
            'processed_by_device_id' => $data['processed_by_device_id'] ?? null,
            'stream_url' => $data['stream_url'],
            'ai_enabled' => (bool) ($data['ai_enabled'] ?? true),
            'status' => HardwareStatus::Offline,
            'meta' => $data['meta'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCamera(Camera $camera, array $data): Camera
    {
        $camera->fill($data)->save();

        return $camera->fresh() ?? $camera;
    }

    public function toggleCameraAi(Camera $camera): Camera
    {
        $camera->forceFill(['ai_enabled' => ! $camera->ai_enabled])->save();

        return $camera;
    }

    public function setCameraStatus(Camera $camera, HardwareStatus $status): Camera
    {
        $camera->forceFill(['status' => $status])->save();

        return $camera->fresh() ?? $camera;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDevice(array $data): Device
    {
        return Device::query()->create([
            'asset_id' => $data['asset_id'],
            'name' => $data['name'],
            'reference' => $data['reference'],
            'serial_number' => $data['serial_number'] ?? null,
            'device_type' => $data['device_type'],
            'status' => HardwareStatus::Offline,
            'config' => $data['config'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDevice(Device $device, array $data): Device
    {
        $device->fill($data)->save();

        return $device->fresh() ?? $device;
    }

    public function setDeviceStatus(Device $device, HardwareStatus $status): Device
    {
        $device->forceFill(['status' => $status])->save();

        if ($status === HardwareStatus::Retired) {
            $device->forceFill(['api_token_hash' => null])->save();
        }

        return $device;
    }

    public function destroyDevice(Device $device): void
    {
        if ($this->deviceHasZoneBinding($device)) {
            throw new HttpException(409, 'Unbind this reader from zones before deleting it.');
        }

        $device->delete();
    }

    /**
     * @return array{device: Device, plain_token: string}
     */
    public function issueToken(Device $device, ?User $actor = null): array
    {
        if ($device->isRetired()) {
            throw new HttpException(409, 'Cannot issue a token for a retired device.');
        }

        $plain = 'dev_'.Str::random(48);
        $device->forceFill([
            'api_token_hash' => hash('sha256', $plain),
            'token_issued_at' => now(),
        ])->save();

        AuditLog::query()->create([
            'event_type' => 'config_changed',
            'user_id' => $actor?->id ?? auth()->id(),
            'route' => request()->path(),
            'payload' => [
                'target' => 'device_token',
                'device_id' => $device->id,
                'token_issued_at' => $device->token_issued_at?->toIso8601String(),
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        return [
            'device' => $device->fresh() ?? $device,
            'plain_token' => $plain,
        ];
    }

    public function recordHeartbeat(Device $device): Device
    {
        if ($device->isRetired()) {
            throw new HttpException(403, 'Device is retired.');
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'status' => $device->status === HardwareStatus::Maintenance
                ? HardwareStatus::Maintenance
                : HardwareStatus::Online,
        ])->save();

        if ($device->asset_id !== null) {
            Asset::query()->whereKey($device->asset_id)->update([
                'last_heartbeat_at' => now(),
            ]);
        }

        $this->alerts->resolveByDedupeKey("device_offline:{$device->id}");

        return $device->fresh() ?? $device;
    }

    private function deviceHasZoneBinding(Device $device): bool
    {
        if (! Schema::hasTable('reader_zone_bindings')) {
            return false;
        }

        return DB::table('reader_zone_bindings')
            ->where('device_id', $device->id)
            ->whereNull('bound_until')
            ->exists();
    }
}
