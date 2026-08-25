<?php

namespace App\Services\Hardware;

use App\Enums\AssetStatus;
use App\Enums\CameraRoiStaleReason;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Events\DeviceStatusChanged;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Camera;
use App\Models\Device;
use App\Models\User;
use App\Services\Alert\AlertService;
use App\Services\Camera\CameraRoiService;
use App\Services\Camera\CameraStreamGatewayService;
use App\Services\Platform\TechTeamNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class HardwareRegistryService
{
    public function __construct(
        private readonly AlertService $alerts,
        private readonly CameraStreamGatewayService $cameraStreams,
        private readonly TechTeamNotifier $techTeam,
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
        $camera = Camera::query()->create([
            'asset_id' => $data['asset_id'],
            'name' => $data['name'],
            'reference' => $data['reference'],
            'camera_type' => $data['camera_type'],
            'processed_by_device_id' => $data['processed_by_device_id']
                ?? $this->deviceIdForCameraRef((string) $data['reference']),
            'stream_url' => $data['stream_url'],
            'ai_enabled' => (bool) ($data['ai_enabled'] ?? true),
            'status' => HardwareStatus::Offline,
            'meta' => $data['meta'] ?? null,
        ]);

        $this->syncCameraStreamOrFlash($camera);

        return $camera;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCamera(Camera $camera, array $data): Camera
    {
        $previousReference = $camera->reference;
        $previousStreamUrl = $camera->stream_url;

        if (! array_key_exists('processed_by_device_id', $data)) {
            $ref = (string) ($data['reference'] ?? $camera->reference);
            $data['processed_by_device_id'] = $this->deviceIdForCameraRef($ref)
                ?? $camera->processed_by_device_id;
        }

        $camera->fill($data)->save();
        $fresh = $camera->fresh() ?? $camera;

        if ($previousReference !== $fresh->reference) {
            $this->cameraStreams->remove($previousReference);
            app(CameraRoiService::class)->markStale($fresh, CameraRoiStaleReason::StreamChanged);
        }

        if ($previousStreamUrl !== $fresh->stream_url) {
            app(CameraRoiService::class)->markStale($fresh, CameraRoiStaleReason::StreamChanged);
        }

        $this->syncCameraStreamOrFlash($fresh);

        return $fresh;
    }

    private function deviceIdForCameraRef(string $cameraRef): ?int
    {
        if ($cameraRef === '') {
            return null;
        }

        return Device::query()
            ->where('device_type', DeviceType::EdgeCompute)
            ->get(['id', 'config'])
            ->first(fn (Device $d): bool => ($d->config['camera_ref'] ?? null) === $cameraRef)
            ?->id;
    }

    private function syncCameraStreamOrFlash(Camera $camera): void
    {
        if (! $this->cameraStreams->isConfigured()) {
            return;
        }

        if ($this->cameraStreams->sync($camera)) {
            return;
        }

        $detail = $this->cameraStreams->lastError();
        session()->flash(
            'warning',
            'Camera saved, but MediaMTX sync failed'
            .($detail !== '' ? ': '.$detail : '.')
            .' Live wall will not show this feed until MEDIAMTX_API_URL is reachable from Lerd (use gateway) and sync succeeds.'
        );
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
        $type = $data['device_type'] instanceof DeviceType
            ? $data['device_type']
            : DeviceType::from((string) $data['device_type']);

        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        $config = $this->syncApiUrl(
            $config,
            $type,
            array_key_exists('api_url', $data) ? (string) ($data['api_url'] ?? '') : null,
        );
        unset($data['api_url']);

        $device = Device::query()->create([
            'asset_id' => $data['asset_id'],
            'name' => $data['name'],
            'reference' => $data['reference'],
            'serial_number' => $data['serial_number'] ?? null,
            'device_type' => $type,
            'status' => HardwareStatus::Offline,
            'config' => $config === [] ? null : $config,
        ]);

        $this->linkCameraToDevice($device);

        return $device;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDevice(Device $device, array $data): Device
    {
        $type = array_key_exists('device_type', $data)
            ? ($data['device_type'] instanceof DeviceType
                ? $data['device_type']
                : DeviceType::from((string) $data['device_type']))
            : $device->device_type;

        $config = is_array($device->config) ? $device->config : [];
        $incoming = array_key_exists('api_url', $data)
            ? (string) ($data['api_url'] ?? '')
            : null;
        $config = $this->syncApiUrl($config, $type, $incoming);
        $data['config'] = $config === [] ? null : $config;
        unset($data['api_url']);

        $device->fill($data)->save();
        $this->linkCameraToDevice($device->fresh() ?? $device);

        return $device->fresh() ?? $device;
    }

    /**
     * Persist nullable full API URL (incl. path) only for edge_compute devices.
     * `$url === null` preserves an existing `api_url`; empty string clears it.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function syncApiUrl(array $config, DeviceType $type, ?string $url): array
    {
        // Drop renamed keys from earlier DOC-23 iterations.
        unset($config['ai_host'], $config['ai_base_url']);

        if ($type !== DeviceType::EdgeCompute) {
            unset($config['api_url']);

            return $config;
        }

        if ($url === null) {
            return $config;
        }

        $url = trim($url);
        if ($url === '') {
            unset($config['api_url']);
        } else {
            $config['api_url'] = $url;
        }

        return $config;
    }

    /** Bind camera.reference ← device.config.camera_ref when present (DOC-23 1:1). */
    private function linkCameraToDevice(Device $device): void
    {
        $cameraRef = is_array($device->config) ? trim((string) ($device->config['camera_ref'] ?? '')) : '';
        if ($cameraRef === '') {
            return;
        }

        Camera::query()
            ->where('reference', $cameraRef)
            ->update(['processed_by_device_id' => $device->id]);
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

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function recordHeartbeat(
        Device $device,
        ?HardwareStatus $status = null,
        ?array $meta = null,
    ): Device {
        if ($device->isRetired()) {
            throw new HttpException(403, 'Device is retired.');
        }

        if ($status === HardwareStatus::Retired) {
            throw new HttpException(422, 'Heartbeat cannot retire a device.');
        }

        $device = $this->touchPresence($device, $status);

        if ($meta !== null) {
            $device->forceFill([
                'config' => array_merge($device->config ?? [], $meta),
            ])->save();
        }

        if ($device->asset_id !== null) {
            Asset::query()->whereKey($device->asset_id)->update([
                'last_heartbeat_at' => now(),
            ]);
        }

        return $device->fresh() ?? $device;
    }

    /**
     * Update last_seen_at and restore Online (unless operator-held Maintenance).
     * Used by device auth + heartbeats. Explicit heartbeat status must not leave maintenance.
     */
    public function touchPresence(Device $device, ?HardwareStatus $status = null): Device
    {
        if ($device->isRetired()) {
            throw new HttpException(403, 'Device is retired.');
        }

        $previousStatus = $device->status;

        // Operator-set maintenance is sticky until an operator restores status (DOC-05 §6.4).
        // Edge agents always post status=online; that must not undo disable-without-delete.
        if ($previousStatus === HardwareStatus::Maintenance) {
            $device->forceFill(['last_seen_at' => now()])->save();

            return $device->fresh() ?? $device;
        }

        $nextStatus = $status ?? HardwareStatus::Online;

        $device->forceFill([
            'last_seen_at' => now(),
            'status' => $nextStatus,
        ])->save();

        $this->alerts->resolveByDedupeKey("device_offline:{$device->id}");
        $this->alerts->resolveByDedupeKey("gas_telemetry_lost:{$device->id}");
        $this->techTeam->clear("device_offline:{$device->id}");
        $this->techTeam->clear("gas_telemetry_lost:{$device->id}");

        if ($previousStatus !== $nextStatus) {
            broadcast(new DeviceStatusChanged(
                $device->id,
                $nextStatus->value,
                $device->device_type->value,
                $device->name,
                $device->asset_id,
            ));
        }

        return $device->fresh() ?? $device;
    }

    /**
     * Refresh camera liveness from a live MediaMTX path (or PPE frame).
     * Restores Online unless operator-held Maintenance / Retired.
     */
    public function touchCameraPresence(Camera $camera, ?\DateTimeInterface $seenAt = null): Camera
    {
        if ($camera->status === HardwareStatus::Retired) {
            return $camera;
        }

        $previousStatus = $camera->status;
        $at = $seenAt ?? now();

        if ($previousStatus === HardwareStatus::Maintenance) {
            $camera->forceFill(['last_frame_at' => $at])->save();

            return $camera->fresh() ?? $camera;
        }

        $camera->forceFill([
            'last_frame_at' => $at,
            'status' => HardwareStatus::Online,
        ])->save();

        $this->alerts->resolveByDedupeKey("camera_offline:{$camera->id}");
        $this->techTeam->clear("camera_offline:{$camera->id}");

        if ($previousStatus !== HardwareStatus::Online) {
            broadcast(new DeviceStatusChanged(
                $camera->id,
                HardwareStatus::Online->value,
                'camera',
                $camera->name,
                $camera->asset_id,
            ));
        }

        return $camera->fresh() ?? $camera;
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
