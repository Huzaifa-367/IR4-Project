<?php

use App\Enums\AlertType;
use App\Enums\EquipmentStatus;
use App\Enums\InspectionOutcome;
use App\Enums\MaintenanceType;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use App\Models\User;
use App\Models\Worker;
use App\Services\EquipmentLabelService;
use App\Services\EquipmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('creates equipment with a permanent qr_token that updates never regenerate', function () {
    $user = User::factory()->withRole('Safety Manager')->create();
    $this->actingAs($user);

    $this->post(route('equipment.store'), [
        'name' => 'Extinguisher A',
        'equipment_type' => 'fire extinguisher',
        'inspection_interval_days' => 30,
    ])->assertRedirect();

    $equipment = Equipment::query()->firstOrFail();
    $token = $equipment->qr_token;

    expect($token)->not->toBeEmpty()
        ->and(Str::isUuid($token))->toBeTrue();

    $this->put(route('equipment.update', $equipment), [
        'name' => 'Extinguisher A2',
        'equipment_type' => 'fire extinguisher',
        'qr_token' => (string) Str::uuid(),
    ])->assertRedirect();

    expect($equipment->fresh()?->qr_token)->toBe($token)
        ->and($equipment->fresh()?->name)->toBe('Extinguisher A2');
});

it('recomputs due dates from schedules after inspection and fails to out of service with alert', function () {
    $user = User::factory()->withRole('Safety Manager')->create();
    $equipment = Equipment::factory()->create();

    app(EquipmentService::class)->syncSchedules($equipment, [
        ['schedule_type' => 'inspection', 'interval_days' => 30],
    ]);

    $this->actingAs($user)
        ->post(route('equipment.inspections.store', $equipment), [
            'inspected_at' => now()->toDateString(),
            'outcome' => InspectionOutcome::Fail->value,
            'notes' => 'Corroded body',
        ])
        ->assertRedirect();

    $equipment->refresh();

    expect($equipment->status)->toBe(EquipmentStatus::OutOfService)
        ->and($equipment->next_inspection_due?->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and(Alert::query()->where('alert_type', AlertType::System)->count())->toBe(1);
});

it('treats retired as terminal except documents', function () {
    $user = User::factory()->withRole('Safety Manager')->create();
    $equipment = Equipment::factory()->create(['status' => EquipmentStatus::Retired]);

    $this->actingAs($user)
        ->post(route('equipment.inspections.store', $equipment), [
            'inspected_at' => now()->toDateString(),
            'outcome' => InspectionOutcome::Pass->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message');
});

it('flags overdue equipment with a deduped alert and resolves when cleared', function () {
    $equipment = Equipment::factory()->create([
        'next_inspection_due' => now()->subDay()->toDateString(),
    ]);

    $service = app(EquipmentService::class);
    expect($service->flagOverdue())->toBe(1)
        ->and($service->flagOverdue())->toBe(1);

    expect(Alert::query()->where('dedupe_key', 'equipment_overdue:'.$equipment->id)->count())->toBe(1);

    $user = User::factory()->withRole('Safety Manager')->create();
    app(EquipmentService::class)->syncSchedules($equipment, [
        ['schedule_type' => 'inspection', 'interval_days' => 30],
    ]);

    $this->actingAs($user)
        ->post(route('equipment.inspections.store', $equipment), [
            'inspected_at' => now()->toDateString(),
            'outcome' => InspectionOutcome::Pass->value,
        ])
        ->assertRedirect();

    expect(Alert::query()
        ->where('dedupe_key', 'equipment_overdue:'.$equipment->id)
        ->where('status', 'open')
        ->count())->toBe(0);
});

it('renders zpl encoding the public qr url and falls back when printer missing', function () {
    $equipment = Equipment::factory()->create();
    $labels = app(EquipmentLabelService::class);

    $zpl = $labels->zpl($equipment);
    expect($zpl)->toContain('^XA')
        ->and($zpl)->toContain('/e/'.$equipment->qr_token)
        ->and($zpl)->toContain($equipment->equipment_code);

    $result = $labels->printLabels([$equipment]);
    expect($result['sent'])->toBeFalse()
        ->and($result['printed'])->toBeFalse()
        ->and($result['zpl'])->toContain('^XA');
});

it('downloads qr as png svg and zpl attachments', function () {
    $user = User::factory()->withRole('Safety Manager')->create();
    $equipment = Equipment::factory()->create();

    $this->actingAs($user)
        ->get(route('equipment.qr', ['equipment' => $equipment, 'format' => 'png']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$equipment->equipment_code.'.png"');

    $this->actingAs($user)
        ->get(route('equipment.qr', ['equipment' => $equipment, 'format' => 'svg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$equipment->equipment_code.'.svg"');

    $this->actingAs($user)
        ->get(route('equipment.qr', ['equipment' => $equipment, 'format' => 'zpl']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$equipment->equipment_code.'.zpl"')
        ->assertSee('/e/'.$equipment->qr_token, false);
});

it('imports csv with partial success and generates qr tokens for new rows', function () {
    Storage::fake('private');
    $user = User::factory()->withRole('Safety Manager')->create();

    $csv = implode("\n", [
        'equipment_code,name,equipment_type,inspection_interval_days',
        'EQ-100,Harness A,safety harness,30',
        ',Missing Type,,',
    ]);

    $file = UploadedFile::fake()->createWithContent('equipment.csv', $csv);

    $this->actingAs($user)
        ->post(route('equipment.import.store'), ['file' => $file])
        ->assertRedirect(route('equipment.import'));

    $item = Equipment::query()->firstOrFail();
    $token = $item->qr_token;

    expect(Equipment::query()->count())->toBe(1)
        ->and($item->equipment_code)->toBe('EQ-100')
        ->and(Str::isUuid((string) $token))->toBeTrue()
        ->and(AuditLog::query()->where('event_type', 'config_changed')->where('payload->target', 'equipment_import')->count())->toBe(1);

    $updateCsv = implode("\n", [
        'equipment_code,name,equipment_type,inspection_interval_days',
        'EQ-100,Harness A Updated,safety harness,45',
    ]);
    $updateFile = UploadedFile::fake()->createWithContent('equipment-update.csv', $updateCsv);

    $this->post(route('equipment.import.store'), ['file' => $updateFile])
        ->assertRedirect(route('equipment.import'));

    expect(Equipment::query()->count())->toBe(1)
        ->and($item->fresh()?->name)->toBe('Harness A Updated')
        ->and($item->fresh()?->qr_token)->toBe($token);
});

it('resolves by token and drives checkout then return with open-checkout guards', function () {
    $user = User::factory()->withRole('Safety Manager')->create();
    $worker = Worker::factory()->create();
    $equipment = Equipment::factory()->checkoutable()->create();

    $this->actingAs($user)
        ->getJson(route('api.equipment.by-token', $equipment->qr_token))
        ->assertOk()
        ->assertJsonPath('data.checkout_state', 'available')
        ->assertJsonPath('data.qr_token', $equipment->qr_token);

    $this->post(route('equipment.checkout', $equipment), [
        'worker_id' => $worker->id,
        'reason' => 'tower work',
    ])->assertRedirect();

    expect(EquipmentCheckout::query()->whereNull('returned_at')->count())->toBe(1);

    $duplicate = $this->from(route('equipment.show', $equipment))
        ->post(route('equipment.checkout', $equipment), [
            'worker_id' => $worker->id,
        ]);
    $duplicate->assertRedirect(route('equipment.show', $equipment))
        ->assertSessionHas('inertia.flash_data.toast.message');

    $nonCheckoutable = Equipment::factory()->create(['is_checkoutable' => false]);
    $this->from(route('equipment.show', $nonCheckoutable))
        ->post(route('equipment.checkout', $nonCheckoutable), [
            'worker_id' => $worker->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('equipment');

    expect(EquipmentCheckout::query()->where('equipment_id', $nonCheckoutable->id)->count())->toBe(0);

    $checkout = EquipmentCheckout::query()->whereNull('returned_at')->firstOrFail();

    $this->post(route('equipment.checkouts.return', $checkout), [
        'return_status' => 'ok',
        'condition_in' => 'fine',
    ])->assertRedirect();

    expect($checkout->fresh()?->returned_at)->not->toBeNull()
        ->and(app(EquipmentService::class)->checkoutState($equipment->fresh() ?? $equipment)->value)->toBe('available');
});

it('blocks worker offboarding while an open checkout exists', function () {
    $user = User::factory()->withRole('Safety Manager')->create();
    $worker = Worker::factory()->create();
    $equipment = Equipment::factory()->checkoutable()->create();

    EquipmentCheckout::factory()->create([
        'equipment_id' => $equipment->id,
        'worker_id' => $worker->id,
        'returned_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('tracking.workers.offboard', $worker))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message');
});

it('serves the public page without auth and rejects writes', function () {
    $equipment = Equipment::factory()->create([
        'status' => EquipmentStatus::Retired,
    ]);

    $this->get(route('public.equipment.show', $equipment->qr_token))
        ->assertOk()
        ->assertSee('RETIRED', false)
        ->assertSee($equipment->equipment_code, false)
        ->assertDontSee('equipment_id', false)
        ->assertDontSee('/equipment/'.$equipment->id, false)
        ->assertDontSee('/equipment/'.$equipment->uuid, false);

    $this->post(route('public.equipment.show', $equipment->qr_token))
        ->assertStatus(405);
});

it('gates operator equipment routes by permission', function () {
    $viewer = User::factory()->withRole('Field Staff')->create();
    $equipment = Equipment::factory()->create();

    $this->actingAs($viewer)
        ->get(route('equipment.index'))
        ->assertForbidden();

    $manager = User::factory()->withRole('Safety Manager')->create();
    $this->actingAs($manager)
        ->get(route('equipment.index'))
        ->assertOk();

    $this->actingAs($manager)
        ->get(route('equipment.show', $equipment))
        ->assertOk();
});

it('records corrective maintenance and can return to service', function () {
    $user = User::factory()->withRole('Safety Manager')->create();
    $equipment = Equipment::factory()->create([
        'status' => EquipmentStatus::OutOfService,
    ]);

    $this->actingAs($user)
        ->post(route('equipment.maintenances.store', $equipment), [
            'performed_at' => now()->toDateString(),
            'maintenance_type' => MaintenanceType::Corrective->value,
            'description' => 'Replaced valve and pressure-tested.',
            'return_to_service' => true,
        ])
        ->assertRedirect();

    expect($equipment->fresh()?->status)->toBe(EquipmentStatus::InService);
});
