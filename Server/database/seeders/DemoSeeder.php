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
use App\Models\CameraZoneBinding;
use App\Models\Device;
use App\Models\Equipment;
use App\Models\ReaderZoneBinding;
use App\Models\RfidTag;
use App\Models\User;
use App\Models\Worker;
use App\Models\Zone;
use App\Support\EdgeDeviceCredentials;
use App\Support\SiteRfidTags;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Initial site registry (baseline hardware for first install).
 * Device UUID + tokens come from database/data/device_credentials.php.
 *
 * Pole set via `IR4_SEED_POLES` (comma list). Defaults to SCC2 `1,2,3,4`.
 * SCC1: set `IR4_SEED_POLES=5,6,7,8` (or `ir4:install --poles=5,6,7,8`).
 * Each pole: RFID, gas, fixed + PTZ cameras, two edge_compute AI devices.
 * Also: main gate, starter workers / tags, equipment.
 * Idempotent: skips when `AST-POLE-{first}` already exists.
 */
final class DemoSeeder extends Seeder
{
    /**
     * Pole number → VLAN 3rd octet (site-network.md). RTSP password Unity@320@.
     *
     * @var array<int, int>
     */
    private const POLE_SUBNETS = [
        1 => 3,
        2 => 2,
        3 => 1,
        4 => 4,
        5 => 5,
        6 => 6,
        7 => 7,
        8 => 8,
    ];

    private User $admin;

    private User $operator;

    /** @var Collection<int|string, Zone> */
    private Collection $zones;

    /** @var list<int> */
    private array $poles = [];

    /** @var list<array{ref: string, uuid: string, token: string, type: string}> */
    private array $issuedCredentials = [];

    public function run(): void
    {
        $this->poles = $this->resolvePoles();
        $first = $this->poles[0];
        $firstId = sprintf('AST-POLE-%02d', $first);

        if (Asset::query()->where('identifier', $firstId)->exists()) {
            $this->command?->warn("Site registry already present ({$firstId}). Skipping.");

            return;
        }

        $list = implode(',', $this->poles);
        $this->command?->info("Seeding site registry for poles [{$list}]…");

        $this->seedUsers();
        $this->seedZones();
        $this->seedPolesAndDevices();
        // Main Gate is SCC2 site pattern — skip on SCC1-only (poles 5–8).
        if ($this->includesScc2Poles()) {
            $this->seedGate();
        }
        $this->seedWorkers();
        $this->seedEquipment();
        $this->printEdgeCredentials();

        $this->command?->info('Initial site registry ready.');
    }

    /**
     * @return list<int>
     */
    private function resolvePoles(): array
    {
        $raw = trim((string) env('IR4_SEED_POLES', '1,2,3,4'));
        $poles = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            $n = (int) $part;
            if ($n < 1 || $n > 8 || ! isset(self::POLE_SUBNETS[$n])) {
                throw new \InvalidArgumentException("IR4_SEED_POLES invalid pole: {$part} (allowed 1–8)");
            }
            $poles[] = $n;
        }
        if ($poles === []) {
            throw new \InvalidArgumentException('IR4_SEED_POLES must list at least one pole');
        }

        return array_values(array_unique($poles));
    }

    private function includesScc2Poles(): bool
    {
        return array_intersect($this->poles, [1, 2, 3, 4]) !== [];
    }

    private function seedUsers(): void
    {
        $this->admin = User::query()->role('Super Admin')->first()
            ?? User::factory()->withRole('Super Admin')->create([
                'name' => 'Super Admin',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('12345677'),
                'must_change_password' => true,
            ]);

        // Local/staging convenience accounts only — install already creates Super Admin.
        if (! app()->environment('production')) {
            User::query()->where('email', 'safety@gmail.com')->first()
                ?? User::factory()->withRole('Safety Manager')->create([
                    'name' => 'Safety Manager',
                    'email' => 'safety@gmail.com',
                    'password' => Hash::make('12345677'),
                    'must_change_password' => true,
                ]);

            $this->operator = User::query()->where('email', 'operator@gmail.com')->first()
                ?? User::factory()->withRole('SCC Operator')->create([
                    'name' => 'SCC Operator',
                    'email' => 'operator@gmail.com',
                    'password' => Hash::make('12345677'),
                    'must_change_password' => true,
                ]);
        } else {
            $this->operator = $this->admin;
        }
    }

    private function seedZones(): void
    {
        $defs = [
            ['key' => 'muster', 'name' => 'Muster Point A', 'type' => ZoneType::MusterPoint, 'color' => '#34D399'],
        ];
        if ($this->includesScc2Poles()) {
            array_unshift($defs, ['key' => 'gate', 'name' => 'Main Gate', 'type' => ZoneType::Gate, 'color' => '#38BDF8']);
        }

        $zoneMeta = [
            1 => ['name' => 'Pole 01 Work', 'type' => ZoneType::Work, 'color' => '#64748B'],
            2 => ['name' => 'Pole 02 Work', 'type' => ZoneType::Work, 'color' => '#64748B'],
            3 => ['name' => 'Pole 03 Laydown', 'type' => ZoneType::Laydown, 'color' => '#F5A524'],
            4 => ['name' => 'Pole 04 Height Work', 'type' => ZoneType::HeightWork, 'color' => '#F97316'],
            5 => ['name' => 'Pole 05 Work', 'type' => ZoneType::Work, 'color' => '#64748B'],
            6 => ['name' => 'Pole 06 Work', 'type' => ZoneType::Work, 'color' => '#64748B'],
            7 => ['name' => 'Pole 07 Laydown', 'type' => ZoneType::Laydown, 'color' => '#F5A524'],
            8 => ['name' => 'Pole 08 Height Work', 'type' => ZoneType::HeightWork, 'color' => '#F97316'],
        ];

        foreach ($this->poles as $n) {
            $meta = $zoneMeta[$n];
            $defs[] = ['key' => $n, 'name' => $meta['name'], 'type' => $meta['type'], 'color' => $meta['color']];
        }

        $this->zones = collect();
        foreach ($defs as $def) {
            $zone = Zone::query()->create([
                'name' => $def['name'],
                'zone_type' => $def['type'],
                'requires_authorization' => $def['type'] === ZoneType::RestrictedRed || $def['type'] === ZoneType::HeightWork,
                'occupancy_limit' => null,
                'color' => $def['color'],
                'is_active' => true,
                'created_by' => $this->admin->id,
            ]);
            $this->zones->put($def['key'], $zone);
        }
    }

    private function seedPolesAndDevices(): void
    {
        foreach ($this->poles as $n) {
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

            $rfid = $this->createDevice("DEV-RFID-{$pad}", 'rfid', [
                'asset_id' => $asset->id,
                'name' => "{$label} RFID Reader",
                'serial_number' => "SN-RFID-{$pad}",
                'device_type' => DeviceType::RfidReader,
                'status' => HardwareStatus::Offline,
                'config' => [
                    'hostname' => $hostname,
                    'mqtt_topic' => "zebra/fxr90-{$pad}/tags",
                ],
            ]);

            ReaderZoneBinding::query()->create([
                'device_id' => $rfid->id,
                'zone_id' => $zone->id,
                'bound_from' => now(),
                'bound_until' => null,
                'bound_by' => $this->admin->id,
                'note' => "{$label} work-zone binding",
            ]);

            $this->createDevice("DEV-GAS-{$pad}", 'gas', [
                'asset_id' => $asset->id,
                'name' => "{$label} Gas Detector",
                'serial_number' => "SN-GAS-{$pad}",
                'device_type' => DeviceType::GasDetector,
                'status' => HardwareStatus::Offline,
                'config' => [
                    'hostname' => $hostname,
                    'modbus_slaves' => [1, 2, 3, 4, 5],
                ],
            ]);

            // Camera AI ingest (DOC-08) — EdgeCompute typed, named as cameras.
            $fixedCamDevice = $this->createDevice("DEV-CAM-FIXED-{$pad}", 'cam_ai', [
                'asset_id' => $asset->id,
                'name' => "{$label} Fixed Camera",
                'serial_number' => "SN-CAM-FIXED-{$pad}",
                'device_type' => DeviceType::EdgeCompute,
                'status' => HardwareStatus::Offline,
                'config' => [
                    'hostname' => $hostname,
                    'api_url' => 'http://'.$this->poleJetsonHost($n).':8600/rois',
                    'camera_ref' => $fixedCamRef,
                    'role' => 'ppe',
                ],
            ]);

            $ptzCamDevice = $this->createDevice("DEV-CAM-PTZ-{$pad}", 'cam_ai', [
                'asset_id' => $asset->id,
                'name' => "{$label} PTZ Camera",
                'serial_number' => "SN-CAM-PTZ-{$pad}",
                'device_type' => DeviceType::EdgeCompute,
                'status' => HardwareStatus::Offline,
                'config' => [
                    'hostname' => $hostname,
                    'api_url' => 'http://'.$this->poleJetsonHost($n).':8600/rois',
                    'camera_ref' => $ptzCamRef,
                    'role' => 'overview',
                ],
            ]);

            // Stream registry — real Hikvision RTSP (SCC-SETUP §13). .11 bullet, .10 PTZ.
            // 1:1 device↔camera (DOC-23): processed_by_device_id = this camera's AI device.
            $fixedCam = Camera::query()->create([
                'asset_id' => $asset->id,
                'name' => "{$label} Fixed Camera",
                'reference' => $fixedCamRef,
                'camera_type' => CameraType::Fixed,
                'stream_url' => $this->poleStreamUrl($n, 11),
                'processed_by_device_id' => $fixedCamDevice->id,
                'ai_enabled' => true,
                'status' => HardwareStatus::Offline,
                'meta' => ['role' => 'ppe'],
            ]);
            $ptzCam = Camera::query()->create([
                'asset_id' => $asset->id,
                'name' => "{$label} PTZ Camera",
                'reference' => $ptzCamRef,
                'camera_type' => CameraType::Ptz,
                'stream_url' => $this->poleStreamUrl($n, 10),
                'processed_by_device_id' => $ptzCamDevice->id,
                'ai_enabled' => false,
                'status' => HardwareStatus::Offline,
                'meta' => ['role' => 'overview'],
            ]);

            // Same pole zone as RFID — required for live camera headcount by_zone (DOC-09).
            foreach ([$fixedCam, $ptzCam] as $camera) {
                CameraZoneBinding::query()->create([
                    'camera_id' => $camera->id,
                    'zone_id' => $zone->id,
                    'bound_from' => now(),
                    'bound_until' => null,
                    'bound_by' => $this->admin->id,
                    'note' => "{$label} camera headcount binding",
                ]);
            }
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

        $reader = $this->createDevice('DEV-RFID-GATE', 'rfid', [
            'asset_id' => $gate->id,
            'name' => 'Main Gate RFID Reader',
            'serial_number' => 'SN-RFID-GATE',
            'device_type' => DeviceType::RfidReader,
            'status' => HardwareStatus::Offline,
        ]);

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
        $epcs = SiteRfidTags::all();
        $workers = [
            [
                'name' => 'Ahmed Al-Rashid',
                'employee_code' => 'EMP-0001',
                'badge_number' => 'BDG-0001',
                'role_title' => 'Foreman',
                'contractor' => 'Owner / EPC',
            ],
            [
                'name' => 'Sara Nguyen',
                'employee_code' => 'EMP-0002',
                'badge_number' => 'BDG-0002',
                'role_title' => 'Safety Officer',
                'contractor' => 'Owner / EPC',
            ],
            [
                'name' => 'Omar Hassan',
                'employee_code' => 'EMP-0003',
                'badge_number' => 'BDG-0003',
                'role_title' => 'Technician',
                'contractor' => 'Owner / EPC',
            ],
        ];

        foreach ($workers as $i => $row) {
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

            $epc = $epcs[$i] ?? null;
            if ($epc === null) {
                continue;
            }

            RfidTag::query()->create([
                'tag_uid' => $epc,
                'worker_id' => $worker->id,
                'status' => TagStatus::Assigned,
                'assigned_at' => now(),
                'assigned_by' => $this->operator->id,
            ]);
        }

        foreach (array_slice($epcs, count($workers)) as $epc) {
            RfidTag::query()->create([
                'tag_uid' => $epc,
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

    private function poleStreamUrl(int $pole, int $host): string
    {
        $subnet = self::POLE_SUBNETS[$pole];

        return sprintf(
            'rtsp://admin:Unity@320@@172.16.%d.%d:554/Streaming/Channels/101',
            $subnet,
            $host,
        );
    }

    /** Jetson J4012 LAN IP (site-network.md) — AI service listens on :8600. */
    private function poleJetsonHost(int $pole): string
    {
        $subnet = self::POLE_SUBNETS[$pole];
        // Pole 3 Jetson is .50; others .2.
        $host = $pole === 3 ? 50 : 2;

        return sprintf('172.16.%d.%d', $subnet, $host);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDevice(string $ref, string $type, array $attributes): Device
    {
        $committed = EdgeDeviceCredentials::find($ref);
        $token = $committed['token'] ?? Str::random(48);
        $payload = array_merge($attributes, [
            'reference' => $ref,
            'api_token_hash' => hash('sha256', $token),
            'token_issued_at' => now(),
        ]);
        if ($committed !== null) {
            $payload['uuid'] = $committed['uuid'];
        }

        $device = Device::query()->create($payload);
        $this->recordCredential($device->reference, $device->uuid, $token, $type);

        return $device;
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
        $this->command?->warn('Device credentials (ir4-edge secrets --pole N copies these into secrets.env):');
        $this->command?->table(
            ['Type', 'Reference', 'UUID', 'Token', 'Notes'],
            collect($this->issuedCredentials)->map(function (array $row): array {
                $notes = EdgeDeviceCredentials::find($row['ref'])['notes'] ?? $row['type'];

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
