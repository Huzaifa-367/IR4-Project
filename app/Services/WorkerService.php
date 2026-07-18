<?php

namespace App\Services;

use App\Enums\WorkerType;
use App\Models\AuditLog;
use App\Models\Worker;
use App\Models\WorkerImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class WorkerService
{
    /**
     * @param  array{
     *     name: string,
     *     contractor: string,
     *     worker_type: string|WorkerType,
     *     role_title?: string|null,
     *     badge_number?: string|null,
     *     employee_code?: string|null,
     *     phone?: string|null,
     *     notes?: string|null,
     *     photo?: UploadedFile|null
     * }  $data
     */
    public function create(array $data): Worker
    {
        $photoPath = $this->storePhoto($data['photo'] ?? null);

        return Worker::query()->create([
            'name' => $data['name'],
            'contractor' => $data['contractor'],
            'worker_type' => $data['worker_type'],
            'role_title' => $data['role_title'] ?? null,
            'badge_number' => $data['badge_number'] ?? null,
            'employee_code' => $data['employee_code'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'photo_path' => $photoPath,
            'is_active' => true,
            'present' => false,
        ]);
    }

    /**
     * @param  array{
     *     name?: string,
     *     contractor?: string,
     *     worker_type?: string|WorkerType,
     *     role_title?: string|null,
     *     badge_number?: string|null,
     *     employee_code?: string|null,
     *     phone?: string|null,
     *     notes?: string|null,
     *     photo?: UploadedFile|null
     * }  $data
     */
    public function update(Worker $worker, array $data): Worker
    {
        $beforeIdentity = [
            'name' => $worker->name,
            'badge_number' => $worker->badge_number,
            'employee_code' => $worker->employee_code,
            'phone' => $worker->phone,
        ];

        if (array_key_exists('photo', $data) && $data['photo'] instanceof UploadedFile) {
            $this->deletePhoto($worker->photo_path);
            $worker->photo_path = $this->storePhoto($data['photo']);
        }

        foreach (['name', 'contractor', 'worker_type', 'role_title', 'badge_number', 'employee_code', 'phone', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $worker->{$field} = $data[$field];
            }
        }

        $worker->save();

        $afterIdentity = [
            'name' => $worker->name,
            'badge_number' => $worker->badge_number,
            'employee_code' => $worker->employee_code,
            'phone' => $worker->phone,
        ];

        if ($beforeIdentity !== $afterIdentity) {
            $this->audit('config_changed', [
                'target' => 'worker_identity',
                'worker_id' => $worker->id,
                'before' => $beforeIdentity,
                'after' => $afterIdentity,
            ]);
        }

        return $worker->fresh() ?? $worker;
    }

    public function deactivate(Worker $worker): Worker
    {
        $this->assertCanLeaveWorkforce($worker);
        $worker->forceFill(['is_active' => false])->save();

        $this->audit('config_changed', [
            'target' => 'worker_deactivate',
            'worker_id' => $worker->id,
        ]);

        return $worker;
    }

    public function reactivate(Worker $worker): Worker
    {
        $worker->forceFill(['is_active' => true])->save();

        $this->audit('config_changed', [
            'target' => 'worker_reactivate',
            'worker_id' => $worker->id,
        ]);

        return $worker;
    }

    /**
     * Unassign tag (when DOC-09 tables exist) + deactivate in one transaction.
     */
    public function offboard(Worker $worker): Worker
    {
        return DB::transaction(function () use ($worker): Worker {
            if ($worker->present) {
                throw new HttpException(409, 'Worker is on site; ensure they have exited before offboarding.');
            }

            if ($this->hasOpenEquipmentCheckout($worker)) {
                throw new HttpException(409, 'Worker still has open equipment checkouts; return or reassign items first.');
            }

            $this->unassignTagIfPresent($worker);
            $worker->forceFill(['is_active' => false])->save();

            $this->audit('config_changed', [
                'target' => 'worker_offboard',
                'worker_id' => $worker->id,
            ]);

            return $worker->fresh() ?? $worker;
        });
    }

    public function destroy(Worker $worker): void
    {
        $this->assertCanLeaveWorkforce($worker);
        $this->deletePhoto($worker->photo_path);
        $worker->delete();

        $this->audit('config_changed', [
            'target' => 'worker_soft_delete',
            'worker_id' => $worker->id,
        ]);
    }

    /**
     * Derived presence mirror — only path ② (TrackingService / DOC-09) should call this.
     */
    public function syncPresenceMirror(Worker $worker, bool $present, ?\DateTimeInterface $lastSeenAt = null): void
    {
        $worker->forceFill([
            'present' => $present,
            'last_seen_at' => $lastSeenAt ?? ($present ? now() : $worker->last_seen_at),
        ])->save();
    }

    public function beginImport(UploadedFile $file, int $userId): WorkerImport
    {
        $storedPath = $file->storeAs(
            'imports/workers/'.now()->format('Y/m/d'),
            Str::uuid()->toString().'.csv',
            'private',
        );

        return WorkerImport::query()->create([
            'created_by' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => 'pending',
        ]);
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, message: string}>, flagged: list<array{row: int, message: string}>}
     */
    public function processImport(WorkerImport $import): array
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
        $flagged = [];
        $seenBadges = [];
        $seenCodes = [];

        try {
            while (($cells = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($header === null) {
                    $header = array_map(static fn ($h): string => Str::of((string) $h)->trim()->lower()->toString(), $cells);

                    continue;
                }

                if ($this->rowIsEmpty($cells)) {
                    continue;
                }

                $row = $this->mapImportRow($header, $cells);

                try {
                    $validated = $this->validateImportRow($row, $seenBadges, $seenCodes);
                } catch (ValidationException $e) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => collect($e->errors())->flatten()->implode(' '),
                    ];

                    continue;
                }

                if (($validated['badge_number'] ?? null) !== null) {
                    $seenBadges[strtolower((string) $validated['badge_number'])] = $rowNumber;
                }

                if (($validated['employee_code'] ?? null) !== null) {
                    $seenCodes[strtolower((string) $validated['employee_code'])] = $rowNumber;
                }

                $existing = $this->findImportMatch($validated);

                if ($existing instanceof Worker) {
                    $this->update($existing, $validated);
                    $updated++;

                    continue;
                }

                if ($this->hasNameContractorCollision($validated)) {
                    $flagged[] = [
                        'row' => $rowNumber,
                        'message' => 'Possible duplicate (name + contractor) without badge/employee_code — skipped for confirmation.',
                    ];
                    $skipped++;

                    continue;
                }

                $this->create($validated);
                $created++;
            }
        } finally {
            fclose($handle);
        }

        $summary = compact('created', 'updated', 'skipped', 'errors', 'flagged');

        $import->forceFill([
            'status' => 'completed',
            'summary' => $summary,
        ])->save();

        $this->audit('config_changed', [
            'target' => 'worker_import',
            'import_id' => $import->id,
            'filename' => $import->original_filename,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'error_count' => count($errors),
        ]);

        return $summary;
    }

    private function assertCanLeaveWorkforce(Worker $worker): void
    {
        if ($worker->present) {
            throw new HttpException(409, 'Worker is on site; ensure they have exited before offboarding.');
        }

        if ($this->hasAssignedTag($worker)) {
            throw new HttpException(409, 'Worker still has an assigned RFID tag; unassign it first or use offboard.');
        }

        if ($this->hasOpenEquipmentCheckout($worker)) {
            throw new HttpException(409, 'Worker still has open equipment checkouts; return or reassign items first.');
        }
    }

    private function unassignTagIfPresent(Worker $worker): void
    {
        if (! Schema::hasTable('rfid_tags')) {
            return;
        }

        app(TagService::class)->unassignWorkerTags($worker);
    }

    private function hasAssignedTag(Worker $worker): bool
    {
        if (! Schema::hasTable('rfid_tags')) {
            return false;
        }

        return DB::table('rfid_tags')
            ->where('worker_id', $worker->id)
            ->where('status', 'assigned')
            ->exists();
    }

    private function hasOpenEquipmentCheckout(Worker $worker): bool
    {
        if (! Schema::hasTable('equipment_checkouts')) {
            return false;
        }

        return DB::table('equipment_checkouts')
            ->where('worker_id', $worker->id)
            ->whereNull('returned_at')
            ->whereNull('deleted_at')
            ->exists();
    }

    private function storePhoto(?UploadedFile $photo): ?string
    {
        if ($photo === null) {
            return null;
        }

        return $photo->store('workers/photos/'.now()->format('Y/m/d'), 'private');
    }

    private function deletePhoto(?string $path): void
    {
        if ($path === null) {
            return;
        }

        Storage::disk('private')->delete($path);
    }

    /**
     * @param  list<string|null>  $cells
     * @return array<string, string|null>
     */
    private function mapImportRow(array $header, array $cells): array
    {
        $row = [];

        foreach ($header as $index => $column) {
            $row[$column] = isset($cells[$index]) ? trim((string) $cells[$index]) : null;
            if ($row[$column] === '') {
                $row[$column] = null;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, int>  $seenBadges
     * @param  array<string, int>  $seenCodes
     * @return array{name: string, contractor: string, worker_type: string, role_title: ?string, badge_number: ?string, employee_code: ?string, phone: ?string, notes: ?string}
     */
    private function validateImportRow(array $row, array $seenBadges, array $seenCodes): array
    {
        $name = $row['name'] ?? null;
        $contractor = $row['contractor'] ?? null;
        $workerType = $row['worker_type'] ?? null;

        $errors = [];

        if ($name === null || strlen($name) > 150) {
            $errors['name'] = ['Name is required (max 150).'];
        }

        if ($contractor === null || strlen($contractor) > 150) {
            $errors['contractor'] = ['Contractor is required (max 150).'];
        }

        if ($workerType === null || WorkerType::tryFrom($workerType) === null) {
            $errors['worker_type'] = ['worker_type must be employee, contractor, or visitor.'];
        }

        $badge = $row['badge_number'] ?? null;
        $code = $row['employee_code'] ?? null;

        if ($badge !== null && isset($seenBadges[strtolower($badge)])) {
            $errors['badge_number'] = ['Duplicate badge_number within file.'];
        }

        if ($code !== null && isset($seenCodes[strtolower($code)])) {
            $errors['employee_code'] = ['Duplicate employee_code within file.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => (string) $name,
            'contractor' => (string) $contractor,
            'worker_type' => (string) $workerType,
            'role_title' => $row['role_title'] ?? null,
            'badge_number' => $badge,
            'employee_code' => $code,
            'phone' => $row['phone'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];
    }

    /**
     * @param  array{badge_number?: string|null, employee_code?: string|null}  $data
     */
    private function findImportMatch(array $data): ?Worker
    {
        if (($data['badge_number'] ?? null) !== null) {
            $byBadge = Worker::query()->where('badge_number', $data['badge_number'])->first();
            if ($byBadge !== null) {
                return $byBadge;
            }
        }

        if (($data['employee_code'] ?? null) !== null) {
            return Worker::query()->where('employee_code', $data['employee_code'])->first();
        }

        return null;
    }

    /**
     * @param  array{name: string, contractor: string, badge_number?: string|null, employee_code?: string|null}  $data
     */
    private function hasNameContractorCollision(array $data): bool
    {
        if (($data['badge_number'] ?? null) !== null || ($data['employee_code'] ?? null) !== null) {
            return false;
        }

        return Worker::query()
            ->where('name', $data['name'])
            ->where('contractor', $data['contractor'])
            ->exists();
    }

    /**
     * @param  list<string|null>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
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
