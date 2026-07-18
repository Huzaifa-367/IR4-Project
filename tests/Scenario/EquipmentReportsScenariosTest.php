<?php

use App\Enums\EquipmentStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\LsrCategory;
use App\Enums\LsrStatus;
use App\Enums\MaintenanceType;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use App\Models\HseIncident;
use App\Models\LsrViolation;
use App\Models\PpeViolation;
use App\Models\User;
use App\Models\VehicleViolation;
use App\Models\Worker;
use App\Services\EquipmentService;
use App\Services\WeeklyReportService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('private');
});

// DOC-21 scenario 9: Equipment lifecycle create/checkout/return/overdue.
it('scenario 09: equipment checkout return damaged maintenance and overdue flag', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $worker = Worker::factory()->create();

    $this->actingAs($manager)
        ->post(route('equipment.store'), [
            'name' => 'Scenario Harness',
            'equipment_type' => 'safety harness',
            'inspection_interval_days' => 30,
            'is_checkoutable' => true,
        ])
        ->assertRedirect();

    $equipment = Equipment::query()->firstOrFail();
    $token = $equipment->qr_token;
    expect(Str::isUuid($token))->toBeTrue();

    $this->getJson(route('api.equipment.by-token', $token))
        ->assertOk()
        ->assertJsonPath('data.checkout_state', 'available');

    $this->post(route('equipment.checkout', $equipment), [
        'worker_id' => $worker->id,
        'reason' => 'tower work',
    ])->assertRedirect();

    expect(EquipmentCheckout::query()->whereNull('returned_at')->count())->toBe(1);

    $checkout = EquipmentCheckout::query()->whereNull('returned_at')->firstOrFail();
    $this->post(route('equipment.checkouts.return', $checkout), [
        'return_status' => 'damaged',
        'condition_in' => 'webbing frayed',
    ])->assertRedirect();

    expect($checkout->fresh()->returned_at)->not->toBeNull();

    $this->post(route('equipment.maintenances.store', $equipment), [
        'performed_at' => now()->toDateString(),
        'maintenance_type' => MaintenanceType::Corrective->value,
        'description' => 'Replaced webbing and load-tested.',
        'return_to_service' => true,
    ])->assertRedirect();

    expect($equipment->fresh()->status)->toBe(EquipmentStatus::InService);

    $overdue = Equipment::factory()->create([
        'next_inspection_due' => now()->subDay()->toDateString(),
    ]);
    expect(app(EquipmentService::class)->flagOverdue())->toBe(1)
        ->and(Alert::query()->where('dedupe_key', 'equipment_overdue:'.$overdue->id)->exists())->toBeTrue();
});

// DOC-21 scenario 10: Weekly report generate/publish/supersede.
it('scenario 10: weekly report generate publish lock and supersede', function () {
    $manager = User::factory()->withRole('Safety Manager')->create();
    $start = now()->startOfWeek(\Carbon\Carbon::SUNDAY)->subWeek();
    $end = $start->copy()->endOfWeek(\Carbon\Carbon::SATURDAY);

    PpeViolation::factory()->create([
        'detected_at' => $start->copy()->addDay(),
        'review_status' => ReviewStatus::Confirmed,
    ]);
    PpeViolation::factory()->create([
        'detected_at' => $start->copy()->addDays(2),
        'review_status' => ReviewStatus::FalsePositive,
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
        ->and($report->data['i_daily_safety_observations']['false_positives_excluded'])->toBe(1);

    $published = app(WeeklyReportService::class)->publish($report, $manager);
    $frozen = $published->data;

    $this->actingAs($manager)
        ->post(route('weekly-reports.publish', $published))
        ->assertStatus(422);

    $second = app(WeeklyReportService::class)->generate($start, $end, $manager);
    expect($second->supersedes_report_id)->toBe($published->id)
        ->and($published->fresh()->data)->toEqual($frozen)
        ->and($published->fresh()->status)->toBe(ReportStatus::Published);
});
