<?php

namespace App\Services\Hardware;

use App\Enums\AssetStatus;
use App\Enums\CameraRoiStaleReason;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Events\DeviceStatusChanged;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use App\Services\Alert\AlertService;
use App\Services\Camera\CameraRoiService;
use App\Services\Camera\CameraStreamGatewayService;
use App\Services\Platform\TechTeamNotifier;
use App\Support\HardwarePresence;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        if ($asset->devices()->exists()) {
            throw new HttpException(409, 'Remove or reassign devices before deleting this asset.');
        }

        $asset->delete();
    }

    private function syncCameraStreamOrFlash(Device $camera): void
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

    public function toggleCameraAi(Device $camera): Device
    {
        $camera->forceFill(['ai_enabled' => ! $camera->ai_enabled])->save();

        return $camera;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{device: Device, plain_token?: string}
     */
    public function createDevice(array $data, ?User $actor = null): array
    {
        $type = $data['device_type'] instanceof DeviceType
            ? $data['device_type']
            : DeviceType::from((string) $data['device_type']);

        if ($type->isCamera()) {
            return DB::transaction(function () use ($data, $actor): array {
                $device = Device::query()->create([
                    'asset_id' => $data['asset_id'],
                    'name' => $data['name'],
                    'reference' => $data['reference'],
                    'device_type' => DeviceType::Camera,
                    'camera_type' => $data['camera_type'],
                    'stream_url' => $data['stream_url'],
                    'ai_enabled' => (bool) ($data['ai_enabled'] ?? true),
                    'api_url' => trim((string) $data['api_url']),
                    'status' => HardwareStatus::Offline,
                    'meta' => $data['meta'] ?? null,
                ]);

                $issued = $this->issueToken($device, $actor);
                $device = $issued['device'];
                $this->syncCameraStreamOrFlash($device);

                return [
                    'device' => $device,
                    'plain_token' => $issued['plain_token'],
                ];
            });
        }

        $device = Device::query()->create([
            'asset_id' => $data['asset_id'],
            'name' => $data['name'],
            'reference' => $data['reference'],
            'serial_number' => $data['serial_number'] ?? null,
            'device_type' => $type,
            'status' => HardwareStatus::Offline,
            'config' => $data['config'] ?? null,
            'printer_host' => $type === DeviceType::QrPrinter ? ($data['printer_host'] ?? null) : null,
            'printer_port' => $type === DeviceType::QrPrinter ? ($data['printer_port'] ?? null) : null,
        ]);

        $plainToken = null;
        if (($data['issue_token'] ?? true) && $device->usesIngestToken()) {
            $issued = $this->issueToken($device, $actor);
            $device = $issued['device'];
            $plainToken = $issued['plain_token'];
        }

        return [
            'device' => $device->fresh() ?? $device,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDevice(Device $device, array $data): Device
    {
        if ($device->isCamera()) {
            $previousReference = $device->reference;
            $previousStreamUrl = $device->stream_url;

            return DB::transaction(function () use ($device, $data, $previousReference, $previousStreamUrl): Device {
                $device->fill([
                    'asset_id' => $data['asset_id'] ?? $device->asset_id,
                    'name' => $data['name'] ?? $device->name,
                    'reference' => $data['reference'] ?? $device->reference,
                    'camera_type' => $data['camera_type'] ?? $device->camera_type,
                    'stream_url' => $data['stream_url'] ?? $device->stream_url,
                    'ai_enabled' => array_key_exists('ai_enabled', $data)
                        ? (bool) $data['ai_enabled']
                        : $device->ai_enabled,
                    'api_url' => array_key_exists('api_url', $data)
                        ? trim((string) $data['api_url'])
                        : $device->api_url,
                ])->save();

                if (! $device->hasToken()) {
                    $this->issueToken($device);
                }

                $fresh = $device->fresh() ?? $device;

                if ($previousReference !== $fresh->reference) {
                    $this->cameraStreams->remove($previousReference);
                    app(CameraRoiService::class)->markStale($fresh, CameraRoiStaleReason::StreamChanged);
                }

                if ($previousStreamUrl !== $fresh->stream_url) {
                    app(CameraRoiService::class)->markStale($fresh, CameraRoiStaleReason::StreamChanged);
                }

                $this->syncCameraStreamOrFlash($fresh);

                return $fresh;
            });
        }

        $type = array_key_exists('device_type', $data)
            ? ($data['device_type'] instanceof DeviceType
                ? $data['device_type']
                : DeviceType::from((string) $data['device_type']))
            : $device->device_type;

        if ($type->isCamera()) {
            throw new HttpException(422, 'Cannot change an existing device into a camera.');
        }

        $device->fill([
            'asset_id' => $data['asset_id'] ?? $device->asset_id,
            'name' => $data['name'] ?? $device->name,
            'reference' => $data['reference'] ?? $device->reference,
            'serial_number' => array_key_exists('serial_number', $data) ? $data['serial_number'] : $device->serial_number,
            'device_type' => $type,
            'config' => array_key_exists('config', $data) ? $data['config'] : $device->config,
            'printer_host' => $type === DeviceType::QrPrinter
                ? ($data['printer_host'] ?? $device->printer_host)
                : null,
            'printer_port' => $type === DeviceType::QrPrinter
                ? ($data['printer_port'] ?? $device->printer_port)
                : null,
        ])->save();

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

        if (! $device->usesIngestToken()) {
            throw new HttpException(422, 'This device type does not use ingest tokens.');
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

    public function touchPresence(Device $device, ?HardwareStatus $status = null): Device
    {
        if ($device->isRetired()) {
            throw new HttpException(403, 'Device is retired.');
        }

        $previousStatus = $device->status;

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

    public function touchCameraPresence(Device $camera, ?\DateTimeInterface $seenAt = null): Device
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

    /**
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function registryRows(Request $request, AssetHealthService $health): array
    {
        $typeFilter = $request->string('device_type')->toString();
        $statusFilter = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());
        $sort = $request->string('sort')->toString() ?: 'name';
        $direction = strtolower($request->string('direction')->toString()) === 'desc' ? 'desc' : 'asc';

        $rows = collect();
        $cameraStaleMinutes = $health->staleMinutesForCamera();

        $deviceQuery = Device::query()
            ->registryVisible()
            ->with('asset:id,uuid,name');

        if ($typeFilter !== '' && $typeFilter !== 'all') {
            if ($typeFilter === 'camera') {
                $deviceQuery->cameras();
            } else {
                $deviceQuery->ofType(DeviceType::from($typeFilter));
            }
        }

        if ($statusFilter !== '') {
            $deviceQuery->where('status', $statusFilter);
        }

        if ($search !== '') {
            $deviceQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        foreach ($deviceQuery->get() as $device) {
            if ($device->isCamera()) {
                $rows->push($this->cameraUnitRegistryRow($device, $health, $cameraStaleMinutes));
            } else {
                $rows->push($this->fieldDeviceRegistryRow($device, $health));
            }
        }

        $sorted = $this->sortRegistryRows($rows, $sort, $direction);
        $total = $sorted->count();
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));
        $data = $sorted->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return ['data' => $data, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    private function cameraUnitRegistryRow(Device $camera, AssetHealthService $health, int $cameraStaleMinutes): array
    {
        $streamOnline = HardwarePresence::isCameraOnline($camera, $cameraStaleMinutes);
        $tokenOnline = HardwarePresence::isDeviceOnline(
            $camera,
            $health->staleMinutesForDevice($camera->device_type),
        );

        return [
            'kind' => 'camera',
            'id' => $camera->id,
            'uuid' => $camera->uuid,
            'name' => $camera->name,
            'reference' => $camera->reference,
            'camera_type' => $camera->camera_type->value,
            'camera_type_label' => $camera->camera_type->label(),
            'stream_url' => $camera->stream_url,
            'ai_enabled' => $camera->ai_enabled,
            'status' => $camera->status->value,
            'is_online' => $streamOnline && $tokenOnline,
            'stream_is_online' => $streamOnline,
            'has_token' => $camera->hasToken(),
            'last_seen_at' => $camera->last_seen_at?->toIso8601String(),
            'last_frame_at' => $camera->last_frame_at?->toIso8601String(),
            'is_incomplete' => ! $camera->hasToken(),
            'api_url' => $camera->api_url,
            'ai_device' => null,
            'asset' => $camera->asset === null ? null : [
                'id' => $camera->asset->id,
                'uuid' => $camera->asset->uuid,
                'name' => $camera->asset->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldDeviceRegistryRow(Device $device, AssetHealthService $health): array
    {
        $isOnline = $device->usesIngestToken()
            ? HardwarePresence::isDeviceOnline($device, $health->staleMinutesForDevice($device->device_type))
            : false;

        return [
            'kind' => 'device',
            'id' => $device->id,
            'uuid' => $device->uuid,
            'name' => $device->name,
            'reference' => $device->reference,
            'serial_number' => $device->serial_number,
            'device_type' => $device->device_type->value,
            'device_type_label' => $device->device_type->label(),
            'status' => $device->status->value,
            'is_online' => $isOnline,
            'has_token' => $device->hasToken(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'printer_host' => $device->printer_host,
            'printer_port' => $device->printer_port,
            'is_orphan_camera_ai' => false,
            'asset' => $device->asset === null ? null : [
                'id' => $device->asset->id,
                'uuid' => $device->asset->uuid,
                'name' => $device->asset->name,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRegistryRows(Collection $rows, string $sort, string $direction): Collection
    {
        $sorted = $rows->sortBy(
            fn (array $row): string => match ($sort) {
                'reference' => (string) $row['reference'],
                'status' => (string) $row['status'],
                default => (string) $row['name'],
            },
            SORT_NATURAL | SORT_FLAG_CASE,
        );

        if ($direction === 'desc') {
            $sorted = $sorted->reverse();
        }

        return $sorted->values();
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
