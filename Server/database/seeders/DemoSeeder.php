<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\CameraType;
use App\Enums\DeviceType;
use App\Enums\EquipmentStatus;
use App\Enums\HardwareStatus;
use App\Enums\TagStatus;
use App\Enums\WorkerType;
use App\Enums\ZoneType;
use App\Models\Asset;
use App\Models\Camera;
use App\Models\Device;
use App\Models\Equipment;
use App\Models\ReaderZoneBinding;
use App\Models\RfidTag;
use App\Models\User;
use App\Models\Worker;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Initial site registry (baseline hardware for first install).
 *
 * Poles 1–4: RFID, gas, fixed + PTZ stream cameras, and two edge_compute
 * camera-AI devices each. Also: main gate, starter workers + tags, spare
 * tags, and equipment. More can be added via the operator UI after install.
 * Idempotent: skips when AST-POLE-01 already exists.
 */
final class DemoSeeder extends Seeder
{
    private const POLE_COUNT = 4;

    private User $admin;

    private User $operator;

    /** @var Collection<int|string, Zone> */
    private Collection $zones;

    /** @var list<array{ref: string, uuid: string, token: string, type: string}> */
    private array $issuedCredentials = [];

    public function run(): void
    {
        if (Asset::query()->where('identifier', 'AST-POLE-01')->exists()) {
            $this->command?->warn('Site registry already present (AST-POLE-01). Skipping.');

            return;
        }

        $this->command?->info('Seeding initial site registry (poles, devices, cameras, workers, equipment)…');

        $this->seedUsers();
        $this->seedZones();
        $this->seedPolesAndDevices();
        $this->seedGate();
        $this->seedWorkers();
        $this->seedSpareTags();
        $this->seedEquipment();
        $this->printEdgeCredentials();

        $this->command?->info('Initial site registry ready.');
    }

    private function seedUsers(): void
    {
        $this->admin = User::query()->role('Super Admin')->first()
            ?? User::factory()->withRole('Super Admin')->create([
                'name' => 'Super Admin',
                'email' => 'admin@ir4.local',
                'password' => Hash::make('password'),
                'must_change_password' => true,
            ]);

        // Local/staging convenience accounts only — install already creates Super Admin.
        if (! app()->environment('production')) {
            User::query()->where('email', 'safety@ir4.local')->first()
                ?? User::factory()->withRole('Safety Manager')->create([
                    'name' => 'Safety Manager',
                    'email' => 'safety@ir4.local',
                    'password' => Hash::make('password'),
                    'must_change_password' => true,
                ]);

            $this->operator = User::query()->where('email', 'operator@ir4.local')->first()
                ?? User::factory()->withRole('SCC Operator')->create([
                    'name' => 'SCC Operator',
                    'email' => 'operator@ir4.local',
                    'password' => Hash::make('password'),
                    'must_change_password' => true,
                ]);
        } else {
            $this->operator = $this->admin;
        }
    }

    private function seedZones(): void
    {
        $defs = [
            ['key' => 'gate', 'name' => 'Main Gate', 'type' => ZoneType::Gate, 'lat' => 27.015154, 'lng' => 49.619475, 'rm' => 48.0, 'color' => '#38BDF8'],
            ['key' => 'muster', 'name' => 'Muster Point A', 'type' => ZoneType::MusterPoint, 'lat' => 27.015783, 'lng' => 49.620987, 'rm' => 64.0, 'color' => '#34D399'],
            ['key' => 1, 'name' => 'Pole 01 Work', 'type' => ZoneType::Work, 'lat' => 27.018478, 'lng' => 49.623004, 'rm' => 80.0, 'color' => '#64748B'],
            ['key' => 2, 'name' => 'Pole 02 Work', 'type' => ZoneType::Work, 'lat' => 27.016951, 'lng' => 49.624718, 'rm' => 80.0, 'color' => '#64748B'],
            ['key' => 3, 'name' => 'Pole 03 Laydown', 'type' => ZoneType::Laydown, 'lat' => 27.015603, 'lng' => 49.626332, 'rm' => 80.0, 'color' => '#F5A524'],
            ['key' => 4, 'name' => 'Pole 04 Height Work', 'type' => ZoneType::HeightWork, 'lat' => 27.019915, 'lng' => 49.623508, 'rm' => 72.0, 'color' => '#F97316'],
        ];

        $this->zones = collect();
        foreach ($defs as $def) {
            $zone = Zone::query()->create([
                'name' => $def['name'],
                'zone_type' => $def['type'],
                'requires_authorization' => $def['type'] === ZoneType::RestrictedRed || $def['type'] === ZoneType::HeightWork,
                'occupancy_limit' => null,
                'latitude' => $def['lat'],
                'longitude' => $def['lng'],
                'radius_meters' => $def['rm'],
                'color' => $def['color'],
                'is_active' => true,
                'created_by' => $this->admin->id,
            ]);
            $this->zones->put($def['key'], $zone);
        }
    }

    private function seedPolesAndDevices(): void
    {
        for ($n = 1; $n <= self::POLE_COUNT; $n++) {
            $pad = sprintf('%02d', $n);
            $zone = $this->zones->get($n);
            $label = "Pole {$pad}";
            $hostname = "pole-{$pad}";
            $fixedCamRef = "CAM-FIXED-{$pad}";
            $ptzCamRef = "CAM-PTZ-{$pad}";

            $asset = Asset::query()->create([
                'asset_type' => AssetType::Pole,
                'name' => $label,
                'identifier' => "AST-POLE-{$pad}",
                'status' => AssetStatus::Active,
                'is_mobile' => true,
                'current_location_label' => $zone->name,
            ]);

            $rfidToken = $this->issueToken();
            $rfid = Device::query()->create([
                'asset_id' => $asset->id,
                'name' => "{$label} RFID Reader",
                'reference' => "DEV-RFID-{$pad}",
                'serial_number' => "SN-RFID-{$pad}",
                'device_type' => DeviceType::RfidReader,
                'status' => HardwareStatus::Offline,
                'api_token_hash' => hash('sha256', $rfidToken),
                'token_issued_at' => now(),
                'config' => [
                    'hostname' => $hostname,
                    'mqtt_topic' => "zebra/fxr90-{$pad}/tags",
                ],
            ]);
            $this->recordCredential($rfid->reference, $rfid->uuid, $rfidToken, 'rfid');

            ReaderZoneBinding::query()->create([
                'device_id' => $rfid->id,
                'zone_id' => $zone->id,
                'bound_from' => now(),
                'bound_until' => null,
                'bound_by' => $this->admin->id,
                'note' => "{$label} work-zone binding",
            ]);

            $gasToken = $this->issueToken();
            $gas = Device::query()->create([
                'asset_id' => $asset->id,
                'name' => "{$label} Gas Detector",
                'reference' => "DEV-GAS-{$pad}",
                'serial_number' => "SN-GAS-{$pad}",
                'device_type' => DeviceType::GasDetector,
                'status' => HardwareStatus::Offline,
                'api_token_hash' => hash('sha256', $gasToken),
                'token_issued_at' => now(),
                'config' => [
                    'hostname' => $hostname,
                    'modbus_slaves' => [1, 2, 3, 4, 5],
                ],
            ]);
            $this->recordCredential($gas->reference, $gas->uuid, $gasToken, 'gas');

            // Camera AI ingest (DOC-08) — EdgeCompute typed, named as cameras.
            $fixedAiToken = $this->issueToken();
            $fixedAi = Device::query()->create([
                'asset_id' => $asset->id,
                'name' => "{$label} Fixed Camera",
                'reference' => "DEV-CAM-FIXED-{$pad}",
                'serial_number' => "SN-CAM-FIXED-{$pad}",
                'device_type' => DeviceType::EdgeCompute,
                'status' => HardwareStatus::Offline,
                'api_token_hash' => hash('sha256', $fixedAiToken),
                'token_issued_at' => now(),
                'config' => [
                    'hostname' => $hostname,
                    'camera_ref' => $fixedCamRef,
                    'role' => 'ppe',
                ],
            ]);
            $this->recordCredential($fixedAi->reference, $fixedAi->uuid, $fixedAiToken, 'cam_ai');

            $ptzAiToken = $this->issueToken();
            $ptzAi = Device::query()->create([
                'asset_id' => $asset->id,
                'name' => "{$label} PTZ Camera",
                'reference' => "DEV-CAM-PTZ-{$pad}",
                'serial_number' => "SN-CAM-PTZ-{$pad}",
                'device_type' => DeviceType::EdgeCompute,
                'status' => HardwareStatus::Offline,
                'api_token_hash' => hash('sha256', $ptzAiToken),
                'token_issued_at' => now(),
                'config' => [
                    'hostname' => $hostname,
                    'camera_ref' => $ptzCamRef,
                    'role' => 'overview',
                ],
            ]);
            $this->recordCredential($ptzAi->reference, $ptzAi->uuid, $ptzAiToken, 'cam_ai');

            // Stream registry (Camera rows).
            Camera::query()->create([
                'asset_id' => $asset->id,
                'name' => "{$label} Fixed Camera",
                'reference' => $fixedCamRef,
                'camera_type' => CameraType::Fixed,
                'stream_url' => sprintf('rtsp://10.20.%d.10/Streaming/Channels/101', 20 + $n),
                'ai_enabled' => true,
                'status' => HardwareStatus::Offline,
                'meta' => ['role' => 'ppe'],
            ]);
            Camera::query()->create([
                'asset_id' => $asset->id,
                'name' => "{$label} PTZ Camera",
                'reference' => $ptzCamRef,
                'camera_type' => CameraType::Ptz,
                'stream_url' => sprintf('rtsp://10.20.%d.11/Streaming/Channels/101', 20 + $n),
                'ai_enabled' => false,
                'status' => HardwareStatus::Offline,
                'meta' => ['role' => 'overview'],
            ]);
        }
    }

    private function seedGate(): void
    {
        $gateZone = $this->zones->get('gate');
        $gate = Asset::query()->create([
            'asset_type' => AssetType::Gate,
            'name' => 'Main Gate',
            'identifier' => 'AST-GATE-01',
            'status' => AssetStatus::Active,
            'is_mobile' => false,
            'current_location_label' => $gateZone->name,
        ]);

        $token = $this->issueToken();
        $reader = Device::query()->create([
            'asset_id' => $gate->id,
            'name' => 'Main Gate RFID Reader',
            'reference' => 'DEV-RFID-GATE',
            'serial_number' => 'SN-RFID-GATE',
            'device_type' => DeviceType::RfidReader,
            'status' => HardwareStatus::Offline,
            'api_token_hash' => hash('sha256', $token),
            'token_issued_at' => now(),
        ]);
        $this->recordCredential($reader->reference, $reader->uuid, $token, 'rfid');

        ReaderZoneBinding::query()->create([
            'device_id' => $reader->id,
            'zone_id' => $gateZone->id,
            'bound_from' => now(),
            'bound_until' => null,
            'bound_by' => $this->admin->id,
            'note' => 'Main Gate entry/exit binding',
        ]);

        Camera::query()->create([
            'asset_id' => $gate->id,
            'name' => 'Main Gate Fixed Camera',
            'reference' => 'CAM-GATE-FIXED',
            'camera_type' => CameraType::Fixed,
            'stream_url' => 'rtsp://10.20.0.2/Streaming/Channels/101',
            'ai_enabled' => false,
            'status' => HardwareStatus::Offline,
        ]);
    }

    private function seedWorkers(): void
    {
        $workers = [
            [
                'name' => 'Ahmed Al-Rashid',
                'employee_code' => 'EMP-0001',
                'badge_number' => 'BDG-0001',
                'role_title' => 'Foreman',
                'contractor' => 'Owner / EPC',
                'tag_uid' => 'E280116060000203IR4W0001',
            ],
            [
                'name' => 'Sara Nguyen',
                'employee_code' => 'EMP-0002',
                'badge_number' => 'BDG-0002',
                'role_title' => 'Safety Officer',
                'contractor' => 'Owner / EPC',
                'tag_uid' => 'E280116060000203IR4W0002',
            ],
            [
                'name' => 'Omar Hassan',
                'employee_code' => 'EMP-0003',
                'badge_number' => 'BDG-0003',
                'role_title' => 'Technician',
                'contractor' => 'Owner / EPC',
                'tag_uid' => 'E280116060000203IR4W0003',
            ],
        ];

        foreach ($workers as $row) {
            $worker = Worker::query()->create([
                'name' => $row['name'],
                'employee_code' => $row['employee_code'],
                'badge_number' => $row['badge_number'],
                'contractor' => $row['contractor'],
                'role_title' => $row['role_title'],
                'worker_type' => WorkerType::Employee,
                'phone' => null,
                'notes' => null,
                'is_active' => true,
                'present' => false,
                'created_by' => $this->operator->id,
            ]);

            RfidTag::query()->create([
                'tag_uid' => $row['tag_uid'],
                'worker_id' => $worker->id,
                'status' => TagStatus::Assigned,
                'assigned_at' => now(),
                'assigned_by' => $this->operator->id,
            ]);
        }
    }

    private function seedSpareTags(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            RfidTag::query()->create([
                'tag_uid' => sprintf('E280116060000203IR4S%04d', $i),
                'worker_id' => null,
                'status' => TagStatus::InStock,
            ]);
        }
    }

    private function seedEquipment(): void
    {
        $items = [
            [
                'code' => 'EQ-FE-001',
                'name' => 'Fire Extinguisher — Main Gate',
                'type' => 'fire extinguisher',
                'location' => 'Main Gate',
                'checkoutable' => false,
            ],
            [
                'code' => 'EQ-FE-002',
                'name' => 'Fire Extinguisher — Pole 01',
                'type' => 'fire extinguisher',
                'location' => 'Pole 01 Work',
                'checkoutable' => false,
            ],
            [
                'code' => 'EQ-HAR-001',
                'name' => 'Safety Harness A',
                'type' => 'safety harness',
                'location' => 'Pole 04 Height Work',
                'checkoutable' => true,
            ],
            [
                'code' => 'EQ-HAR-002',
                'name' => 'Safety Harness B',
                'type' => 'safety harness',
                'location' => 'Pole 04 Height Work',
                'checkoutable' => true,
            ],
            [
                'code' => 'EQ-GEN-001',
                'name' => 'Portable Generator 5kVA',
                'type' => 'generator',
                'location' => 'Pole 03 Laydown',
                'checkoutable' => true,
            ],
        ];

        foreach ($items as $item) {
            Equipment::query()->create([
                'equipment_code' => $item['code'],
                'qr_token' => (string) Str::uuid(),
                'name' => $item['name'],
                'equipment_type' => $item['type'],
                'status' => EquipmentStatus::InService,
                'is_checkoutable' => $item['checkoutable'],
                'location_label' => $item['location'],
                'description' => null,
                'next_inspection_due' => now()->addMonths(3)->toDateString(),
                'created_by' => $this->operator->id,
            ]);
        }
    }

    private function issueToken(): string
    {
        return Str::random(48);
    }

    private function recordCredential(string $ref, string $uuid, string $token, string $type): void
    {
        $this->issuedCredentials[] = [
            'ref' => $ref,
            'uuid' => $uuid,
            'token' => $token,
            'type' => $type,
        ];
    }

    private function printEdgeCredentials(): void
    {
        $this->command?->newLine();
        $this->command?->warn('Device credentials (store in EdgeCompute secrets.env — shown once):');
        $this->command?->table(
            ['Type', 'Reference', 'UUID', 'Token', 'Notes'],
            collect($this->issuedCredentials)->map(function (array $row): array {
                if ($row['ref'] === 'DEV-RFID-GATE') {
                    return [$row['type'], $row['ref'], $row['uuid'], $row['token'], 'Main Gate'];
                }

                $n = (int) substr($row['ref'], -2);
                $pad = sprintf('%02d', $n);
                $notes = match ($row['type']) {
                    'rfid' => "zebra/fxr90-{$pad}/tags · pole-{$pad}",
                    'gas' => "pole-{$pad} · YT-98H slaves 1–5",
                    'cam_ai' => str_contains($row['ref'], 'FIXED')
                        ? "PPE AI · camera_ref CAM-FIXED-{$pad}"
                        : "Overview AI · camera_ref CAM-PTZ-{$pad}",
                    default => "pole-{$pad}",
                };

                return [
                    $row['type'],
                    $row['ref'],
                    $row['uuid'],
                    $row['token'],
                    $notes,
                ];
            })->all(),
        );
        $this->command?->info('Each pole: DEV-RFID / DEV-GAS / DEV-CAM-FIXED / DEV-CAM-PTZ + CAM-FIXED / CAM-PTZ streams.');
    }
}
