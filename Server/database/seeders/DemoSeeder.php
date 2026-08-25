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
 * LAN IPs, RTSP, and Jetson ROI URLs follow site-network.md + SCC-SETUP §13.
 * Device UUID + tokens: database/data/device_credentials.php.
 *
 * Idempotent: skips when AST-POLE-01 already exists.
 */
final class DemoSeeder extends Seeder
{
    private const POLE_COUNT = 4;

    private const RTSP_CREDENTIAL = 'admin:Unity@320@';

    /** Pole number → VLAN third octet (site-network.md). */
    private const POLE_SUBNETS = [
        1 => 3,
        2 => 2,
        3 => 1,
        4 => 4,
    ];

    /** Standard last octets on each pole VLAN. */
    private const HOST_JETSON = 2;

    private const HOST_JETSON_POLE3 = 50;

    private const HOST_PTZ = 10;

    private const HOST_BULLET = 11;

    private const HOST_RFID = 12;

    private const HOST_SCC = 40;

    private const ROI_PORT = 8600;

    private const IR4_PORT = 9100;

    /** Main gate devices on SCC2 VLAN 3 (commissioning — confirm on site). */
    private const GATE_CAMERA_IP = '172.16.3.13';

    private const GATE_RFID_IP = '172.16.3.14';

    /** Zebra ZT411 on SCC2 LAN (DOC-13). */
    private const QR_PRINTER_HOST = '172.16.3.41';

    private const QR_PRINTER_PORT = 9100;

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
        $this->seedSccInfrastructure();
        $this->seedWorkers();
        $this->seedEquipment();
        $this->printEdgeCredentials();

        $this->command?->info('Initial site registry ready.');
    }

    private function seedUsers(): void
    {
        $this->admin = User::query()->role('Super Admin')->first()
            ?? User::factory()->withRole('Super Admin')->create([
                'name' => 'Super Admin',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('password'),
                'must_change_password' => true,
            ]);

        if (! app()->environment('production')) {
            User::query()->where('email', 'safety@gmail.com')->first()
                ?? User::factory()->withRole('Safety Manager')->create([
                    'name' => 'Safety Manager',
                    'email' => 'safety@gmail.com',
                    'password' => Hash::make('password'),
                    'must_change_password' => true,
                ]);

            $this->operator = User::query()->where('email', 'operator@gmail.com')->first()
                ?? User::factory()->withRole('SCC Operator')->create([
                    'name' => 'SCC Operator',
                    'email' => 'operator@gmail.com',
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
            ['key' => 'gate', 'name' => 'Main Gate', 'type' => ZoneType::Gate, 'color' => '#38BDF8'],
            ['key' => 'muster', 'name' => 'Muster Point A', 'type' => ZoneType::MusterPoint, 'color' => '#34D399'],
            ['key' => 1, 'name' => 'Pole 01 Work', 'type' => ZoneType::Work, 'color' => '#64748B'],
            ['key' => 2, 'name' => 'Pole 02 Work', 'type' => ZoneType::Work, 'color' => '#64748B'],
            ['key' => 3, 'name' => 'Pole 03 Laydown', 'type' => ZoneType::Laydown, 'color' => '#F5A524'],
            ['key' => 4, 'name' => 'Pole 04 Height Work', 'type' => ZoneType::HeightWork, 'color' => '#F97316'],
        ];

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
        for ($n = 1; $n <= self::POLE_COUNT; $n++) {
            $pad = sprintf('%02d', $n);
            $zone = $this->zones->get($n);
            $label = "Pole {$pad}";
            $fixedCamRef = "CAM-FIXED-{$pad}";
            $ptzCamRef = "CAM-PTZ-{$pad}";
            $jetsonIp = $this->poleJetsonIp($n);
            $ir4Base = $this->poleIr4BaseUrl($n);
            $roiApi = $this->jetsonRoiApiUrl($n);

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
                    'hostname' => "pole-{$pad}",
                    'lan_ip' => $this->poleIp($n, self::HOST_RFID),
                    'mqtt_topic' => "zebra/fxr90-{$pad}/tags",
                    'ir4_base_url' => $ir4Base,
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
                    'hostname' => $jetsonIp,
                    'modbus_gateway' => $jetsonIp,
                    'modbus_slaves' => [1, 2, 3, 4, 5],
                    'ir4_base_url' => $ir4Base,
                ],
            ]);

            if ($n === 1) {
                $this->createDevice('DEV-ENV-01', 'environmental', [
                    'asset_id' => $asset->id,
                    'name' => 'SCC Environmental Sensor',
                    'serial_number' => 'SN-ENV-01',
                    'device_type' => DeviceType::EnvironmentalSensor,
                    'status' => HardwareStatus::Offline,
                    'config' => [
                        'hostname' => $jetsonIp,
                        'rs485_gateway' => $jetsonIp,
                        'ir4_base_url' => $ir4Base,
                        'scope' => 'scc_site',
                    ],
                ]);
            }

            $this->createCamera($fixedCamRef, 'camera', [
                'asset_id' => $asset->id,
                'name' => "{$label} Fixed Camera",
                'serial_number' => "SN-CAM-FIXED-{$pad}",
                'camera_type' => CameraType::Fixed,
                'stream_url' => $this->hikvisionRtsp($this->poleIp($n, self::HOST_BULLET)),
                'ai_enabled' => true,
                'status' => HardwareStatus::Offline,
                'api_url' => $roiApi,
                'config' => [
                    'jetson_ip' => $this->poleJetsonIp($n),
                    'ir4_base_url' => $ir4Base,
                    'role' => 'ppe',
                ],
                'meta' => [
                    'role' => 'ppe',
                    'camera_ip' => $this->poleIp($n, self::HOST_BULLET),
                ],
            ]);

            $this->createCamera($ptzCamRef, 'camera', [
                'asset_id' => $asset->id,
                'name' => "{$label} PTZ Camera",
                'serial_number' => "SN-CAM-PTZ-{$pad}",
                'camera_type' => CameraType::Ptz,
                'stream_url' => $this->hikvisionRtsp($this->poleIp($n, self::HOST_PTZ)),
                'ai_enabled' => true,
                'status' => HardwareStatus::Offline,
                'api_url' => $roiApi,
                'config' => [
                    'jetson_ip' => $this->poleJetsonIp($n),
                    'ir4_base_url' => $ir4Base,
                    'role' => 'overview',
                ],
                'meta' => [
                    'role' => 'overview',
                    'camera_ip' => $this->poleIp($n, self::HOST_PTZ),
                ],
            ]);
        }
    }

    private function seedGate(): void
    {
        $gateZone = $this->zones->get('gate');
        $ir4Base = $this->poleIr4BaseUrl(1);
        $roiApi = $this->jetsonRoiApiUrl(1);
        $gateCamRef = 'CAM-GATE-FIXED';

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
            'config' => [
                'lan_ip' => self::GATE_RFID_IP,
                'mqtt_topic' => 'zebra/fxr90-gate/tags',
                'ir4_base_url' => $ir4Base,
            ],
        ]);

        ReaderZoneBinding::query()->create([
            'device_id' => $reader->id,
            'zone_id' => $gateZone->id,
            'bound_from' => now(),
            'bound_until' => null,
            'bound_by' => $this->admin->id,
            'note' => 'Main Gate entry/exit binding',
        ]);

        $this->createCamera($gateCamRef, 'camera', [
            'asset_id' => $gate->id,
            'name' => 'Main Gate Fixed Camera',
            'serial_number' => 'SN-CAM-GATE',
            'camera_type' => CameraType::Fixed,
            'stream_url' => $this->hikvisionRtsp(self::GATE_CAMERA_IP),
            'ai_enabled' => true,
            'status' => HardwareStatus::Offline,
            'api_url' => $roiApi,
            'config' => [
                'jetson_ip' => $this->poleJetsonIp(1),
                'ir4_base_url' => $ir4Base,
            ],
            'meta' => ['camera_ip' => self::GATE_CAMERA_IP],
        ]);
    }

    private function seedSccInfrastructure(): void
    {
        $scc = Asset::query()->create([
            'asset_type' => AssetType::SccServer,
            'name' => 'SCC2 Command Server',
            'identifier' => 'AST-SCC2-01',
            'status' => AssetStatus::Active,
            'is_mobile' => false,
            'current_location_label' => 'SCC2 · 172.16.3.40',
        ]);

        Device::query()->create([
            'asset_id' => $scc->id,
            'name' => 'SCC2 QR Label Printer',
            'reference' => 'DEV-QR-SCC2',
            'serial_number' => 'SN-ZT411-SCC2',
            'device_type' => DeviceType::QrPrinter,
            'status' => HardwareStatus::Online,
            'printer_host' => self::QR_PRINTER_HOST,
            'printer_port' => self::QR_PRINTER_PORT,
            'config' => [
                'model' => 'ZT411',
                'ir4_base_url' => $this->poleIr4BaseUrl(1),
            ],
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

    private function poleSubnet(int $pole): int
    {
        return self::POLE_SUBNETS[$pole];
    }

    private function poleIp(int $pole, int $hostOctet): string
    {
        if ($hostOctet === self::HOST_JETSON && $pole === 3) {
            return sprintf('172.16.%d.%d', $this->poleSubnet($pole), self::HOST_JETSON_POLE3);
        }

        return sprintf('172.16.%d.%d', $this->poleSubnet($pole), $hostOctet);
    }

    private function poleJetsonIp(int $pole): string
    {
        return $this->poleIp($pole, self::HOST_JETSON);
    }

    private function poleIr4BaseUrl(int $pole): string
    {
        return sprintf(
            'http://%s:%d',
            $this->poleIp($pole, self::HOST_SCC),
            self::IR4_PORT,
        );
    }

    private function jetsonRoiApiUrl(int $pole): string
    {
        return sprintf(
            'http://%s:%d/rois',
            $this->poleJetsonIp($pole),
            self::ROI_PORT,
        );
    }

    private function hikvisionRtsp(string $ip): string
    {
        return sprintf(
            'rtsp://%s@%s:554/Streaming/Channels/101',
            self::RTSP_CREDENTIAL,
            $ip,
        );
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCamera(string $ref, string $type, array $attributes): Device
    {
        $committed = EdgeDeviceCredentials::find($ref);
        $token = $committed['token'] ?? Str::random(48);
        $payload = array_merge($attributes, [
            'reference' => $ref,
            'device_type' => DeviceType::Camera,
            'api_token_hash' => hash('sha256', $token),
            'token_issued_at' => now(),
        ]);
        if ($committed !== null) {
            $payload['uuid'] = $committed['uuid'];
        }

        $camera = Device::query()->cameras()->create($payload);
        $this->recordCredential($camera->reference, $camera->uuid, $token, $type);

        return $camera;
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
        $this->command?->info('Poles 1–4: RFID .12 · gas on Jetson · one SCC env sensor (pole 1) · CAM bullet .11 / PTZ .10 · ROI :8600/rois · IR4 :9100.');
    }
}
