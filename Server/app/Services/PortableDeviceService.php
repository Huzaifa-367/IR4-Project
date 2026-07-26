<?php

namespace App\Services;

use App\Enums\PortableDeviceStatus;
use App\Models\AuditLog;
use App\Models\PortableDevice;
use App\Models\User;
use App\Models\Worker;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PortableDeviceService
{
    /**
     * @param  array{device_type: string, make_model?: ?string, serial_number?: ?string, approval_reference?: ?string}  $data
     */
    public function create(Worker $worker, array $data, User $by): PortableDevice
    {
        $device = PortableDevice::query()->create([
            'worker_id' => $worker->id,
            'device_type' => $data['device_type'],
            'make_model' => $data['make_model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'approval_reference' => $data['approval_reference'] ?? null,
            'status' => PortableDeviceStatus::Approved,
            'approved_by' => $by->id,
            'approved_at' => now(),
        ]);

        AuditLog::query()->create([
            'event_type' => 'config_changed',
            'user_id' => $by->id,
            'route' => request()->path(),
            'payload' => [
                'target' => 'portable_device_create',
                'portable_device_id' => $device->id,
                'worker_id' => $worker->id,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        return $device;
    }

    public function revoke(PortableDevice $device, string $reason, User $by): PortableDevice
    {
        if ($device->status === PortableDeviceStatus::Revoked) {
            throw new HttpException(409, 'Device already revoked.');
        }

        $device->forceFill([
            'status' => PortableDeviceStatus::Revoked,
            'revoked_by' => $by->id,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ])->save();

        AuditLog::query()->create([
            'event_type' => 'config_changed',
            'user_id' => $by->id,
            'route' => request()->path(),
            'payload' => [
                'target' => 'portable_device_revoke',
                'portable_device_id' => $device->id,
            ],
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        return $device->fresh() ?? $device;
    }
}
