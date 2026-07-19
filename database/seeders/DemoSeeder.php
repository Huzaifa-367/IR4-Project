<?php

namespace Database\Seeders;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\CameraType;
use App\Enums\DeviceType;
use App\Enums\Involvement;
use App\Enums\Direction;
use App\Enums\EntryExitSource;
use App\Enums\EquipmentStatus;
use App\Enums\GasAlarmLevel;
use App\Enums\GasType;
use App\Enums\HardwareStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\InspectionOutcome;
use App\Enums\LsrCategory;
use App\Enums\LsrStatus;
use App\Enums\MaintenanceType;
use App\Enums\ReviewStatus;
use App\Enums\ScheduleType;
use App\Enums\TagStatus;
use App\Enums\ViolationType;
use App\Enums\WorkerType;
use App\Enums\ZoneType;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\Camera;
use App\Models\Device;
use App\Models\EntryExitLog;
use App\Models\EnvironmentalReading;
use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use App\Models\EquipmentInspection;
use App\Models\EquipmentMaintenance;
use App\Models\GasAlarm;
use App\Models\GasReading;
use App\Models\HseIncident;
use App\Models\IncidentPersonnel;
use App\Models\LsrViolation;
use App\Models\MaintenanceSchedule;
use App\Models\PpeViolation;
use App\Models\ReaderZoneBinding;
use App\Models\RfidTag;
use App\Models\User;
use App\Models\VehicleViolation;
use App\Models\Worker;
use App\Models\WorkerPosition;
use App\Models\Zone;
use App\Models\ZoneAccessListEntry;
use App\Services\WeeklyReportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Local/staging demo dataset: a single construction site with ~4 months of history.
 * Never run in production (DOC-05 / DOC-06).
 */
final class DemoSeeder extends Seeder
{
    private CarbonImmutable $from;

    private CarbonImmutable $to;

    private User $admin;

    private User $safetyManager;

    private User $operator;

    /** @var Collection<int, Zone> */
    private Collection $zones;

    /** @var Collection<int, Asset> */
    private Collection $assets;

    /** @var Collection<int, Device> */
    private Collection $readers;

    /** @var Collection<int, Device> */
    private Collection $gasDevices;

    /** @var Collection<int, Device> */
    private Collection $envDevices;

    /** @var Collection<int, Camera> */
    private Collection $cameras;

    /** @var Collection<int, Worker> */
    private Collection $workers;

    /** @var Collection<int, RfidTag> */
    private Collection $tags;

    /** @var Collection<int, Equipment> */
    private Collection $equipment;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder refuses to run in production.');

            return;
        }

        if (Zone::query()->where('name', 'Main Gate')->exists()) {
            $this->command?->warn('Demo site already seeded (Main Gate present). Skipping.');

            return;
        }

        $this->to = CarbonImmutable::now()->startOfMinute();
        $this->from = $this->to->subMonths(4)->startOfDay();

        $this->command?->info(sprintf(
            'Seeding demo site from %s → %s …',
            $this->from->toDateString(),
            $this->to->toDateString(),
        ));

        $this->seedUsers();
        $this->seedZones();
        $this->seedHardware();
        $this->seedWorkersAndTags();
        $this->seedBindingsAndAccess();
        $this->seedEntryExitHistory();
        $this->seedGasAndEnvironmentHistory();
        $this->seedPpeHistory();
        $this->seedAlerts();
        $this->seedEquipmentLifecycle();
        $this->seedIncidentsAndLsr();
        $this->seedVehicleViolations();
        $this->seedLivePresence();
        $this->seedWeeklyReports();

        $this->command?->info('Demo site ready. Log in as operator@ir4.local / password (or your Super Admin).');
    }

    private function seedUsers(): void
    {
        $this->admin = User::query()->role('Super Admin')->first()
            ?? User::factory()->withRole('Super Admin')->create([
                'name' => 'Super Admin',
                'email' => 'admin@ir4.local',
                'password' => Hash::make('password'),
                'must_change_password' => false,
            ]);

        $this->safetyManager = User::factory()->withRole('Safety Manager')->create([
            'name' => 'Layla Al-Harbi',
            'email' => 'safety@ir4.local',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'last_login_at' => $this->to->subHours(2),
        ]);

        $this->operator = User::factory()->withRole('SCC Operator')->create([
            'name' => 'Omar Farooq',
            'email' => 'operator@ir4.local',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'last_login_at' => $this->to->subMinutes(15),
        ]);

        User::factory()->withRole('Project Manager')->create([
            'name' => 'James Okonkwo',
            'email' => 'pm@ir4.local',
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ]);
    }

    private function seedZones(): void
    {
        // Site anchor: Jubail Industrial City, Saudi Arabia (real KSA petrochemical hub).
        $defs = [
            ['name' => 'Main Gate', 'type' => ZoneType::Gate, 'x' => 12.0, 'y' => 78.0, 'r' => 6.0, 'lat' => 27.015154, 'lng' => 49.619475, 'rm' => 48.0, 'color' => '#38BDF8', 'auth' => false, 'limit' => null],
            ['name' => 'Muster Point A', 'type' => ZoneType::MusterPoint, 'x' => 22.0, 'y' => 72.0, 'r' => 8.0, 'lat' => 27.015783, 'lng' => 49.620987, 'rm' => 64.0, 'color' => '#34D399', 'auth' => false, 'limit' => 120],
            ['name' => 'Work Area North', 'type' => ZoneType::Work, 'x' => 38.0, 'y' => 42.0, 'r' => 14.0, 'lat' => 27.018478, 'lng' => 49.623004, 'rm' => 112.0, 'color' => '#64748B', 'auth' => false, 'limit' => 80],
            ['name' => 'Work Area South', 'type' => ZoneType::Work, 'x' => 55.0, 'y' => 58.0, 'r' => 12.0, 'lat' => 27.016951, 'lng' => 49.624718, 'rm' => 96.0, 'color' => '#64748B', 'auth' => false, 'limit' => 60],
            ['name' => 'Laydown Yard', 'type' => ZoneType::Laydown, 'x' => 72.0, 'y' => 70.0, 'r' => 10.0, 'lat' => 27.015603, 'lng' => 49.626332, 'rm' => 80.0, 'color' => '#F5A524', 'auth' => false, 'limit' => 40],
            ['name' => 'Height Work Deck', 'type' => ZoneType::HeightWork, 'x' => 48.0, 'y' => 28.0, 'r' => 9.0, 'lat' => 27.019915, 'lng' => 49.623508, 'rm' => 72.0, 'color' => '#F97316', 'auth' => true, 'limit' => 25],
            ['name' => 'Hot Work Bay', 'type' => ZoneType::RestrictedRed, 'x' => 68.0, 'y' => 32.0, 'r' => 8.0, 'lat' => 27.019377, 'lng' => 49.625525, 'rm' => 64.0, 'color' => '#F0506E', 'auth' => true, 'limit' => 12],
            ['name' => 'Temporary Works', 'type' => ZoneType::Other, 'x' => 30.0, 'y' => 55.0, 'r' => 7.0, 'lat' => 27.017759, 'lng' => 49.621693, 'rm' => 56.0, 'color' => '#94A3B8', 'auth' => false, 'limit' => 30],
        ];

        $this->zones = collect();

        foreach ($defs as $def) {
            $this->zones->put($def['name'], Zone::query()->create([
                'name' => $def['name'],
                'zone_type' => $def['type'],
                'requires_authorization' => $def['auth'],
                'occupancy_limit' => $def['limit'],
                'map_x' => $def['x'],
                'map_y' => $def['y'],
                'map_radius' => $def['r'],
                'latitude' => $def['lat'],
                'longitude' => $def['lng'],
                'radius_meters' => $def['rm'],
                'color' => $def['color'],
                'is_active' => true,
                'created_by' => $this->admin->id,
                'created_at' => $this->from,
                'updated_at' => $this->from,
            ]));
        }
    }

    private function seedHardware(): void
    {
        $this->assets = collect();
        $this->readers = collect();
        $this->gasDevices = collect();
        $this->envDevices = collect();
        $this->cameras = collect();

        $poleSpecs = [
            ['name' => 'Pole P-01 North', 'loc' => 'Work Area North', 'zone' => 'Work Area North'],
            ['name' => 'Pole P-02 South', 'loc' => 'Work Area South', 'zone' => 'Work Area South'],
            ['name' => 'Pole P-03 Laydown', 'loc' => 'Laydown Yard', 'zone' => 'Laydown Yard'],
            ['name' => 'Pole P-04 Height', 'loc' => 'Height Work Deck', 'zone' => 'Height Work Deck'],
            ['name' => 'Pole P-05 Hot Work', 'loc' => 'Hot Work Bay', 'zone' => 'Hot Work Bay'],
            ['name' => 'Pole P-06 Temp', 'loc' => 'Temporary Works', 'zone' => 'Temporary Works'],
        ];

        foreach ($poleSpecs as $i => $spec) {
            $asset = Asset::query()->create([
                'asset_type' => AssetType::Pole,
                'name' => $spec['name'],
                'identifier' => sprintf('AST-POLE-%02d', $i + 1),
                'status' => AssetStatus::Active,
                'is_mobile' => true,
                'current_location_label' => $spec['loc'],
                'last_heartbeat_at' => $this->to->subMinutes(random_int(1, 20)),
                'created_at' => $this->from,
                'updated_at' => $this->to,
            ]);
            $this->assets->put($spec['name'], $asset);

            $reader = Device::query()->create([
                'asset_id' => $asset->id,
                'name' => $spec['name'].' RFID',
                'reference' => sprintf('DEV-RFID-%02d', $i + 1),
                'serial_number' => 'SN-RDR-'.(1000 + $i),
                'device_type' => DeviceType::RfidReader,
                'status' => HardwareStatus::Online,
                'api_token_hash' => hash('sha256', 'demo-reader-token-'.($i + 1)),
                'token_issued_at' => $this->from,
                'last_seen_at' => $this->to->subMinutes(random_int(1, 15)),
                'created_at' => $this->from,
                'updated_at' => $this->to,
            ]);
            $this->readers->put($spec['zone'], $reader);

            $camera = Camera::query()->create([
                'asset_id' => $asset->id,
                'name' => $spec['name'].' PPE Cam',
                'reference' => sprintf('cam-ppe-%02d', $i + 1),
                'camera_type' => CameraType::Fixed,
                'stream_url' => sprintf('rtsp://10.20.0.%d/stream1', 20 + $i),
                'ai_enabled' => true,
                'status' => HardwareStatus::Online,
                'last_frame_at' => $this->to->subSeconds(random_int(5, 90)),
                'created_at' => $this->from,
                'updated_at' => $this->to,
            ]);
            $this->cameras->push($camera);
        }

        $gateAsset = Asset::query()->create([
            'asset_type' => AssetType::Gate,
            'name' => 'Main Gate Barrier',
            'identifier' => 'AST-GATE-01',
            'status' => AssetStatus::Active,
            'is_mobile' => false,
            'current_location_label' => 'Main Gate',
            'last_heartbeat_at' => $this->to->subMinutes(2),
            'created_at' => $this->from,
            'updated_at' => $this->to,
        ]);
        $this->assets->put('Main Gate Barrier', $gateAsset);

        $gateReader = Device::query()->create([
            'asset_id' => $gateAsset->id,
            'name' => 'Main Gate RFID',
            'reference' => 'DEV-RFID-GATE',
            'serial_number' => 'SN-RDR-GATE',
            'device_type' => DeviceType::RfidReader,
            'status' => HardwareStatus::Online,
            'api_token_hash' => hash('sha256', 'demo-gate-reader-token'),
            'token_issued_at' => $this->from,
            'last_seen_at' => $this->to->subMinutes(1),
            'created_at' => $this->from,
            'updated_at' => $this->to,
        ]);
        $this->readers->put('Main Gate', $gateReader);

        foreach ([
            ['name' => 'Gas Skid North', 'ref' => 'DEV-GAS-01', 'loc' => 'Work Area North'],
            ['name' => 'Gas Skid Hot Work', 'ref' => 'DEV-GAS-02', 'loc' => 'Hot Work Bay'],
            ['name' => 'Gas Skid Laydown', 'ref' => 'DEV-GAS-03', 'loc' => 'Laydown Yard'],
        ] as $i => $gas) {
            $asset = Asset::query()->create([
                'asset_type' => AssetType::Pole,
                'name' => $gas['name'].' Mount',
                'identifier' => sprintf('AST-GAS-%02d', $i + 1),
                'status' => AssetStatus::Active,
                'is_mobile' => false,
                'current_location_label' => $gas['loc'],
                'last_heartbeat_at' => $this->to->subMinutes(random_int(1, 10)),
                'created_at' => $this->from,
                'updated_at' => $this->to,
            ]);

            $device = Device::query()->create([
                'asset_id' => $asset->id,
                'name' => $gas['name'],
                'reference' => $gas['ref'],
                'serial_number' => 'SN-GAS-'.(200 + $i),
                'device_type' => DeviceType::GasDetector,
                'status' => HardwareStatus::Online,
                'api_token_hash' => hash('sha256', 'demo-gas-token-'.($i + 1)),
                'token_issued_at' => $this->from,
                'last_seen_at' => $this->to->subMinutes(random_int(1, 8)),
                'created_at' => $this->from,
                'updated_at' => $this->to,
            ]);
            $this->gasDevices->push($device);
        }

        foreach ([
            ['name' => 'Weather Station Gate', 'ref' => 'DEV-ENV-01'],
            ['name' => 'Weather Station North', 'ref' => 'DEV-ENV-02'],
        ] as $i => $env) {
            $asset = Asset::query()->create([
                'asset_type' => AssetType::Pole,
                'name' => $env['name'].' Mount',
                'identifier' => sprintf('AST-ENV-%02d', $i + 1),
                'status' => AssetStatus::Active,
                'is_mobile' => false,
                'current_location_label' => $i === 0 ? 'Main Gate' : 'Work Area North',
                'last_heartbeat_at' => $this->to->subMinutes(random_int(1, 12)),
                'created_at' => $this->from,
                'updated_at' => $this->to,
            ]);

            $device = Device::query()->create([
                'asset_id' => $asset->id,
                'name' => $env['name'],
                'reference' => $env['ref'],
                'serial_number' => 'SN-ENV-'.(300 + $i),
                'device_type' => DeviceType::EnvironmentalSensor,
                'status' => HardwareStatus::Online,
                'api_token_hash' => hash('sha256', 'demo-env-token-'.($i + 1)),
                'token_issued_at' => $this->from,
                'last_seen_at' => $this->to->subMinutes(random_int(1, 10)),
                'created_at' => $this->from,
                'updated_at' => $this->to,
            ]);
            $this->envDevices->push($device);
        }

        // One offline camera for system-health amber/red story
        Camera::query()->create([
            'asset_id' => $this->assets->get('Pole P-06 Temp')->id,
            'name' => 'Temp Works Cam (offline)',
            'reference' => 'cam-ppe-offline',
            'camera_type' => CameraType::Fixed,
            'stream_url' => 'rtsp://10.20.0.99/stream1',
            'ai_enabled' => true,
            'status' => HardwareStatus::Offline,
            'last_frame_at' => $this->to->subDays(2),
            'created_at' => $this->from,
            'updated_at' => $this->to,
        ]);
    }

    private function seedWorkersAndTags(): void
    {
        $this->workers = collect();
        $this->tags = collect();

        $contractors = [
            'Al-Naboodah Construction',
            'Besix',
            'Six Construct',
            'Target Engineering',
            'China State Construction',
            'Habtoor Leighton',
        ];

        $roles = [
            'Scaffolder', 'Rigger', 'Electrician', 'Welder', 'Carpenter',
            'Pipe Fitter', 'Crane Operator', 'Safety Officer', 'Foreman', 'Labourer',
        ];

        for ($i = 1; $i <= 48; $i++) {
            $type = match (true) {
                $i <= 28 => WorkerType::Employee,
                $i <= 42 => WorkerType::Contractor,
                default => WorkerType::Visitor,
            };

            $hired = $this->from->addDays(random_int(0, 20));
            $worker = Worker::query()->create([
                'name' => fake()->name(),
                'employee_code' => $type === WorkerType::Visitor ? null : sprintf('EMP-%04d', $i),
                'badge_number' => sprintf('BDG-%04d', $i),
                'contractor' => $type === WorkerType::Employee
                    ? 'Owner / EPC'
                    : $contractors[($i - 1) % count($contractors)],
                'role_title' => $type === WorkerType::Visitor ? 'Site Visitor' : $roles[($i - 1) % count($roles)],
                'worker_type' => $type,
                'phone' => '+9715'.fake()->numerify('########'),
                'notes' => null,
                'is_active' => $i !== 47,
                'present' => false,
                'last_seen_at' => null,
                'created_by' => $this->operator->id,
                'created_at' => $hired,
                'updated_at' => $hired,
            ]);
            $this->workers->push($worker);

            if ($type !== WorkerType::Visitor && $worker->is_active) {
                $tag = RfidTag::query()->create([
                    'tag_uid' => sprintf('E2%022d', $i),
                    'worker_id' => $worker->id,
                    'status' => TagStatus::Assigned,
                    'assigned_at' => $hired->addHours(2),
                    'assigned_by' => $this->operator->id,
                    'notes' => null,
                    'created_at' => $hired,
                    'updated_at' => $hired,
                ]);
                $this->tags->put($worker->id, $tag);
            }
        }

        // Spare tags in stock
        for ($i = 1; $i <= 8; $i++) {
            RfidTag::query()->create([
                'tag_uid' => sprintf('E2SPARE%018d', $i),
                'worker_id' => null,
                'status' => TagStatus::InStock,
                'created_at' => $this->from,
                'updated_at' => $this->from,
            ]);
        }
    }

    private function seedBindingsAndAccess(): void
    {
        $gate = $this->zones->get('Main Gate');
        $hotWork = $this->zones->get('Hot Work Bay');
        $height = $this->zones->get('Height Work Deck');

        // Historical binding then current for gate reader (repositioning story)
        $gateReader = $this->readers->get('Main Gate');
        ReaderZoneBinding::query()->create([
            'device_id' => $gateReader->id,
            'zone_id' => $gate->id,
            'bound_from' => $this->from,
            'bound_until' => $this->from->addMonths(2),
            'bound_by' => $this->admin->id,
            'note' => 'Initial commissioning binding',
        ]);
        ReaderZoneBinding::query()->create([
            'device_id' => $gateReader->id,
            'zone_id' => $gate->id,
            'bound_from' => $this->from->addMonths(2)->addHour(),
            'bound_until' => null,
            'bound_by' => $this->safetyManager->id,
            'note' => 'Reconfirmed after barrier upgrade',
        ]);

        foreach ($this->readers as $zoneName => $reader) {
            if ($zoneName === 'Main Gate') {
                continue;
            }

            $zone = $this->zones->get($zoneName);
            ReaderZoneBinding::query()->create([
                'device_id' => $reader->id,
                'zone_id' => $zone->id,
                'bound_from' => $this->from->addDays(1),
                'bound_until' => null,
                'bound_by' => $this->admin->id,
            ]);
        }

        $authorizedWorkers = $this->workers
            ->filter(fn (Worker $w) => $w->worker_type !== WorkerType::Visitor && $w->is_active)
            ->take(12);

        foreach ($authorizedWorkers as $worker) {
            ZoneAccessListEntry::query()->create([
                'zone_id' => $hotWork->id,
                'worker_id' => $worker->id,
                'authorized_by' => $this->safetyManager->id,
                'authorized_at' => $this->from->addWeeks(1),
            ]);
        }

        foreach ($authorizedWorkers->take(8) as $worker) {
            ZoneAccessListEntry::query()->create([
                'zone_id' => $height->id,
                'worker_id' => $worker->id,
                'authorized_by' => $this->safetyManager->id,
                'authorized_at' => $this->from->addWeeks(1),
            ]);
        }
    }

    private function seedEntryExitHistory(): void
    {
        $gate = $this->zones->get('Main Gate');
        $tagged = $this->workers->filter(
            fn (Worker $w) => $this->tags->has($w->id),
        )->values();

        $rows = [];
        $cursor = $this->from;

        while ($cursor->lte($this->to)) {
            if ($cursor->isWeekend()) {
                $cursor = $cursor->addDay();

                continue;
            }

            $dayCrew = $tagged->random(min(28, $tagged->count()));

            foreach ($dayCrew as $worker) {
                $tag = $this->tags->get($worker->id);
                $inAt = $cursor->setTime(random_int(5, 7), random_int(0, 59));
                $outAt = $cursor->setTime(random_int(16, 18), random_int(0, 59));

                if ($outAt->gt($this->to)) {
                    continue;
                }

                $rows[] = $this->entryExitRow($worker, $tag, $gate, Direction::In, $inAt);
                $rows[] = $this->entryExitRow($worker, $tag, $gate, Direction::Out, $outAt);

                if (count($rows) >= 500) {
                    EntryExitLog::query()->insert($rows);
                    $rows = [];
                }
            }

            $cursor = $cursor->addDay();
        }

        if ($rows !== []) {
            EntryExitLog::query()->insert($rows);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function entryExitRow(
        Worker $worker,
        RfidTag $tag,
        Zone $gate,
        Direction $direction,
        CarbonImmutable $at,
    ): array {
        return [
            'worker_id' => $worker->id,
            'tag_id' => $tag->id,
            'gate_zone_id' => $gate->id,
            'direction' => $direction->value,
            'occurred_at' => $at,
            'source' => EntryExitSource::GateReader->value,
            'created_at' => $at,
            'updated_at' => $at,
            'deleted_at' => null,
        ];
    }

    private function seedGasAndEnvironmentHistory(): void
    {
        $gasRows = [];
        $envRows = [];
        $nowTs = $this->to->toDateTimeString();

        foreach ($this->gasDevices as $deviceIndex => $device) {
            $cursor = $this->from;

            while ($cursor->lte($this->to)) {
                $hour = (int) $cursor->format('G');
                // Site operates ~05:00–20:00; still sample overnight sparsely
                if ($hour < 5 || $hour > 20) {
                    $cursor = $cursor->addHours(3);

                    continue;
                }

                $spike = random_int(1, 100) <= 3;
                $h2s = $spike ? fake()->randomFloat(2, 6, 18) : fake()->randomFloat(2, 0.2, 3.5);
                $lel = $spike ? fake()->randomFloat(2, 8, 22) : fake()->randomFloat(2, 0.5, 4.0);
                $co = fake()->randomFloat(2, 1, 18);
                $o2 = fake()->randomFloat(2, 20.4, 21.0);
                $isBackfill = $cursor->lt($this->to->subDays(2));

                $gasRows[] = [
                    'device_id' => $device->id,
                    'asset_id' => $device->asset_id,
                    'recorded_at' => $cursor,
                    'received_at' => $cursor->addSeconds(random_int(1, 8)),
                    'lel_pct' => $lel,
                    'h2s_ppm' => $h2s,
                    'o2_pct' => $o2,
                    'co_ppm' => $co,
                    'co2_ppm' => $deviceIndex === 1 ? fake()->randomFloat(2, 420, 780) : null,
                    'is_backfill' => $isBackfill,
                    'clock_skew' => false,
                    'event_uid' => (string) Str::uuid(),
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];

                if ($spike && ! $isBackfill) {
                    $level = $h2s >= 10 ? GasAlarmLevel::Alarm : GasAlarmLevel::Warning;
                    $alert = Alert::query()->create([
                        'alert_type' => $level === GasAlarmLevel::Alarm
                            ? AlertType::GasAlarm
                            : AlertType::GasWarning,
                        'severity' => $level === GasAlarmLevel::Alarm
                            ? AlertSeverity::Critical
                            : AlertSeverity::Warning,
                        'title' => sprintf('H₂S %s on %s', $level->value, $device->name),
                        'payload' => [
                            'device_id' => $device->id,
                            'gas_type' => GasType::H2s->value,
                            'reading' => $h2s,
                        ],
                        'status' => AlertStatus::Resolved,
                        'raised_at' => $cursor,
                        'resolved_at' => $cursor->addMinutes(random_int(15, 90)),
                        'audible' => $level === GasAlarmLevel::Alarm,
                        'dedupe_key' => 'gas:'.$device->id.':'.$cursor->timestamp,
                        'occurrences' => 1,
                        'created_at' => $cursor,
                        'updated_at' => $cursor,
                    ]);

                    GasAlarm::query()->create([
                        'device_id' => $device->id,
                        'asset_id' => $device->asset_id,
                        'gas_type' => GasType::H2s,
                        'level' => $level,
                        'reading_value' => $h2s,
                        'threshold_value' => $level === GasAlarmLevel::Alarm ? 10 : 5,
                        'triggered_at' => $cursor,
                        'resolved_at' => $cursor->addMinutes(random_int(15, 90)),
                        'alert_id' => $alert->id,
                        'acknowledged_by' => $this->operator->id,
                        'acknowledged_at' => $cursor->addMinutes(5),
                        'during_outage' => false,
                        'created_at' => $cursor,
                        'updated_at' => $cursor,
                    ]);
                }

                if (count($gasRows) >= 400) {
                    GasReading::query()->insert($gasRows);
                    $gasRows = [];
                }

                $cursor = $cursor->addHours(2);
            }
        }

        if ($gasRows !== []) {
            GasReading::query()->insert($gasRows);
        }

        foreach ($this->envDevices as $device) {
            $cursor = $this->from;

            while ($cursor->lte($this->to)) {
                $tempBase = 28 + 8 * sin(($cursor->dayOfYear / 365) * 2 * M_PI);
                $envRows[] = [
                    'device_id' => $device->id,
                    'asset_id' => $device->asset_id,
                    'recorded_at' => $cursor,
                    'received_at' => $cursor->addSeconds(random_int(1, 5)),
                    'temperature_c' => round($tempBase + fake()->randomFloat(1, -2, 3), 2),
                    'humidity_pct' => fake()->randomFloat(1, 35, 78),
                    'wind_speed_ms' => fake()->randomFloat(1, 0.5, 9.5),
                    'extra' => null,
                    'is_backfill' => $cursor->lt($this->to->subDays(2)),
                    'clock_skew' => false,
                    'event_uid' => (string) Str::uuid(),
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];

                if (count($envRows) >= 400) {
                    EnvironmentalReading::query()->insert($envRows);
                    $envRows = [];
                }

                $cursor = $cursor->addHours(2);
            }
        }

        if ($envRows !== []) {
            EnvironmentalReading::query()->insert($envRows);
        }
    }

    private function seedPpeHistory(): void
    {
        $types = ViolationType::cases();
        $cursor = $this->from->addDays(2);

        while ($cursor->lte($this->to)) {
            if ($cursor->isWeekend()) {
                $cursor = $cursor->addDay();

                continue;
            }

            $count = random_int(1, 4);

            for ($i = 0; $i < $count; $i++) {
                $camera = $this->cameras->random();
                $detected = $cursor->setTime(random_int(7, 16), random_int(0, 59));
                $reviewed = random_int(1, 100) <= 70;
                $falsePositive = $reviewed && random_int(1, 100) <= 20;

                PpeViolation::query()->create([
                    'camera_id' => $camera->id,
                    'violation_type' => $types[array_rand($types)],
                    'detected_at' => $detected,
                    'worker_count' => random_int(1, 3),
                    'snapshot_path' => 'snapshots/'.$detected->format('Y/m/d').'/'.Str::uuid().'.jpg',
                    'confidence' => fake()->randomFloat(2, 0.72, 0.98),
                    'location_label' => $camera->name,
                    'review_status' => $falsePositive
                        ? ReviewStatus::FalsePositive
                        : ($reviewed ? ReviewStatus::Confirmed : ReviewStatus::Unreviewed),
                    'reviewed_by' => $reviewed ? $this->operator->id : null,
                    'reviewed_at' => $reviewed ? $detected->addHours(random_int(1, 8)) : null,
                    'review_note' => $falsePositive ? 'Glare / partial occlusion' : null,
                    'is_backfill' => false,
                    'event_uid' => (string) Str::uuid(),
                    'created_at' => $detected,
                    'updated_at' => $detected,
                ]);
            }

            $cursor = $cursor->addDay();
        }
    }

    private function seedAlerts(): void
    {
        $openCritical = [
            ['type' => AlertType::CameraOffline, 'title' => 'Camera offline — Temp Works Cam', 'sev' => AlertSeverity::Warning],
            ['type' => AlertType::PpeViolation, 'title' => 'Unreviewed PPE cluster — Work Area North', 'sev' => AlertSeverity::Warning],
            ['type' => AlertType::GasAlarm, 'title' => 'H₂S alarm — Gas Skid Hot Work', 'sev' => AlertSeverity::Critical],
            ['type' => AlertType::DeviceOffline, 'title' => 'RFID reader heartbeat stale — Pole P-06', 'sev' => AlertSeverity::Warning],
        ];

        foreach ($openCritical as $i => $def) {
            Alert::query()->create([
                'alert_type' => $def['type'],
                'severity' => $def['sev'],
                'title' => $def['title'],
                'payload' => ['demo' => true, 'index' => $i],
                'status' => $i === 2 ? AlertStatus::Open : AlertStatus::Acknowledged,
                'raised_at' => $this->to->subHours(random_int(1, 18)),
                'acknowledged_at' => $i === 2 ? null : $this->to->subHours(1),
                'acknowledged_by' => $i === 2 ? null : $this->operator->id,
                'audible' => $def['sev'] === AlertSeverity::Critical,
                'dedupe_key' => 'demo-open-'.$i,
                'occurrences' => random_int(1, 4),
            ]);
        }

        $historicalTypes = [
            AlertType::PpeViolation,
            AlertType::RedZoneIntrusion,
            AlertType::UnauthorizedZoneAccess,
            AlertType::DeviceOffline,
            AlertType::StationaryTag,
            AlertType::EquipmentOverdue,
            AlertType::FallDetection,
        ];

        for ($i = 0; $i < 60; $i++) {
            $raised = $this->from->addDays(random_int(0, (int) $this->from->diffInDays($this->to) - 1))
                ->setTime(random_int(6, 17), random_int(0, 59));
            $type = $historicalTypes[array_rand($historicalTypes)];
            $critical = in_array($type, [AlertType::FallDetection, AlertType::RedZoneIntrusion], true);

            Alert::query()->create([
                'alert_type' => $type,
                'severity' => $critical ? AlertSeverity::Critical : AlertSeverity::Warning,
                'title' => $type->label().' — '.$this->zones->random()->name,
                'payload' => ['demo' => true],
                'status' => AlertStatus::Resolved,
                'raised_at' => $raised,
                'acknowledged_at' => $raised->addMinutes(random_int(5, 40)),
                'acknowledged_by' => $this->operator->id,
                'resolved_at' => $raised->addHours(random_int(1, 6)),
                'audible' => $critical,
                'dedupe_key' => 'demo-hist-'.$i.'-'.$raised->timestamp,
                'occurrences' => random_int(1, 3),
                'created_at' => $raised,
                'updated_at' => $raised,
            ]);
        }
    }

    private function seedEquipmentLifecycle(): void
    {
        $this->equipment = collect();

        $catalog = [
            ['Fire Extinguisher CO₂ 5kg', 'fire extinguisher', false],
            ['Fire Extinguisher Foam 9L', 'fire extinguisher', false],
            ['Full Body Harness', 'safety harness', true],
            ['Retractable Lanyard', 'safety harness', true],
            ['SCBA Set', 'respiratory', true],
            ['Portable Gas Detector', 'gas detector', true],
            ['First Aid Kit A', 'first aid', false],
            ['First Aid Kit B', 'first aid', false],
            ['Generator 20kVA', 'generator', false],
            ['Welding Machine', 'hot work', true],
            ['Confined Space Tripod', 'rescue', true],
            ['Rescue Stretcher', 'rescue', false],
            ['Traffic Cone Set', 'traffic', true],
            ['Spill Kit', 'environmental', false],
            ['Ladder 6m FRP', 'access', true],
            ['Scaffold Tag Board', 'scaffold', false],
            ['Eye Wash Station', 'first aid', false],
            ['Lockout Kit', 'electrical', true],
            ['Torque Wrench Set', 'tools', true],
            ['Radios (pack of 6)', 'comms', true],
        ];

        foreach ($catalog as $i => $item) {
            [$name, $type, $checkoutable] = $item;
            $created = $this->from->addDays(random_int(0, 10));
            $nextInspection = $this->to->addDays(random_int(-10, 45));

            $eq = Equipment::query()->create([
                'equipment_code' => sprintf('EQ-%04d', $i + 1),
                'qr_token' => (string) Str::uuid(),
                'name' => $name,
                'equipment_type' => $type,
                'status' => $nextInspection->lt($this->to)
                    ? EquipmentStatus::OutOfService
                    : EquipmentStatus::InService,
                'is_checkoutable' => $checkoutable,
                'location_label' => $this->zones->random()->name,
                'description' => 'Commissioned for IR4 demo site.',
                'next_inspection_due' => $nextInspection->toDateString(),
                'next_service_due' => $this->to->addDays(random_int(20, 120))->toDateString(),
                'created_by' => $this->admin->id,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
            $this->equipment->push($eq);

            MaintenanceSchedule::query()->create([
                'equipment_id' => $eq->id,
                'schedule_type' => ScheduleType::Inspection,
                'interval_days' => 30,
                'created_by' => $this->admin->id,
            ]);

            // Historical inspections
            for ($n = 0; $n < 3; $n++) {
                $when = $this->from->addDays(20 + ($n * 35) + random_int(0, 5));
                if ($when->gt($this->to)) {
                    break;
                }

                EquipmentInspection::query()->create([
                    'equipment_id' => $eq->id,
                    'inspected_at' => $when->toDateString(),
                    'outcome' => $n === 2 && $i % 7 === 0
                        ? InspectionOutcome::Fail
                        : InspectionOutcome::Pass,
                    'notes' => $n === 2 && $i % 7 === 0 ? 'Wear beyond tolerance — tagged out.' : 'Visual + function OK.',
                    'inspector_id' => $this->safetyManager->id,
                    'created_by' => $this->safetyManager->id,
                    'created_at' => $when,
                    'updated_at' => $when,
                ]);
            }

            if ($i % 3 === 0) {
                $maintAt = $this->from->addDays(random_int(30, 100));
                if ($maintAt->lte($this->to)) {
                    EquipmentMaintenance::query()->create([
                        'equipment_id' => $eq->id,
                        'performed_at' => $maintAt->toDateString(),
                        'maintenance_type' => MaintenanceType::Preventive,
                        'description' => 'Scheduled preventive service.',
                        'recorded_by' => $this->operator->id,
                        'created_by' => $this->operator->id,
                        'created_at' => $maintAt,
                        'updated_at' => $maintAt,
                    ]);
                }
            }
        }

        $checkoutable = $this->equipment->where('is_checkoutable', true)->values();
        $taggedWorkers = $this->workers->filter(fn (Worker $w) => $this->tags->has($w->id))->values();

        foreach ($checkoutable->take(8) as $i => $eq) {
            $worker = $taggedWorkers[$i % $taggedWorkers->count()];
            $outAt = $this->to->subDays(random_int(1, 14))->setTime(8, 0);
            $returned = $i % 2 === 0;

            EquipmentCheckout::query()->create([
                'equipment_id' => $eq->id,
                'worker_id' => $worker->id,
                'checked_out_at' => $outAt,
                'checked_out_by' => $this->operator->id,
                'expected_return_at' => $outAt->addDays(3),
                'returned_at' => $returned ? $outAt->addDays(2) : null,
                'returned_to' => $returned ? $this->operator->id : null,
                'return_status' => $returned ? 'ok' : null,
                'zone_id' => $this->zones->get('Laydown Yard')->id,
                'notes' => null,
                'created_by' => $this->operator->id,
                'created_at' => $outAt,
                'updated_at' => $outAt,
            ]);
        }
    }

    private function seedIncidentsAndLsr(): void
    {
        $scenarios = [
            [
                'days_ago' => 95,
                'type' => IncidentType::NearMiss,
                'sev' => IncidentSeverity::Medium,
                'nature' => 'Load swung near walkway during tandem lift.',
                'immediate' => 'Lift paused; exclusion zone enforced.',
                'corrective' => 'Banksman briefing and tagged exclusion markers.',
                'status' => IncidentStatus::Closed,
            ],
            [
                'days_ago' => 72,
                'type' => IncidentType::Injury,
                'sev' => IncidentSeverity::Low,
                'nature' => 'Minor laceration while cutting banding.',
                'immediate' => 'First aid administered; work stopped in bay.',
                'corrective' => 'Cut-resistant gloves made mandatory for banding.',
                'status' => IncidentStatus::Closed,
            ],
            [
                'days_ago' => 48,
                'type' => IncidentType::PropertyDamage,
                'sev' => IncidentSeverity::Medium,
                'nature' => 'Scaffold board struck parked MEWP.',
                'immediate' => 'Area cordoned; MEWP isolated.',
                'corrective' => 'Traffic plan updated for scaffold material routes.',
                'status' => IncidentStatus::Classified,
            ],
            [
                'days_ago' => 21,
                'type' => IncidentType::Environmental,
                'sev' => IncidentSeverity::Low,
                'nature' => 'Small hydraulic oil drip in laydown.',
                'immediate' => 'Spill kit deployed; residue cleaned.',
                'corrective' => 'Plant pre-use checks reinforced.',
                'status' => IncidentStatus::UnderReview,
            ],
            [
                'days_ago' => 4,
                'type' => IncidentType::NearMiss,
                'sev' => IncidentSeverity::High,
                'nature' => 'Worker entered hot-work bay without fire watch confirmation.',
                'immediate' => 'Work stopped; permit suspended.',
                'corrective' => null,
                'status' => IncidentStatus::Open,
            ],
        ];

        foreach ($scenarios as $i => $s) {
            $occurred = $this->to->subDays($s['days_ago'])->setTime(10, 30);
            $incident = HseIncident::query()->create([
                'incident_number' => sprintf('INC-%s-%04d', $this->to->format('Y'), $i + 1),
                'source' => IncidentSource::Manual,
                'zone_id' => $this->zones->random()->id,
                'occurred_at' => $occurred,
                'status' => $s['status'],
                'incident_type' => $s['type'],
                'severity' => $s['sev'],
                'nature_of_incident' => $s['nature'],
                'immediate_action' => $s['immediate'],
                'corrective_action' => $s['corrective'],
                'classified_by' => in_array($s['status'], [IncidentStatus::Classified, IncidentStatus::Closed], true)
                    ? $this->safetyManager->id
                    : null,
                'classified_at' => in_array($s['status'], [IncidentStatus::Classified, IncidentStatus::Closed], true)
                    ? $occurred->addHours(6)
                    : null,
                'closed_by' => $s['status'] === IncidentStatus::Closed ? $this->safetyManager->id : null,
                'closed_at' => $s['status'] === IncidentStatus::Closed ? $occurred->addDays(3) : null,
                'close_note' => $s['status'] === IncidentStatus::Closed ? 'Actions verified closed-out.' : null,
                'created_by' => $this->operator->id,
                'created_at' => $occurred,
                'updated_at' => $occurred,
            ]);

            $worker = $this->workers->filter(fn (Worker $w) => $this->tags->has($w->id))->random();
            IncidentPersonnel::query()->create([
                'hse_incident_id' => $incident->id,
                'worker_id' => $worker->id,
                'involvement' => Involvement::Involved,
            ]);
        }

        $lsrCats = [
            LsrCategory::MissingPpe,
            LsrCategory::HotWorkWithoutFireWatch,
            LsrCategory::HeightWithoutHarness,
            LsrCategory::WorkingWithoutPermit,
            LsrCategory::RedZoneIntrusion,
            LsrCategory::SimopsViolation,
        ];

        for ($i = 0; $i < 28; $i++) {
            $occurred = $this->from->addDays(random_int(5, (int) $this->from->diffInDays($this->to) - 1))
                ->setTime(random_int(8, 15), random_int(0, 59));
            $cat = $lsrCats[$i % count($lsrCats)];
            $closed = $i < 22;
            $ppeLinked = $cat === LsrCategory::MissingPpe;

            LsrViolation::query()->create([
                'category' => $cat,
                'occurred_at' => $occurred,
                'worker_id' => $ppeLinked ? null : $this->workers->random()->id,
                'zone_id' => $this->zones->random()->id,
                'camera_id' => $ppeLinked ? $this->cameras->random()->id : null,
                'description' => $cat->label().' observed during field patrol.',
                'action_taken' => $closed
                    ? 'Work stopped, briefing delivered, and controls reinstated before restart.'
                    : null,
                'status' => $closed ? LsrStatus::Closed : LsrStatus::Open,
                'closed_by' => $closed ? $this->safetyManager->id : null,
                'closed_at' => $closed ? $occurred->addHours(random_int(2, 24)) : null,
                'logged_by' => $this->operator->id,
                'created_at' => $occurred,
                'updated_at' => $occurred,
            ]);
        }
    }

    private function seedVehicleViolations(): void
    {
        $types = ['speeding', 'seatbelt', 'unauthorized_parking', 'reckless_driving'];

        for ($i = 0; $i < 36; $i++) {
            $when = $this->from->addDays(random_int(0, (int) $this->from->diffInDays($this->to) - 1))
                ->setTime(random_int(7, 17), random_int(0, 59));

            VehicleViolation::query()->create([
                'observed_at' => $when,
                'vehicle_description' => fake()->bothify('Pickup ####?? / Plate ???-####'),
                'violation_type' => $types[$i % count($types)],
                'description' => 'Observed on internal haul road near laydown.',
                'action_taken' => 'Driver stopped, briefed, and warning recorded with supervisor.',
                'camera_id' => $this->cameras->random()->id,
                'logged_by' => $this->operator->id,
                'created_at' => $when,
                'updated_at' => $when,
            ]);
        }
    }

    private function seedLivePresence(): void
    {
        $onSiteZones = [
            'Work Area North',
            'Work Area South',
            'Laydown Yard',
            'Height Work Deck',
            'Temporary Works',
            'Muster Point A',
        ];

        $present = $this->workers
            ->filter(fn (Worker $w) => $this->tags->has($w->id) && $w->is_active)
            ->shuffle()
            ->take(22)
            ->values();

        foreach ($present as $i => $worker) {
            $zone = $this->zones->get($onSiteZones[$i % count($onSiteZones)]);
            $tag = $this->tags->get($worker->id);
            $seen = $this->to->subMinutes(random_int(1, 40));

            $worker->forceFill([
                'present' => true,
                'last_seen_at' => $seen,
            ])->save();

            WorkerPosition::query()->create([
                'tag_id' => $tag->id,
                'worker_id' => $worker->id,
                'zone_id' => $zone->id,
                'last_seen_at' => $seen,
                'is_on_site' => true,
            ]);
        }
    }

    private function seedWeeklyReports(): void
    {
        Notification::fake();

        /** @var WeeklyReportService $reports */
        $reports = app(WeeklyReportService::class);

        // Sunday-start weeks fully inside the demo window
        $weekStart = $this->from->startOfWeek(CarbonImmutable::SUNDAY);
        if ($weekStart->lt($this->from)) {
            $weekStart = $weekStart->addWeek();
        }

        $generated = 0;

        while (true) {
            $weekEnd = $weekStart->endOfWeek(CarbonImmutable::SATURDAY);

            if ($weekEnd->gte($this->to->startOfDay())) {
                break;
            }

            try {
                $report = $reports->generate(
                    start: $weekStart->toDateString(),
                    end: $weekEnd->toDateString(),
                    by: $this->safetyManager,
                    auto: false,
                );

                // Publish all but the most recent completed week
                $ageWeeks = (int) $weekEnd->diffInWeeks($this->to);
                if ($ageWeeks >= 1) {
                    $reports->publish($report, $this->admin);
                }

                $generated++;
            } catch (\Throwable $e) {
                $this->command?->warn('Weekly report skipped for '.$weekStart->toDateString().': '.$e->getMessage());
            }

            $weekStart = $weekStart->addWeek();
        }

        $this->command?->info("Generated {$generated} weekly report(s).");
    }
}
