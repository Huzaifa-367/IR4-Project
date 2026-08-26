<?php

namespace Database\Seeders;

use App\Enums\DeviceType;
use App\Models\Camera;
use App\Models\CameraZoneBinding;
use App\Models\Device;
use App\Models\ReaderZoneBinding;
use Illuminate\Database\Seeder;

/**
 * Idempotent: bind cameras that have no open zone binding to the same zone
 * as the RFID reader on their pole asset (DOC-09 headcount parity).
 */
final class CameraZoneBindingSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;

        Camera::query()
            ->whereDoesntHave('currentZoneBinding')
            ->orderBy('id')
            ->each(function (Camera $camera) use (&$created): void {
                $readerIds = Device::query()
                    ->where('asset_id', $camera->asset_id)
                    ->where('device_type', DeviceType::RfidReader)
                    ->pluck('id');

                if ($readerIds->isEmpty()) {
                    return;
                }

                $zoneId = ReaderZoneBinding::query()
                    ->whereIn('device_id', $readerIds)
                    ->whereNull('bound_until')
                    ->orderByDesc('bound_from')
                    ->value('zone_id');

                if ($zoneId === null) {
                    return;
                }

                CameraZoneBinding::query()->create([
                    'camera_id' => $camera->id,
                    'zone_id' => (int) $zoneId,
                    'bound_from' => now(),
                    'bound_until' => null,
                    'bound_by' => null,
                    'note' => 'Seeded from sibling RFID reader zone',
                ]);
                $created++;
            });

        if ($created > 0) {
            $this->command?->info("Camera zone bindings created: {$created}");
        }
    }
}
