<?php

use App\Enums\AlertType;
use App\Enums\PermitStatus;
use App\Enums\WorkerDocumentVerificationStatus;
use App\Models\Alert;
use App\Models\Permit;
use App\Models\PermitType;
use App\Models\PermitTypeConflict;
use App\Models\PermitTypeRole;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Models\WorkerDocumentType;
use App\Models\WorkerPosition;
use App\Models\WorkOrder;
use App\Models\Zone;
use App\Services\PermitDetectionService;
use Database\Seeders\PermitCatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(PermitCatalogueSeeder::class);
});

/**
 * Mandatory cold-work checklist answers for submit/issue gates.
 *
 * @return array<string, bool>
 */
function coldWorkChecklist(): array
{
    return [
        'jsa_complete' => true,
        'tools_inspected' => true,
        'area_barricaded' => true,
    ];
}

/**
 * Advance a cold-work permit through joint inspection to pending issue.
 */
function advanceColdWorkPermitToPendingIssue(Permit $permit, User $actor): Permit
{
    test()->actingAs($actor)
        ->put(route('permits.update', $permit), [
            'zone_id' => $permit->zone_id,
            'task_description' => $permit->task_description,
            'checklist' => coldWorkChecklist(),
        ])
        ->assertRedirect();

    test()->actingAs($actor)
        ->post(route('permits.submit', $permit))
        ->assertRedirect();

    test()->actingAs($actor)
        ->post(route('permits.inspect', $permit), ['as' => 'issuer'])
        ->assertRedirect();

    test()->actingAs($actor)
        ->post(route('permits.inspect', $permit), ['as' => 'receiver'])
        ->assertRedirect();

    $permit = $permit->fresh() ?? $permit;
    expect($permit->status)->toBe(PermitStatus::PendingIssue);

    return $permit;
}

it('cannot issue a draft permit without going through the workflow', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $coldWork = PermitType::query()->where('code', 'cold_work')->firstOrFail();
    $zone = Zone::factory()->create();

    $this->actingAs($admin)
        ->post(route('permits.store'), [
            'permit_type_id' => $coldWork->id,
            'zone_id' => $zone->id,
            'task_description' => 'Replace gasket on flange.',
        ])
        ->assertRedirect();

    $permit = Permit::query()->firstOrFail();
    expect($permit->status)->toBe(PermitStatus::Draft);

    $this->actingAs($admin)
        ->postJson(route('permits.issue', $permit))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('completes cold work flow through inspection to issue', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $coldWork = PermitType::query()->where('code', 'cold_work')->firstOrFail();
    $zone = Zone::factory()->create();

    $this->actingAs($admin)
        ->post(route('permits.store'), [
            'permit_type_id' => $coldWork->id,
            'zone_id' => $zone->id,
            'task_description' => 'Mechanical maintenance — no ignition sources.',
            'checklist' => coldWorkChecklist(),
        ])
        ->assertRedirect();

    $permit = Permit::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('permits.submit', $permit))
        ->assertRedirect();

    expect($permit->fresh()?->status)->toBe(PermitStatus::PendingInspection);

    $this->actingAs($admin)
        ->post(route('permits.inspect', $permit), ['as' => 'issuer'])
        ->assertRedirect();

    expect($permit->fresh()?->status)->toBe(PermitStatus::PendingInspection);

    $this->actingAs($admin)
        ->post(route('permits.inspect', $permit), ['as' => 'receiver'])
        ->assertRedirect();

    expect($permit->fresh()?->status)->toBe(PermitStatus::PendingIssue);

    $this->actingAs($admin)
        ->post(route('permits.issue', $permit))
        ->assertRedirect();

    $permit = $permit->fresh() ?? $permit;
    expect($permit->status)->toBe(PermitStatus::Active)
        ->and($permit->issued_at)->not->toBeNull()
        ->and($permit->valid_to)->not->toBeNull();
});

it('blocks hot work store when fire watch worker lacks required documents', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $hotWork = PermitType::query()->where('code', 'hot_work')->firstOrFail();
    $zone = Zone::factory()->create();
    $fireWatch = Worker::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('permits.store'), [
            'permit_type_id' => $hotWork->id,
            'zone_id' => $zone->id,
            'task_description' => 'Welding on pipe support.',
            'personnel' => [
                [
                    'worker_id' => $fireWatch->id,
                    'role_code' => 'fire_watch',
                ],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['personnel']);

    expect(Permit::query()->count())->toBe(0);
});

it('allows hot work store when fire watch has verified documents', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $hotWork = PermitType::query()->where('code', 'hot_work')->firstOrFail();
    $zone = Zone::factory()->create();
    $fireWatch = Worker::factory()->create();

    $fireWatchType = WorkerDocumentType::query()->where('code', 'fire_watch')->firstOrFail();
    $medicalType = WorkerDocumentType::query()->where('code', 'medical_fitness')->firstOrFail();
    $h2sType = WorkerDocumentType::query()->where('code', 'h2s_awareness')->firstOrFail();

    foreach ([$fireWatchType, $medicalType, $h2sType] as $documentType) {
        WorkerDocument::query()->create([
            'worker_id' => $fireWatch->id,
            'worker_document_type_id' => $documentType->id,
            'expires_at' => now()->addYear(),
            'file_path' => 'worker-docs/'.$fireWatch->id.'/'.uniqid('', true).'.pdf',
            'verification_status' => WorkerDocumentVerificationStatus::Verified,
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'uploaded_by' => $admin->id,
        ]);
    }

    $this->actingAs($admin)
        ->post(route('permits.store'), [
            'permit_type_id' => $hotWork->id,
            'zone_id' => $zone->id,
            'task_description' => 'Welding on pipe support.',
            'personnel' => [
                [
                    'worker_id' => $fireWatch->id,
                    'role_code' => 'fire_watch',
                ],
            ],
        ])
        ->assertRedirect();

    expect(Permit::query()->count())->toBe(1);
});

it('stores a worker document attachment on the private disk', function (): void {
    Storage::fake('private');

    $admin = User::factory()->withRole('Super Admin')->create();
    $worker = Worker::factory()->create();
    $documentType = WorkerDocumentType::query()->where('code', 'medical_fitness')->firstOrFail();

    $file = UploadedFile::fake()->create('fitness.pdf', 120, 'application/pdf');

    $this->actingAs($admin)
        ->from(route('tracking.workers.show', $worker))
        ->post(route('workers.documents.store', $worker), [
            'worker_document_type_id' => $documentType->id,
            'document_number' => 'MED-001',
            'expires_at' => now()->addYear()->toDateString(),
            'file' => $file,
        ])
        ->assertRedirect(route('tracking.workers.show', $worker));

    $document = WorkerDocument::query()->where('worker_id', $worker->id)->firstOrFail();

    expect($document->file_path)->not->toBeNull()
        ->and($document->file_path)->toStartWith('worker-docs/'.$worker->id.'/')
        ->and(Storage::disk('private')->exists($document->file_path))->toBeTrue();
});

it('rejects document upload without a file when the type requires one', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $worker = Worker::factory()->create();
    $documentType = WorkerDocumentType::query()->where('code', 'medical_fitness')->firstOrFail();

    expect($documentType->requires_file)->toBeTrue();

    $this->actingAs($admin)
        ->from(route('tracking.workers.show', $worker))
        ->post(route('workers.documents.store', $worker), [
            'worker_document_type_id' => $documentType->id,
            'document_number' => 'MED-002',
        ])
        ->assertRedirect(route('tracking.workers.show', $worker))
        ->assertSessionHasErrors('file');

    expect(WorkerDocument::query()->where('worker_id', $worker->id)->count())->toBe(0);
});

it('lists and creates crew roles for a permit type', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $hotWork = PermitType::query()->where('code', 'hot_work')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('settings.crew-roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('access/crew-roles/index')
            ->has('roles')
            ->has('permitTypes'));

    $this->actingAs($admin)
        ->get(route('settings.permit-types.show', $hotWork))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workforce/permit-types/show')
            ->where('permitType.code', 'hot_work')
            ->has('permitType.roles')
            ->has('permitType.checklist_items')
            ->has('permitType.gas_channels')
            ->has('permitType.conflicts')
            ->has('permitType.document_requirements')
            ->has('otherTypes')
            ->has('documentTypes'));

    $this->actingAs($admin)
        ->post(route('settings.crew-roles.store'), [
            'permit_type_id' => $hotWork->id,
            'role_code' => 'spotter',
            'label' => 'Spotter',
            'min_count' => 1,
            'is_mandatory' => true,
        ])
        ->assertRedirect();

    expect(PermitTypeRole::query()
        ->where('permit_type_id', $hotWork->id)
        ->where('role_code', 'spotter')
        ->exists())->toBeTrue();
});

it('manages nested catalogue rows on a permit type', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $coldWork = PermitType::query()->where('code', 'cold_work')->firstOrFail();
    $hotWork = PermitType::query()->where('code', 'hot_work')->firstOrFail();
    $medical = WorkerDocumentType::query()->where('code', 'medical_fitness')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('settings.permit-types.checklist-items.store', $coldWork), [
            'code' => 'site_specific_barrier',
            'label' => 'Site-specific barrier installed',
            'is_mandatory' => true,
        ])
        ->assertRedirect();

    expect($coldWork->checklistItems()->where('code', 'site_specific_barrier')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('settings.permit-types.gas-channels.store', $coldWork), [
            'channel_code' => 'custom_voc',
            'label' => 'VOC',
            'unit' => 'ppm',
            'alarm_above' => 50,
        ])
        ->assertRedirect();

    expect($coldWork->gasChannels()->where('channel_code', 'custom_voc')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('settings.permit-types.conflicts.store', $coldWork), [
            'conflicts_with_type_id' => $hotWork->id,
            'scope' => 'same_zone',
            'severity' => 'warn',
            'note' => 'Prefer sequential cold then hot work',
        ])
        ->assertRedirect();

    expect($coldWork->conflicts()->where('conflicts_with_type_id', $hotWork->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('settings.permit-types.document-requirements.store', $coldWork), [
            'worker_document_type_id' => $medical->id,
            'role_code' => null,
            'is_mandatory' => true,
            'must_be_verified' => true,
        ])
        ->assertRedirect();

    expect($coldWork->documentRequirements()
        ->where('worker_document_type_id', $medical->id)
        ->exists())->toBeTrue();

    $this->actingAs($admin)
        ->put(route('settings.permit-types.update', $coldWork), [
            'name' => 'Cold Work (Updated)',
            'default_validity_minutes' => 360,
            'max_renewals' => 2,
            'requires_gas_test' => true,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($coldWork->fresh()?->name)->toBe('Cold Work (Updated)')
        ->and($coldWork->fresh()?->default_validity_minutes)->toBe(360);
});

it('creates and deactivates worker document types in the catalogue', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();

    $this->actingAs($admin)
        ->get(route('settings.worker-document-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workforce/worker-document-types/index')
            ->has('documentTypes')
            ->has('categories'));

    $this->actingAs($admin)
        ->post(route('settings.worker-document-types.store'), [
            'code' => 'gas_tester',
            'name' => 'Gas Tester Competence',
            'category' => 'competence',
            'requires_expiry' => true,
            'requires_file' => true,
            'sort_order' => 95,
            'is_active' => true,
        ])
        ->assertRedirect();

    $documentType = WorkerDocumentType::query()->where('code', 'gas_tester')->firstOrFail();

    expect($documentType->name)->toBe('Gas Tester Competence')
        ->and($documentType->is_active)->toBeTrue();

    $worker = Worker::factory()->create();
    WorkerDocument::query()->create([
        'worker_id' => $worker->id,
        'worker_document_type_id' => $documentType->id,
        'expires_at' => now()->addYear(),
        'file_path' => 'worker-docs/'.$worker->id.'/test.pdf',
        'verification_status' => WorkerDocumentVerificationStatus::Pending,
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('settings.worker-document-types.update', $documentType), [
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($documentType->fresh()?->is_active)->toBeFalse()
        ->and(WorkerDocument::query()->where('worker_document_type_id', $documentType->id)->count())->toBe(1);
});

it('rejects a pending worker document', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $worker = Worker::factory()->create();
    $documentType = WorkerDocumentType::query()->where('code', 'medical_fitness')->firstOrFail();

    $document = WorkerDocument::query()->create([
        'worker_id' => $worker->id,
        'worker_document_type_id' => $documentType->id,
        'document_number' => 'MED-REJECT',
        'expires_at' => now()->addYear(),
        'file_path' => 'worker-docs/'.$worker->id.'/reject.pdf',
        'verification_status' => WorkerDocumentVerificationStatus::Pending,
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->from(route('tracking.workers.show', $worker))
        ->post(route('workers.documents.reject', [$worker, $document]))
        ->assertRedirect(route('tracking.workers.show', $worker));

    $document = $document->fresh() ?? $document;

    expect($document->verification_status)->toBe(WorkerDocumentVerificationStatus::Rejected)
        ->and($document->verified_by)->toBeNull()
        ->and($document->verified_at)->toBeNull();
});

it('blocks submit when mandatory checklist items are incomplete', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $coldWork = PermitType::query()->where('code', 'cold_work')->firstOrFail();
    $zone = Zone::factory()->create();

    $this->actingAs($admin)
        ->post(route('permits.store'), [
            'permit_type_id' => $coldWork->id,
            'zone_id' => $zone->id,
            'task_description' => 'Incomplete checklist test.',
            'checklist' => [
                'jsa_complete' => true,
            ],
        ])
        ->assertRedirect();

    $permit = Permit::query()->firstOrFail();

    $this->actingAs($admin)
        ->postJson(route('permits.submit', $permit))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['checklist']);

    expect($permit->fresh()?->status)->toBe(PermitStatus::Draft);
});

it('blocks submit when mandatory crew role minimums are not met', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $coldWork = PermitType::query()->where('code', 'cold_work')->firstOrFail();
    $zone = Zone::factory()->create();

    PermitTypeRole::query()->updateOrCreate(
        ['permit_type_id' => $coldWork->id, 'role_code' => 'spotter'],
        [
            'label' => 'Spotter',
            'min_count' => 2,
            'is_mandatory' => true,
            'sort_order' => 99,
        ],
    );

    $this->actingAs($admin)
        ->post(route('permits.store'), [
            'permit_type_id' => $coldWork->id,
            'zone_id' => $zone->id,
            'task_description' => 'Cold work with insufficient spotters.',
            'checklist' => coldWorkChecklist(),
        ])
        ->assertRedirect();

    $permit = Permit::query()->firstOrFail();

    $this->actingAs($admin)
        ->postJson(route('permits.submit', $permit))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['personnel']);

    expect($permit->fresh()?->status)->toBe(PermitStatus::Draft);
});

it('blocks advancing past gas test when a custom channel reading fails', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $coldWork = PermitType::query()->where('code', 'cold_work')->firstOrFail();
    $zone = Zone::factory()->create();

    $coldWork->update([
        'requires_gas_test' => true,
        'requires_joint_inspection' => false,
    ]);

    $this->actingAs($admin)
        ->post(route('settings.permit-types.gas-channels.store', $coldWork), [
            'channel_code' => 'custom_voc',
            'label' => 'VOC',
            'unit' => 'ppm',
            'alarm_above' => 50,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('permits.store'), [
            'permit_type_id' => $coldWork->id,
            'zone_id' => $zone->id,
            'task_description' => 'Cold work with VOC monitoring.',
            'checklist' => coldWorkChecklist(),
        ])
        ->assertRedirect();

    $permit = Permit::query()->firstOrFail();
    expect($permit->fresh()?->status)->toBe(PermitStatus::Draft);

    $this->actingAs($admin)
        ->post(route('permits.submit', $permit))
        ->assertRedirect();

    expect($permit->fresh()?->status)->toBe(PermitStatus::PendingGasTest);

    $this->actingAs($admin)
        ->post(route('permits.gas-tests.store', $permit), [
            'readings' => [
                'custom_voc' => 75,
            ],
            'source' => 'manual',
            'phase' => 'pre_start',
        ])
        ->assertRedirect();

    expect($permit->fresh()?->status)->toBe(PermitStatus::PendingGasTest);

    $this->actingAs($admin)
        ->postJson(route('permits.issue', $permit))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('updates a draft permit and re-runs worker document gates on personnel', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $hotWork = PermitType::query()->where('code', 'hot_work')->firstOrFail();
    $zone = Zone::factory()->create();
    $fireWatch = Worker::factory()->create();

    $this->actingAs($admin)
        ->post(route('permits.store'), [
            'permit_type_id' => $hotWork->id,
            'zone_id' => $zone->id,
            'task_description' => 'Initial draft task.',
        ])
        ->assertRedirect();

    $permit = Permit::query()->firstOrFail();

    $this->actingAs($admin)
        ->putJson(route('permits.update', $permit), [
            'zone_id' => $zone->id,
            'task_description' => 'Updated draft task.',
            'checklist' => [
                'area_cleared' => true,
                'extinguisher_ready' => true,
                'gas_test_complete' => true,
                'fire_watch_assigned' => true,
            ],
            'personnel' => [
                [
                    'worker_id' => $fireWatch->id,
                    'role_code' => 'fire_watch',
                ],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['personnel']);

    $permit = $permit->fresh() ?? $permit;

    $this->actingAs($admin)
        ->put(route('permits.update', $permit), [
            'zone_id' => $zone->id,
            'task_description' => 'Updated draft task without crew.',
            'checklist' => [
                'area_cleared' => true,
                'extinguisher_ready' => true,
                'gas_test_complete' => true,
                'fire_watch_assigned' => true,
            ],
            'personnel' => [],
        ])
        ->assertRedirect(route('permits.show', $permit));

    expect($permit->fresh()?->task_description)->toBe('Updated draft task without crew.')
        ->and($permit->fresh()?->personnel)->toHaveCount(0);
});

it('creates a work order', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $zone = Zone::factory()->create();

    $this->actingAs($admin)
        ->post(route('work-orders.store'), [
            'reference' => 'WO-TEST-001',
            'description' => 'Turnaround mechanical package',
            'zone_id' => $zone->id,
        ])
        ->assertRedirect();

    $workOrder = WorkOrder::query()->where('reference', 'WO-TEST-001')->first();

    expect($workOrder)->not->toBeNull()
        ->and($workOrder->zone_id)->toBe($zone->id)
        ->and($workOrder->status)->toBe('open');
});

it('updates zone requires_permit flag', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $zone = Zone::factory()->create(['requires_permit' => false]);

    $this->actingAs($admin)
        ->put(route('settings.zones.update', $zone), [
            'requires_permit' => true,
        ])
        ->assertRedirect();

    expect($zone->fresh()?->requires_permit)->toBeTrue();
});

it('expires active permits past valid_to via permits tick', function (): void {
    $admin = User::factory()->withRole('Super Admin')->create();
    $coldWork = PermitType::query()->where('code', 'cold_work')->firstOrFail();
    $zone = Zone::factory()->create();

    $this->actingAs($admin)
        ->post(route('permits.store'), [
            'permit_type_id' => $coldWork->id,
            'zone_id' => $zone->id,
            'task_description' => 'Scheduled expiry test.',
        ])
        ->assertRedirect();

    $permit = Permit::query()->firstOrFail();
    $permit = advanceColdWorkPermitToPendingIssue($permit, $admin);

    $this->actingAs($admin)
        ->post(route('permits.issue', $permit))
        ->assertRedirect();

    $permit = $permit->fresh() ?? $permit;
    expect($permit->status)->toBe(PermitStatus::Active);

    $permit->update([
        'valid_to' => now()->subMinute(),
    ]);

    Artisan::call('ir4:permits-tick');

    expect($permit->fresh()?->status)->toBe(PermitStatus::Expired);
});

it('seeds hot work vs confined space simops conflict', function (): void {
    $hotWork = PermitType::query()->where('code', 'hot_work')->firstOrFail();
    $confinedSpace = PermitType::query()->where('code', 'confined_space')->firstOrFail();

    expect(PermitTypeConflict::query()
        ->where('permit_type_id', $hotWork->id)
        ->where('conflicts_with_type_id', $confinedSpace->id)
        ->where('scope', 'same_zone')
        ->where('severity', 'block')
        ->exists())->toBeTrue();
});

it('raises work without permit alert during detection', function (): void {
    $zone = Zone::factory()->create(['requires_permit' => true]);
    $worker = Worker::factory()->create();

    WorkerPosition::factory()->create([
        'worker_id' => $worker->id,
        'zone_id' => $zone->id,
        'is_on_site' => true,
        'last_seen_at' => now(),
    ]);

    app(PermitDetectionService::class)->run();

    expect(Alert::query()
        ->where('alert_type', AlertType::System)
        ->where('dedupe_key', "ptw:no_permit:{$zone->id}:{$worker->id}")
        ->exists())->toBeTrue();
});
