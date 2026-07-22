<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\CheckoutState;
use App\Enums\EquipmentStatus;
use App\Enums\InspectionOutcome;
use App\Enums\MaintenanceType;
use App\Enums\ScheduleType;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use App\Models\EquipmentDocument;
use App\Models\EquipmentImport;
use App\Models\EquipmentInspection;
use App\Models\EquipmentMaintenance;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EquipmentService
{
    public function __construct(
        private readonly AlertService $alerts,
        private readonly SignedStorageUrlService $signedUrls,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Equipment
    {
        $code = filled($data['equipment_code'] ?? null)
            ? (string) $data['equipment_code']
            : $this->generateEquipmentCode();

        $equipment = Equipment::query()->create([
            'equipment_code' => $code,
            'qr_token' => (string) Str::uuid(),
            'name' => $data['name'],
            'equipment_type' => $data['equipment_type'],
            'status' => EquipmentStatus::InService,
            'is_checkoutable' => (bool) ($data['is_checkoutable']
                ?? $this->settings->get('equipment.default_is_checkoutable', false)),
            'location_label' => $data['location_label'] ?? null,
            'description' => $data['description'] ?? null,
            'next_inspection_due' => $data['next_inspection_due'] ?? null,
            'next_service_due' => $data['next_service_due'] ?? null,
        ]);

        $this->syncSchedulesFromIntervals($equipment, $data);
        $this->recomputeDueDates($equipment->fresh() ?? $equipment);

        $this->audit('config_changed', [
            'target' => 'equipment_create',
            'equipment_id' => $equipment->id,
            'equipment_code' => $equipment->equipment_code,
        ]);

        return $equipment->fresh(['maintenanceSchedules', 'openCheckout.worker']) ?? $equipment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Equipment $equipment, array $data): Equipment
    {
        $this->assertNotRetired($equipment, allowDocuments: false);

        if (array_key_exists('qr_token', $data)) {
            unset($data['qr_token']);
        }

        foreach (['equipment_code', 'name', 'equipment_type', 'location_label', 'description', 'is_checkoutable'] as $field) {
            if (array_key_exists($field, $data)) {
                $equipment->{$field} = $data[$field];
            }
        }

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $this->transitionStatus($equipment, EquipmentStatus::from((string) $data['status']));
        }

        $equipment->save();
        $this->syncSchedulesFromIntervals($equipment, $data);
        $this->recomputeDueDates($equipment->fresh() ?? $equipment);

        $this->audit('config_changed', [
            'target' => 'equipment_update',
            'equipment_id' => $equipment->id,
        ]);

        return $equipment->fresh(['maintenanceSchedules', 'openCheckout.worker']) ?? $equipment;
    }

    public function retire(Equipment $equipment): Equipment
    {
        if ($equipment->status === EquipmentStatus::Retired) {
            return $equipment;
        }

        if ($equipment->openCheckout()->exists()) {
            throw new HttpException(409, 'Return open checkout before retiring this item.');
        }

        $equipment->forceFill(['status' => EquipmentStatus::Retired])->save();

        $this->audit('config_changed', [
            'target' => 'equipment_retire',
            'equipment_id' => $equipment->id,
        ]);

        return $equipment->fresh() ?? $equipment;
    }

    public function destroy(Equipment $equipment): void
    {
        if ($equipment->openCheckout()->exists()) {
            throw new HttpException(409, 'Return open checkout before deleting this item.');
        }

        $equipment->delete();

        $this->audit('config_changed', [
            'target' => 'equipment_soft_delete',
            'equipment_id' => $equipment->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addInspection(Equipment $equipment, array $data, User $inspector): EquipmentInspection
    {
        $this->assertNotRetired($equipment);

        $outcome = $data['outcome'] instanceof InspectionOutcome
            ? $data['outcome']
            : InspectionOutcome::from((string) $data['outcome']);

        $inspection = $equipment->inspections()->create([
            'inspected_at' => $data['inspected_at'],
            'outcome' => $outcome,
            'notes' => $data['notes'] ?? null,
            'inspector_id' => $inspector->id,
            'next_due' => $data['next_due'] ?? null,
        ]);

        if ($outcome === InspectionOutcome::Fail) {
            $equipment->forceFill(['status' => EquipmentStatus::OutOfService])->save();
            $this->alerts->raise(
                AlertType::System,
                AlertSeverity::Warning,
                "Equipment {$equipment->equipment_code} failed inspection",
                [
                    'equipment_id' => $equipment->id,
                    'equipment_code' => $equipment->equipment_code,
                    'inspection_id' => $inspection->id,
                ],
                $equipment,
            );
        }

        $this->recomputeDueDates($equipment->fresh() ?? $equipment);
        $this->resolveOverdueAlertIfCleared($equipment->fresh() ?? $equipment);

        return $inspection->fresh() ?? $inspection;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addMaintenance(Equipment $equipment, array $data, User $recorder): EquipmentMaintenance
    {
        $this->assertNotRetired($equipment);

        $type = $data['maintenance_type'] instanceof MaintenanceType
            ? $data['maintenance_type']
            : MaintenanceType::from((string) $data['maintenance_type']);

        $maintenance = $equipment->maintenances()->create([
            'performed_at' => $data['performed_at'],
            'maintenance_type' => $type,
            'description' => $data['description'],
            'performed_by_name' => $data['performed_by_name'] ?? null,
            'recorded_by' => $recorder->id,
            'next_due' => $data['next_due'] ?? null,
        ]);

        if ($type === MaintenanceType::Corrective && (bool) ($data['return_to_service'] ?? false)) {
            $this->transitionStatus($equipment, EquipmentStatus::InService);
            $equipment->save();
        } elseif ($type === MaintenanceType::Corrective) {
            $this->transitionStatus($equipment, EquipmentStatus::UnderMaintenance);
            $equipment->save();
        }

        $this->recomputeDueDates($equipment->fresh() ?? $equipment);
        $this->resolveOverdueAlertIfCleared($equipment->fresh() ?? $equipment);

        return $maintenance->fresh() ?? $maintenance;
    }

    /**
     * @param  array<int, array{schedule_type: string|ScheduleType, interval_days: int, notes?: string|null}>  $schedules
     */
    public function syncSchedules(Equipment $equipment, array $schedules): Equipment
    {
        $this->assertNotRetired($equipment);

        return DB::transaction(function () use ($equipment, $schedules): Equipment {
            $keep = [];

            foreach ($schedules as $row) {
                $type = $row['schedule_type'] instanceof ScheduleType
                    ? $row['schedule_type']
                    : ScheduleType::from((string) $row['schedule_type']);

                $schedule = MaintenanceSchedule::query()->updateOrCreate(
                    [
                        'equipment_id' => $equipment->id,
                        'schedule_type' => $type->value,
                    ],
                    [
                        'interval_days' => (int) $row['interval_days'],
                        'notes' => $row['notes'] ?? null,
                    ],
                );
                $keep[] = $schedule->id;
            }

            MaintenanceSchedule::query()
                ->where('equipment_id', $equipment->id)
                ->when($keep !== [], fn ($q) => $q->whereNotIn('id', $keep))
                ->when($keep === [], fn ($q) => $q)
                ->delete();

            $fresh = $equipment->fresh() ?? $equipment;
            $this->recomputeDueDates($fresh);

            return $fresh->fresh(['maintenanceSchedules']) ?? $fresh;
        });
    }

    /**
     * @param  array{title: string, file: UploadedFile}  $data
     */
    public function addDocument(Equipment $equipment, array $data, User $uploader): EquipmentDocument
    {
        $file = $data['file'];
        $path = $file->storeAs(
            'equipment-docs/'.$equipment->id,
            (string) Str::uuid().'.pdf',
            'private',
        );

        return $equipment->documents()->create([
            'title' => $data['title'],
            'file_path' => $path,
            'mime' => $file->getMimeType() ?: 'application/pdf',
            'uploaded_by' => $uploader->id,
        ]);
    }

    public function deleteDocument(EquipmentDocument $document): void
    {
        if ($document->file_path !== '') {
            Storage::disk('private')->delete($document->file_path);
        }

        $document->delete();
    }

    public function recomputeDueDates(Equipment $equipment): void
    {
        $inspectionSchedule = $equipment->maintenanceSchedules()
            ->where('schedule_type', ScheduleType::Inspection->value)
            ->first();
        $serviceSchedule = $equipment->maintenanceSchedules()
            ->where('schedule_type', ScheduleType::Service->value)
            ->first();

        $lastInspection = $equipment->inspections()->latest('inspected_at')->first();
        $lastService = $equipment->maintenances()
            ->where('maintenance_type', MaintenanceType::Preventive->value)
            ->latest('performed_at')
            ->first();

        $nextInspection = null;
        if ($lastInspection !== null) {
            $nextInspection = $lastInspection->next_due
                ?? ($inspectionSchedule !== null
                    ? $lastInspection->inspected_at?->copy()->addDays($inspectionSchedule->interval_days)
                    : null);
        }

        $nextService = null;
        if ($lastService !== null) {
            $nextService = $lastService->next_due
                ?? ($serviceSchedule !== null
                    ? $lastService->performed_at?->copy()->addDays($serviceSchedule->interval_days)
                    : null);
        }

        $equipment->forceFill([
            'next_inspection_due' => $nextInspection,
            'next_service_due' => $nextService,
        ])->save();
    }

    public function flagOverdue(): int
    {
        $today = now()->toDateString();
        $count = 0;

        Equipment::query()
            ->where('status', '!=', EquipmentStatus::Retired->value)
            ->where(function ($query) use ($today): void {
                $query->whereDate('next_inspection_due', '<', $today)
                    ->orWhereDate('next_service_due', '<', $today);
            })
            ->orderBy('id')
            ->each(function (Equipment $equipment) use (&$count): void {
                $this->alerts->raise(
                    AlertType::EquipmentOverdue,
                    null,
                    "Equipment {$equipment->equipment_code} is overdue",
                    [
                        'equipment_id' => $equipment->id,
                        'equipment_code' => $equipment->equipment_code,
                        'next_inspection_due' => optional($equipment->next_inspection_due)?->toDateString(),
                        'next_service_due' => optional($equipment->next_service_due)?->toDateString(),
                    ],
                    $equipment,
                    null,
                    'equipment_overdue:'.$equipment->id,
                );
                $count++;
            });

        if ((bool) $this->settings->get('equipment.overdue_return_alerts', false)) {
            EquipmentCheckout::query()
                ->whereNull('returned_at')
                ->whereNotNull('expected_return_at')
                ->where('expected_return_at', '<', now())
                ->with('equipment')
                ->orderBy('id')
                ->each(function (EquipmentCheckout $checkout) use (&$count): void {
                    $equipment = $checkout->equipment;
                    if ($equipment === null || $equipment->status === EquipmentStatus::Retired) {
                        return;
                    }

                    $this->alerts->raise(
                        AlertType::EquipmentOverdue,
                        AlertSeverity::Warning,
                        "Equipment {$equipment->equipment_code} return is overdue",
                        [
                            'equipment_id' => $equipment->id,
                            'equipment_code' => $equipment->equipment_code,
                            'checkout_id' => $checkout->id,
                            'expected_return_at' => optional($checkout->expected_return_at)?->toIso8601String(),
                            'kind' => 'overdue_return',
                        ],
                        $equipment,
                        null,
                        'equipment_overdue_return:'.$checkout->id,
                    );
                    $count++;
                });
        }

        return $count;
    }

    /**
     * @return array{overdue: int, due_soon: int, checked_out: int}
     */
    public function summaryCounts(): array
    {
        $today = now()->startOfDay();
        $soon = now()->addDays(7)->endOfDay();

        return [
            'overdue' => Equipment::query()
                ->where('status', '!=', EquipmentStatus::Retired->value)
                ->where(function ($query) use ($today): void {
                    $query->whereDate('next_inspection_due', '<', $today->toDateString())
                        ->orWhereDate('next_service_due', '<', $today->toDateString());
                })
                ->count(),
            'due_soon' => Equipment::query()
                ->where('status', '!=', EquipmentStatus::Retired->value)
                ->where(function ($query) use ($today, $soon): void {
                    $query->whereBetween('next_inspection_due', [$today->toDateString(), $soon->toDateString()])
                        ->orWhereBetween('next_service_due', [$today->toDateString(), $soon->toDateString()]);
                })
                ->count(),
            'checked_out' => EquipmentCheckout::query()
                ->whereNull('returned_at')
                ->count(),
        ];
    }

    public function checkoutState(Equipment $equipment): CheckoutState
    {
        $open = $equipment->relationLoaded('openCheckout')
            ? $equipment->openCheckout
            : $equipment->openCheckout()->first();

        if ($open === null) {
            return CheckoutState::Available;
        }

        if ($open->expected_return_at !== null && $open->expected_return_at->isPast()) {
            return CheckoutState::OverdueReturn;
        }

        return CheckoutState::CheckedOut;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Equipment $equipment, bool $includeRelations = false, bool $canSeeIdentity = false): array
    {
        $equipment->loadMissing([
            'maintenanceSchedules',
            'openCheckout.worker',
            'openCheckout.zone',
        ]);

        $today = now()->startOfDay();
        $soon = now()->addDays(7)->endOfDay();
        $inspectionDue = $equipment->next_inspection_due;
        $serviceDue = $equipment->next_service_due;
        $isInspectionOverdue = $inspectionDue !== null && $inspectionDue->lt($today);
        $isServiceOverdue = $serviceDue !== null && $serviceDue->lt($today);
        $isDueSoon = (! $isInspectionOverdue && $inspectionDue !== null && $inspectionDue->lte($soon))
            || (! $isServiceOverdue && $serviceDue !== null && $serviceDue->lte($soon));

        $payload = [
            'id' => $equipment->id,
            'uuid' => $equipment->uuid,
            'equipment_code' => $equipment->equipment_code,
            'qr_token' => $equipment->qr_token,
            'public_url' => url('/e/'.$equipment->qr_token),
            'name' => $equipment->name,
            'equipment_type' => $equipment->equipment_type,
            'status' => $equipment->status->value,
            'status_label' => $equipment->status->label(),
            'is_checkoutable' => $equipment->is_checkoutable,
            'location_label' => $equipment->location_label,
            'description' => $equipment->description,
            'next_inspection_due' => optional($inspectionDue)?->toDateString(),
            'next_service_due' => optional($serviceDue)?->toDateString(),
            'checkout_state' => $this->checkoutState($equipment)->value,
            'is_inspection_overdue' => $isInspectionOverdue,
            'is_service_overdue' => $isServiceOverdue,
            'is_due_soon' => $isDueSoon,
            'open_checkout' => $this->checkoutToArray($equipment->openCheckout, $canSeeIdentity),
            'created_at' => optional($equipment->created_at)?->toIso8601String(),
            'updated_at' => optional($equipment->updated_at)?->toIso8601String(),
        ];

        if (! $includeRelations) {
            return $payload;
        }

        $equipment->loadMissing([
            'inspections.inspector',
            'maintenances.recorder',
            'documents.uploader',
            'checkouts.worker',
            'checkouts.zone',
        ]);

        $payload['inspections'] = $equipment->inspections->map(fn (EquipmentInspection $row): array => [
            'id' => $row->id,
            'equipment_id' => $row->equipment_id,
            'inspected_at' => optional($row->inspected_at)?->toDateString(),
            'outcome' => $row->outcome->value,
            'outcome_label' => $row->outcome->label(),
            'notes' => $row->notes,
            'inspector_id' => $row->inspector_id,
            'inspector' => $row->inspector === null ? null : [
                'id' => $row->inspector->id,
                'uuid' => $row->inspector->uuid,
                'name' => $row->inspector->name,
            ],
            'next_due' => optional($row->next_due)?->toDateString(),
            'created_at' => optional($row->created_at)?->toIso8601String(),
        ])->values()->all();

        $payload['maintenances'] = $equipment->maintenances->map(fn (EquipmentMaintenance $row): array => [
            'id' => $row->id,
            'equipment_id' => $row->equipment_id,
            'performed_at' => optional($row->performed_at)?->toDateString(),
            'maintenance_type' => $row->maintenance_type->value,
            'maintenance_type_label' => $row->maintenance_type->label(),
            'description' => $row->description,
            'performed_by_name' => $row->performed_by_name,
            'recorded_by' => $row->recorded_by,
            'recorded_by_user' => $row->recorder === null ? null : [
                'id' => $row->recorder->id,
                'uuid' => $row->recorder->uuid,
                'name' => $row->recorder->name,
            ],
            'next_due' => optional($row->next_due)?->toDateString(),
            'created_at' => optional($row->created_at)?->toIso8601String(),
        ])->values()->all();

        $payload['schedules'] = $equipment->maintenanceSchedules->map(fn (MaintenanceSchedule $schedule): array => [
            'id' => $schedule->id,
            'equipment_id' => $schedule->equipment_id,
            'schedule_type' => $schedule->schedule_type->value,
            'schedule_type_label' => $schedule->schedule_type->label(),
            'interval_days' => $schedule->interval_days,
            'notes' => $schedule->notes,
        ])->values()->all();

        $payload['documents'] = $equipment->documents->map(fn (EquipmentDocument $document): array => [
            'id' => $document->id,
            'uuid' => $document->uuid,
            'equipment_id' => $document->equipment_id,
            'title' => $document->title,
            'mime' => $document->mime,
            'uploaded_by' => $document->uploaded_by,
            'uploaded_by_user' => $document->uploader === null ? null : [
                'id' => $document->uploader->id,
                'uuid' => $document->uploader->uuid,
                'name' => $document->uploader->name,
            ],
            'download_url' => $this->signedUrls->temporaryUrl($document->file_path),
            'created_at' => optional($document->created_at)?->toIso8601String(),
        ])->values()->all();

        $payload['checkouts'] = $equipment->checkouts->map(
            fn (EquipmentCheckout $row): array => $this->checkoutToArray($row, $canSeeIdentity) ?? [],
        )->values()->all();

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function checkoutToArray(?EquipmentCheckout $checkout, bool $canSeeIdentity = false): ?array
    {
        if ($checkout === null) {
            return null;
        }

        $checkout->loadMissing(['worker', 'zone', 'checkedOutByUser', 'returnedToUser']);
        $worker = $checkout->worker;

        return [
            'id' => $checkout->id,
            'uuid' => $checkout->uuid,
            'equipment_id' => $checkout->equipment_id,
            'worker_id' => $checkout->worker_id,
            'worker' => $worker === null ? null : [
                'id' => $worker->id,
                'uuid' => $worker->uuid,
                'name' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
            ],
            'checked_out_at' => optional($checkout->checked_out_at)?->toIso8601String(),
            'checked_out_by' => $checkout->checked_out_by,
            'checked_out_by_user' => $checkout->checkedOutByUser === null ? null : [
                'id' => $checkout->checkedOutByUser->id,
                'uuid' => $checkout->checkedOutByUser->uuid,
                'name' => $checkout->checkedOutByUser->name,
            ],
            'reason' => $checkout->reason,
            'zone_id' => $checkout->zone_id,
            'zone' => $checkout->zone === null ? null : [
                'id' => $checkout->zone->id,
                'uuid' => $checkout->zone->uuid,
                'name' => $checkout->zone->name,
            ],
            'expected_return_at' => optional($checkout->expected_return_at)?->toIso8601String(),
            'returned_at' => optional($checkout->returned_at)?->toIso8601String(),
            'returned_to' => $checkout->returned_to,
            'returned_to_user' => $checkout->returnedToUser === null ? null : [
                'id' => $checkout->returnedToUser->id,
                'uuid' => $checkout->returnedToUser->uuid,
                'name' => $checkout->returnedToUser->name,
            ],
            'condition_out' => $checkout->condition_out,
            'condition_in' => $checkout->condition_in,
            'return_status' => $checkout->return_status?->value,
            'return_reason' => $checkout->return_reason,
            'notes' => $checkout->notes,
            'is_overdue_return' => $checkout->returned_at === null
                && $checkout->expected_return_at !== null
                && $checkout->expected_return_at->isPast(),
        ];
    }

    public function beginImport(UploadedFile $file, int $userId): EquipmentImport
    {
        $storedPath = $file->storeAs(
            'imports/equipment/'.now()->format('Y/m/d'),
            (string) Str::uuid().'.csv',
            'private',
        );

        return EquipmentImport::query()->create([
            'created_by' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => 'pending',
        ]);
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, message: string}>, created_ids: list<int>}
     */
    public function processImport(EquipmentImport $import): array
    {
        $path = Storage::disk('private')->path($import->stored_path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $import->forceFill([
                'status' => 'failed',
                'summary' => ['errors' => [['row' => 0, 'message' => 'Could not open import file.']]],
            ])->save();

            throw new HttpException(500, 'Could not open import file.');
        }

        $header = null;
        $rowNumber = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $createdIds = [];
        $seenCodes = [];

        try {
            while (($cells = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($header === null) {
                    $header = array_map(
                        static fn ($h): string => Str::of((string) $h)->trim()->lower()->toString(),
                        $cells,
                    );

                    continue;
                }

                if ($this->rowIsEmpty($cells)) {
                    continue;
                }

                $row = $this->mapImportRow($header, $cells);

                try {
                    $validated = $this->validateImportRow($row, $seenCodes);
                } catch (ValidationException $e) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => collect($e->errors())->flatten()->implode(' '),
                    ];

                    continue;
                }

                if (($validated['equipment_code'] ?? null) !== null) {
                    $seenCodes[strtolower((string) $validated['equipment_code'])] = $rowNumber;
                }

                $existing = null;
                if (($validated['equipment_code'] ?? null) !== null) {
                    $existing = Equipment::query()
                        ->where('equipment_code', $validated['equipment_code'])
                        ->first();
                }

                if ($existing instanceof Equipment) {
                    $this->update($existing, $validated);
                    $updated++;

                    continue;
                }

                $equipment = $this->create($validated);
                $createdIds[] = $equipment->id;
                $created++;
            }
        } finally {
            fclose($handle);
        }

        $summary = compact('created', 'updated', 'skipped', 'errors', 'createdIds');

        $import->forceFill([
            'status' => 'completed',
            'summary' => $summary,
        ])->save();

        $this->audit('config_changed', [
            'target' => 'equipment_import',
            'import_id' => $import->id,
            'filename' => $import->original_filename,
            'created' => $created,
            'updated' => $updated,
            'error_count' => count($errors),
        ]);

        return $summary;
    }

    private function resolveOverdueAlertIfCleared(Equipment $equipment): void
    {
        $today = now()->toDateString();
        $inspectionOverdue = $equipment->next_inspection_due !== null
            && $equipment->next_inspection_due->toDateString() < $today;
        $serviceOverdue = $equipment->next_service_due !== null
            && $equipment->next_service_due->toDateString() < $today;

        if (! $inspectionOverdue && ! $serviceOverdue) {
            $this->alerts->resolveByDedupeKey('equipment_overdue:'.$equipment->id);
        }
    }

    private function transitionStatus(Equipment $equipment, EquipmentStatus $status): void
    {
        if ($equipment->status === EquipmentStatus::Retired && $status !== EquipmentStatus::Retired) {
            throw new HttpException(422, 'Retired equipment cannot change status.');
        }

        if ($status === EquipmentStatus::Retired) {
            $this->retire($equipment);

            return;
        }

        $allowed = match ($equipment->status) {
            EquipmentStatus::InService => [
                EquipmentStatus::OutOfService,
                EquipmentStatus::UnderMaintenance,
                EquipmentStatus::Retired,
            ],
            EquipmentStatus::OutOfService => [
                EquipmentStatus::InService,
                EquipmentStatus::UnderMaintenance,
                EquipmentStatus::Retired,
            ],
            EquipmentStatus::UnderMaintenance => [
                EquipmentStatus::InService,
                EquipmentStatus::OutOfService,
                EquipmentStatus::Retired,
            ],
            EquipmentStatus::Retired => [],
        };

        if ($status !== $equipment->status && ! in_array($status, $allowed, true)) {
            throw new HttpException(422, 'Invalid equipment status transition.');
        }

        $equipment->status = $status;
    }

    private function assertNotRetired(Equipment $equipment, bool $allowDocuments = false): void
    {
        if ($equipment->status !== EquipmentStatus::Retired) {
            return;
        }

        if ($allowDocuments) {
            return;
        }

        throw new HttpException(422, 'Retired equipment is terminal.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncSchedulesFromIntervals(Equipment $equipment, array $data): void
    {
        $schedules = [];

        if (isset($data['inspection_interval_days']) && $data['inspection_interval_days'] !== null && $data['inspection_interval_days'] !== '') {
            $schedules[] = [
                'schedule_type' => ScheduleType::Inspection,
                'interval_days' => (int) $data['inspection_interval_days'],
            ];
        }

        if (isset($data['service_interval_days']) && $data['service_interval_days'] !== null && $data['service_interval_days'] !== '') {
            $schedules[] = [
                'schedule_type' => ScheduleType::Service,
                'interval_days' => (int) $data['service_interval_days'],
            ];
        }

        if ($schedules !== []) {
            foreach ($schedules as $row) {
                MaintenanceSchedule::query()->updateOrCreate(
                    [
                        'equipment_id' => $equipment->id,
                        'schedule_type' => $row['schedule_type']->value,
                    ],
                    [
                        'interval_days' => $row['interval_days'],
                    ],
                );
            }
        }

        if (isset($data['last_inspection_date']) && filled($data['last_inspection_date'])) {
            $interval = (int) ($data['inspection_interval_days'] ?? 0);
            $equipment->inspections()->firstOrCreate(
                [
                    'inspected_at' => $data['last_inspection_date'],
                    'outcome' => InspectionOutcome::Pass->value,
                ],
                [
                    'notes' => $data['notes'] ?? 'Imported last inspection',
                    'next_due' => $interval > 0
                        ? Carbon::parse((string) $data['last_inspection_date'])->addDays($interval)->toDateString()
                        : null,
                ],
            );
        }
    }

    private function generateEquipmentCode(): string
    {
        do {
            $code = 'EQ-'.strtoupper(Str::random(6));
        } while (Equipment::query()->where('equipment_code', $code)->exists());

        return $code;
    }

    /**
     * @param  list<int|string|null>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        return collect($cells)->every(fn ($cell): bool => trim((string) $cell) === '');
    }

    /**
     * @param  list<string>  $header
     * @param  list<int|string|null>  $cells
     * @return array<string, string|null>
     */
    private function mapImportRow(array $header, array $cells): array
    {
        $row = [];
        foreach ($header as $index => $key) {
            $row[$key] = isset($cells[$index]) ? trim((string) $cells[$index]) : null;
            if ($row[$key] === '') {
                $row[$key] = null;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, int>  $seenCodes
     * @return array<string, mixed>
     */
    private function validateImportRow(array $row, array $seenCodes): array
    {
        $code = $row['equipment_code'] ?? null;
        if ($code !== null && isset($seenCodes[strtolower($code)])) {
            throw ValidationException::withMessages([
                'equipment_code' => ['Duplicate equipment_code in file.'],
            ]);
        }

        if (! filled($row['name'] ?? null) || ! filled($row['equipment_type'] ?? null)) {
            throw ValidationException::withMessages([
                'name' => ['name and equipment_type are required.'],
            ]);
        }

        return [
            'equipment_code' => $code,
            'name' => (string) $row['name'],
            'equipment_type' => (string) $row['equipment_type'],
            'location_label' => $row['location_label'] ?? null,
            'description' => $row['description'] ?? null,
            'inspection_interval_days' => $row['inspection_interval_days'] ?? null,
            'service_interval_days' => $row['service_interval_days'] ?? null,
            'last_inspection_date' => $row['last_inspection_date'] ?? null,
            'notes' => $row['notes'] ?? null,
            'is_checkoutable' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(string $eventType, array $payload): void
    {
        AuditLog::query()->create([
            'event_type' => $eventType,
            'user_id' => auth()->id(),
            'route' => request()->path(),
            'payload' => $payload,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
