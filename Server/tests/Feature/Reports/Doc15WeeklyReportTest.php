<?php

use App\Enums\AlertType;
use App\Enums\DeviceType;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\LsrCategory;
use App\Enums\LsrStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Models\Alert;
use App\Models\Device;
use App\Models\HseIncident;
use App\Models\LsrViolation;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\VehicleViolation;
use App\Models\WeeklyReport;
use App\Services\Report\WeeklyReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
});

it('generates all 9 frozen data keys and includes only confirmed PPE', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $start = now()->startOfWeek(Carbon::SUNDAY)->subWeek();
    $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

    PpeViolation::factory()->create([
        'detected_at' => $start->copy()->addDay(),
        'review_status' => ReviewStatus::Confirmed,
    ]);
    PpeViolation::factory()->create([
        'detected_at' => $start->copy()->addDays(2),
        'review_status' => ReviewStatus::FalsePositive,
    ]);
    PpeViolation::factory()->create([
        'detected_at' => $start->copy()->addDays(2),
        'review_status' => ReviewStatus::Unreviewed,
    ]);

    HseIncident::factory()->classified()->create([
        'occurred_at' => $start->copy()->addDays(3),
        'classified_at' => $start->copy()->addDays(3),
        'status' => IncidentStatus::Classified,
        'incident_type' => IncidentType::NearMiss,
        'severity' => IncidentSeverity::Medium,
        'nature_of_incident' => 'Classified near miss with enough text.',
        'immediate_action' => 'Immediate action with enough text.',
        'corrective_action' => 'Corrective action with enough text.',
    ]);

    LsrViolation::factory()->create([
        'occurred_at' => $start->copy()->addDays(4),
        'category' => LsrCategory::MissingPpe,
        'status' => LsrStatus::Closed,
        'action_taken' => 'Harness issued before restart.',
    ]);

    VehicleViolation::factory()->create([
        'observed_at' => $start->copy()->addDays(5),
        'logged_by' => $manager->id,
    ]);

    $report = app(WeeklyReportService::class)->generate($start, $end, $manager);

    expect($report->status)->toBe(ReportStatus::Generated)
        ->and(array_keys($report->data))->toEqualCanonicalizing(WeeklyReportService::dataKeys())
        ->and(WeeklyReportService::dataKeys())->not->toContain('x_co2')
        ->and($report->data)->not->toHaveKey('x_co2')
        ->and($report->data['ii_hse_incidents'])->toHaveCount(1)
        ->and($report->data['iii_lsr_violations']['entries'])->toHaveCount(1)
        ->and($report->data['vii_vehicle_violations'])->toHaveCount(1)
        ->and($report->data['vi_units_monitored']['note'])->toContain('monitoring devices')
        ->and(collect($report->data['ix_gas']['per_day'])->first())->toHaveKeys(['date', 'lel', 'h2s', 'o2', 'co', 'co2'])
        ->and($report->data['ix_gas']['per_day'][0]['co2'])->toHaveKeys(['min', 'avg', 'max'])
        ->and((int) collect($report->data['i_daily_safety_observations']['per_day'])->sum('total'))->toBe(1)
        ->and($report->pdf_path)->not->toBeNull()
        ->and($report->csv_path)->not->toBeNull();

    Storage::disk('private')->assertExists($report->pdf_path);
    Storage::disk('private')->assertExists($report->csv_path);

    // Frozen: later PPE does not change snapshot.
    $frozenTotal = (int) collect($report->data['i_daily_safety_observations']['per_day'])->sum('total');
    PpeViolation::factory()->create([
        'detected_at' => $start->copy()->addDays(1),
        'review_status' => ReviewStatus::Confirmed,
    ]);
    expect((int) collect($report->fresh()->data['i_daily_safety_observations']['per_day'])->sum('total'))
        ->toBe($frozenTotal);
});

it('generates manually in-request and redirects to the report', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $start = now()->toDateString();
    $end = now()->toDateString();

    $response = $this->actingAs($manager)
        ->post(route('weekly-reports.generate'), [
            'period_start' => $start,
            'period_end' => $end,
        ]);

    $report = WeeklyReport::query()->latest('id')->first();
    expect($report)->not->toBeNull()
        ->and($report->status)->toBe(ReportStatus::Generated)
        ->and($report->generated_by)->toBe($manager->id);

    $response->assertRedirect(route('reports.show', $report));
});

it('adds completeness notes when device offline exceeds threshold', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $start = now()->startOfWeek(Carbon::SUNDAY)->subWeek();
    $end = $start->copy()->endOfWeek(Carbon::SATURDAY);
    $device = Device::factory()->create(['device_type' => DeviceType::GasDetector]);

    Alert::factory()->create([
        'alert_type' => AlertType::DeviceOffline,
        'payload' => ['device_id' => $device->id],
        'created_at' => $start->copy(),
        'resolved_at' => $start->copy()->addDays(3),
    ]);

    $report = app(WeeklyReportService::class)->generate($start, $end, $manager);
    $notes = $report->data['completeness']['notes'];

    expect($notes)->not->toBeEmpty()
        ->and($notes[0]['item'])->toBe('ix_gas');
});

it('maps edge/rfid outages away from ix_gas and keeps payload names for missing devices', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $start = now()->startOfWeek(Carbon::SUNDAY)->subWeek();
    $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

    $edge = Device::factory()->create([
        'name' => 'Pole 03 Fixed Camera',
        'device_type' => DeviceType::EdgeCompute,
    ]);
    $rfid = Device::factory()->create([
        'name' => 'Pole 03 RFID Reader',
        'device_type' => DeviceType::RfidReader,
    ]);
    $gas = Device::factory()->create([
        'name' => 'Pole 03 Gas Detector',
        'device_type' => DeviceType::GasDetector,
    ]);

    foreach ([$edge, $rfid, $gas] as $device) {
        Alert::factory()->create([
            'alert_type' => AlertType::DeviceOffline,
            'payload' => ['device_id' => $device->id, 'device_name' => $device->name],
            'created_at' => $start->copy(),
            'resolved_at' => $start->copy()->addDays(3),
        ]);
    }

    Alert::factory()->create([
        'alert_type' => AlertType::DeviceOffline,
        'payload' => ['device_id' => 999999, 'device_name' => 'Former Device 17'],
        'created_at' => $start->copy(),
        'resolved_at' => $start->copy()->addDays(3),
    ]);

    Alert::factory()->create([
        'alert_type' => AlertType::CameraOffline,
        'payload' => ['camera_id' => 99, 'camera_name' => 'Pole 04 Fixed Camera'],
        'created_at' => $start->copy(),
        'resolved_at' => $start->copy()->addDays(3),
    ]);

    $notes = collect(app(WeeklyReportService::class)->generate($start, $end, $manager)->data['completeness']['notes']);

    expect($notes->where('item', 'ix_gas'))->toHaveCount(1)
        ->and($notes->where('item', 'v_manpower'))->toHaveCount(1)
        ->and($notes->where('item', 'vi_units_monitored'))->toHaveCount(1)
        ->and($notes->firstWhere('item', 'ix_gas')['message'])->toContain('Pole 03 Gas Detector')
        ->and($notes->firstWhere('item', 'v_manpower')['message'])->toContain('Pole 03 RFID Reader')
        ->and($notes->firstWhere('item', 'vi_units_monitored')['message'])->toContain('units offline')
        ->and($notes->where('item', 'ix_gas')->every(fn (array $n): bool => ! str_contains($n['message'], 'Camera')))->toBeTrue();
});

it('publish-locks and supersedes without mutating the old published report', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $start = now()->startOfWeek(Carbon::SUNDAY)->subWeek();
    $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

    $first = app(WeeklyReportService::class)->generate($start, $end, $manager);
    $published = app(WeeklyReportService::class)->publish($first, $manager);
    $frozen = $published->data;

    $this->actingAs($manager)
        ->post(route('weekly-reports.publish', $published))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message');

    $second = app(WeeklyReportService::class)->generate($start, $end, $manager);
    expect($second->supersedes_report_id)->toBe($published->id)
        ->and($published->fresh()->data)->toEqual($frozen)
        ->and($published->fresh()->status)->toBe(ReportStatus::Published);
});

it('requires action_taken for vehicle violations and gates PM to published reports', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $pm = User::factory()->withRole('Project Manager')->create();

    $this->actingAs($operator)
        ->post(route('hse.vehicle-violations.store'), [
            'observed_at' => now()->toDateTimeString(),
            'vehicle_description' => 'Plate 1234',
            'violation_type' => 'speeding',
            'action_taken' => 'short',
        ])
        ->assertSessionHasErrors('action_taken');

    $this->post(route('hse.vehicle-violations.store'), [
        'observed_at' => now()->toDateTimeString(),
        'vehicle_description' => 'Plate 1234',
        'violation_type' => 'speeding',
        'action_taken' => 'Driver warned and logged in toolbox talk.',
    ])->assertRedirect();

    $draft = WeeklyReport::factory()->generated()->create();
    $published = WeeklyReport::factory()->published()->create();

    $this->actingAs($pm)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('reports.data', 1)
            ->where('reports.data.0.id', $published->id));

    $this->get(route('reports.show', $draft))->assertForbidden();
    $this->get(route('reports.show', $published))->assertOk();
});
