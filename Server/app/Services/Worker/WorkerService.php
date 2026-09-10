<?php

namespace App\Services\Worker;

use App\Enums\WorkerType;
use App\Models\AuditLog;
use App\Models\Worker;
use App\Models\WorkerImport;
use App\Services\Tracking\TagService;
use App\Services\Tracking\TrackingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
     *     nationality?: string|null,
     *     date_of_birth?: string|null,
     *     joined_on?: string|null,
     *     government_id_number?: string|null,
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
            'nationality' => $data['nationality'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'joined_on' => $data['joined_on'] ?? null,
            'government_id_number' => $data['government_id_number'] ?? null,
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
     *     nationality?: string|null,
     *     date_of_birth?: string|null,
     *     joined_on?: string|null,
     *     government_id_number?: string|null,
     *     badge_number?: string|null,
     *     employee_code?: string|null,
     *     phone?: string|null,
     *     notes?: string|null,
     *     photo?: UploadedFile|null
     * }  $data
     */
    public function update(Worker $worker, array $data): Worker
    {
        $beforeIdentity = $this->identitySnapshot($worker);

        if (array_key_exists('photo', $data) && $data['photo'] instanceof UploadedFile) {
            $this->deletePhoto($worker->photo_path);
            $worker->photo_path = $this->storePhoto($data['photo']);
        }

        foreach ([
            'name',
            'contractor',
            'worker_type',
            'role_title',
            'nationality',
            'date_of_birth',
            'joined_on',
            'government_id_number',
            'badge_number',
            'employee_code',
            'phone',
            'notes',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $worker->{$field} = $data[$field];
            }
        }

        $worker->save();

        $afterIdentity = $this->identitySnapshot($worker);

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

    /**
     * Import from an HTTP upload without persisting the spreadsheet.
     * Row errors are returned for a one-shot flash — not stored on the import row.
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, message: string}>, flagged: list<array{row: int, message: string}>, filename: string, import_id: int}
     */
    public function importUploadedFile(UploadedFile $file, int $userId): array
    {
        $path = $file->getRealPath();
        if ($path === false || $path === '' || ! is_readable($path)) {
            throw new HttpException(422, 'Could not read the uploaded import file.');
        }

        $import = WorkerImport::query()->create([
            'created_by' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            // Never retain the spreadsheet on disk (DOC-04 import is data-only).
            'stored_path' => '',
            'status' => 'pending',
        ]);

        $extension = strtolower($file->getClientOriginalExtension());
        $summary = $this->processImport($import, $path, $extension !== '' ? $extension : null);

        return [
            ...$summary,
            'filename' => $file->getClientOriginalName(),
            'import_id' => $import->id,
        ];
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, message: string}>, flagged: list<array{row: int, message: string}>}
     */
    public function processImport(WorkerImport $import, ?string $absolutePath = null, ?string $extensionHint = null): array
    {
        $path = $absolutePath;
        $storedRelative = trim((string) $import->stored_path);
        if ($path === null || $path === '') {
            if ($storedRelative === '') {
                throw new HttpException(500, 'Import file path is missing.');
            }
            $path = Storage::disk('private')->path($storedRelative);
        }

        $extension = strtolower((string) ($extensionHint ?: pathinfo($path, PATHINFO_EXTENSION)));
        if ($extension === '' && $import->original_filename !== '') {
            $extension = strtolower((string) pathinfo($import->original_filename, PATHINFO_EXTENSION));
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
            foreach ($this->importRows($path, $extension) as $cells) {
                $rowNumber++;

                if ($header === null) {
                    $header = array_map(fn ($h): string => $this->normalizeImportHeader((string) $h), $cells);

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
        } catch (\Throwable $e) {
            $import->forceFill([
                'status' => 'failed',
                'summary' => [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'error_count' => 1,
                    'flagged_count' => count($flagged),
                ],
                'stored_path' => '',
            ])->save();
            $this->forgetStoredImportFile($storedRelative);

            throw $e;
        }

        $summary = compact('created', 'updated', 'skipped', 'errors', 'flagged');

        // Persist counts only — row errors/flags are ephemeral (session flash), not permanent logs.
        $import->forceFill([
            'status' => 'completed',
            'summary' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'error_count' => count($errors),
                'flagged_count' => count($flagged),
            ],
            'stored_path' => '',
        ])->save();
        $this->forgetStoredImportFile($storedRelative);

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

    private function forgetStoredImportFile(string $storedRelative): void
    {
        if ($storedRelative === '') {
            return;
        }

        Storage::disk('private')->delete($storedRelative);
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
     * @return \Generator<int, list<string|null>>
     */
    private function importRows(string $path, ?string $extensionHint = null): \Generator
    {
        $extension = strtolower((string) ($extensionHint ?: pathinfo($path, PATHINFO_EXTENSION)));
        if ($extension === 'xlsx') {
            yield from $this->readXlsxRows($path);

            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new HttpException(500, 'Could not open import file.');
        }

        try {
            while (($cells = fgetcsv($handle)) !== false) {
                yield array_map(static fn ($cell): ?string => $cell === null ? null : (string) $cell, $cells);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return \Generator<int, list<string|null>>
     */
    private function readXlsxRows(string $path): \Generator
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new HttpException(422, 'Could not open Excel import file.');
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $reader = new \XMLReader;
        $sheetPath = 'zip://'.$path.'#xl/worksheets/sheet1.xml';
        if (! $reader->open($sheetPath)) {
            $zip->close();
            throw new HttpException(422, 'Could not read the first Excel worksheet.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $row = simplexml_load_string($reader->readOuterXml());
                if ($row === false) {
                    continue;
                }

                $cells = [];
                foreach ($row->c as $cell) {
                    $reference = (string) $cell['r'];
                    $index = $this->xlsxColumnIndex($reference);
                    $value = (string) ($cell->v ?? '');
                    $type = (string) ($cell['t'] ?? '');

                    if ($type === 's' && $value !== '') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = (string) ($cell->is->t ?? '');
                    }

                    $cells[$index] = $value === '' ? null : $value;
                }

                if ($cells !== [] && array_filter($cells, static fn ($value): bool => $value !== null && trim((string) $value) !== '')) {
                    ksort($cells);
                    $lastIndex = (int) array_key_last($cells);
                    $orderedCells = [];
                    for ($index = 0; $index <= $lastIndex; $index++) {
                        $orderedCells[] = $cells[$index] ?? null;
                    }
                    yield $orderedCells;
                }
            }
        } finally {
            $reader->close();
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function readXlsxSharedStrings(\ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');
        if ($contents === false) {
            return [];
        }

        $xml = simplexml_load_string($contents);
        if ($xml === false) {
            return [];
        }

        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $strings = [];
        foreach ($xml->children($namespace)->si as $item) {
            $strings[] = implode('', array_map(
                static fn ($text): string => (string) $text,
                iterator_to_array($item->children($namespace)->t),
            ));
        }

        return $strings;
    }

    private function xlsxColumnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($reference), $matches);
        $letters = $matches[1] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - ord('A') + 1;
        }

        return $index - 1;
    }

    private function normalizeImportHeader(string $header): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower($header)), '_');
    }

    /**
     * @param  list<string>  $header
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

        if (! array_key_exists('fullnameen', $row)) {
            return $row;
        }

        return [
            'name' => $row['fullnameen'],
            'contractor' => $row['project_cost_center_id'],
            'worker_type' => 'employee',
            'role_title' => $row['job_title'] ?? null,
            'nationality' => $row['nationality'] ?? null,
            'date_of_birth' => $this->normalizeXlsxDate($row['dateofbirth'] ?? null),
            'joined_on' => $this->normalizeXlsxDate($row['hiringdate'] ?? null),
            'government_id_number' => $row['iqama_id'] ?? null,
            'badge_number' => null,
            'employee_code' => $row['employee'] ?? null,
            'phone' => $row['mobile'] ?? null,
            'notes' => $this->mapAramcoNotes($row),
        ];
    }

    private function normalizeXlsxDate(?string $value): ?string
    {
        if ($value === null || ! is_numeric($value)) {
            return $value;
        }

        return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function mapAramcoNotes(array $row): ?string
    {
        $status = $row['empstatusid'] ?? null;
        $expiry = $row['iqama_expire_in_muqeem'] ?? null;
        $notes = [];

        if ($status !== null) {
            $notes[] = 'Aramco status: '.$status;
        }

        if ($expiry !== null && $expiry !== '0') {
            $notes[] = 'Iqama expiry source value: '.$expiry;
        }

        return $notes === [] ? null : implode('; ', $notes);
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, int>  $seenBadges
     * @param  array<string, int>  $seenCodes
     * @return array{
     *     name: string,
     *     contractor: string,
     *     worker_type: string,
     *     role_title: ?string,
     *     nationality: ?string,
     *     date_of_birth: ?string,
     *     joined_on: ?string,
     *     government_id_number: ?string,
     *     badge_number: ?string,
     *     employee_code: ?string,
     *     phone: ?string,
     *     notes: ?string
     * }
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
        $dateOfBirth = $this->parseImportDate($row['date_of_birth'] ?? null, $errors, 'date_of_birth', beforeToday: true);
        $joinedOn = $this->parseImportDate($row['joined_on'] ?? null, $errors, 'joined_on', beforeToday: false);

        if ($badge !== null && isset($seenBadges[strtolower($badge)])) {
            $errors['badge_number'] = ['Duplicate badge_number within file.'];
        }

        if ($code !== null && isset($seenCodes[strtolower($code)])) {
            $errors['employee_code'] = ['Duplicate employee_code within file.'];
        }

        $nationality = $row['nationality'] ?? null;
        if ($nationality !== null && strlen($nationality) > 100) {
            $errors['nationality'] = ['nationality max 100.'];
        }

        $governmentId = $row['government_id_number'] ?? null;
        if ($governmentId !== null && strlen($governmentId) > 100) {
            $errors['government_id_number'] = ['government_id_number max 100.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => (string) $name,
            'contractor' => (string) $contractor,
            'worker_type' => (string) $workerType,
            'role_title' => $row['role_title'] ?? null,
            'nationality' => $nationality,
            'date_of_birth' => $dateOfBirth,
            'joined_on' => $joinedOn,
            'government_id_number' => $governmentId,
            'badge_number' => $badge,
            'employee_code' => $code,
            'phone' => $row['phone'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function parseImportDate(?string $value, array &$errors, string $field, bool $beforeToday): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            $date = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            $errors[$field] = ["{$field} must be a valid date (Y-m-d)."];

            return null;
        }

        if ($beforeToday && $date->greaterThanOrEqualTo(now()->startOfDay())) {
            $errors[$field] = ["{$field} must be before today."];

            return null;
        }

        if (! $beforeToday && $date->greaterThan(now()->startOfDay())) {
            $errors[$field] = ["{$field} must be on or before today."];

            return null;
        }

        return $date->toDateString();
    }

    /**
     * @return array{
     *     name: string,
     *     badge_number: string|null,
     *     employee_code: string|null,
     *     phone: string|null,
     *     date_of_birth: string|null,
     *     government_id_number: string|null
     * }
     */
    private function identitySnapshot(Worker $worker): array
    {
        return [
            'name' => $worker->name,
            'badge_number' => $worker->badge_number,
            'employee_code' => $worker->employee_code,
            'phone' => $worker->phone,
            'date_of_birth' => $worker->date_of_birth?->toDateString(),
            'government_id_number' => $worker->government_id_number,
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
