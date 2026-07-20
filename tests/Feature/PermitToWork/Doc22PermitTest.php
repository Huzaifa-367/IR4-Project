<?php

use App\Enums\PermitStatus;
use App\Enums\WorkerDocumentVerificationStatus;
use App\Models\Permit;
use App\Models\PermitType;
use App\Models\PermitTypeRole;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Models\WorkerDocumentType;
use App\Models\Zone;
use Database\Seeders\PermitCatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(PermitCatalogueSeeder::class);
});

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
            ->has('permitType.roles'));

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
