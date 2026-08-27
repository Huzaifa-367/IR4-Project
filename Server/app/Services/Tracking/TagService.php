<?php

namespace App\Services\Tracking;

use App\Enums\TagStatus;
use App\Models\AuditLog;
use App\Models\RfidTag;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPosition;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TagService
{
    public function create(string $tagUid, ?string $notes = null): RfidTag
    {
        return RfidTag::query()->create([
            'tag_uid' => strtoupper(trim($tagUid)),
            'status' => TagStatus::InStock,
            'notes' => $notes,
        ]);
    }

    /**
     * Bulk-register spare tags from a CSV that has an `epc` column.
     * Existing UIDs (case-insensitive) are skipped; duplicates in-file are ignored.
     *
     * @return array{created: int, skipped: int, invalid: int, total_rows: int}
     */
    public function importFromCsv(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'Could not read CSV file.',
            ]);
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === [null] || $header === []) {
                throw ValidationException::withMessages([
                    'file' => 'CSV is empty.',
                ]);
            }

            $header = array_map(
                static fn (mixed $col): string => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $col))),
                $header,
            );
            $epcIndex = array_search('epc', $header, true);
            if ($epcIndex === false) {
                throw ValidationException::withMessages([
                    'file' => 'CSV must include an epc column.',
                ]);
            }

            /** @var list<string> $candidates */
            $candidates = [];
            $invalid = 0;
            $totalRows = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }
                $totalRows++;
                $raw = isset($row[$epcIndex]) ? trim((string) $row[$epcIndex]) : '';
                if ($raw === '') {
                    $invalid++;

                    continue;
                }
                if (strlen($raw) > 150) {
                    $invalid++;

                    continue;
                }
                $candidates[] = strtoupper($raw);
            }
        } finally {
            fclose($handle);
        }

        $unique = array_values(array_unique($candidates));
        $existing = RfidTag::withTrashed()
            ->whereIn('tag_uid', $unique)
            ->pluck('tag_uid')
            ->map(static fn (string $uid): string => strtoupper($uid))
            ->all();
        $existingSet = array_fill_keys($existing, true);

        $toCreate = [];
        foreach ($unique as $uid) {
            if (isset($existingSet[$uid])) {
                continue;
            }
            $toCreate[] = $uid;
        }

        $created = 0;
        foreach (array_chunk($toCreate, 200) as $chunk) {
            $now = now();
            $rows = array_map(
                static fn (string $uid): array => [
                    'uuid' => (string) Str::uuid(),
                    'tag_uid' => $uid,
                    'status' => TagStatus::InStock->value,
                    'notes' => 'imported from CSV',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $chunk,
            );
            RfidTag::query()->insert($rows);
            $created += count($rows);
        }

        return [
            'created' => $created,
            'skipped' => count($unique) - $created,
            'invalid' => $invalid,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Path ②: first sighting of an EPC creates a spare-pool row (no worker, no position).
     */
    public function firstOrRegisterFromIngest(string $tagUid): RfidTag
    {
        $normalized = strtoupper(trim($tagUid));

        try {
            return RfidTag::query()->firstOrCreate(
                ['tag_uid' => $normalized],
                ['status' => TagStatus::InStock, 'notes' => 'auto-registered from ingest'],
            );
        } catch (UniqueConstraintViolationException) {
            return RfidTag::query()->where('tag_uid', $normalized)->firstOrFail();
        }
    }

    public function assign(RfidTag $tag, Worker $worker, User $by): RfidTag
    {
        return DB::transaction(function () use ($tag, $worker, $by): RfidTag {
            $tag = RfidTag::query()->whereKey($tag->id)->lockForUpdate()->firstOrFail();
            $worker = Worker::query()->whereKey($worker->id)->lockForUpdate()->firstOrFail();

            if ($tag->status !== TagStatus::InStock) {
                throw new HttpException(409, 'Tag is not in stock.');
            }

            if (RfidTag::query()
                ->where('worker_id', $worker->id)
                ->where('status', TagStatus::Assigned)
                ->exists()) {
                throw new HttpException(409, 'Worker already has an assigned tag; use replace instead.');
            }

            $tag->forceFill([
                'worker_id' => $worker->id,
                'status' => TagStatus::Assigned,
                'assigned_at' => now(),
                'assigned_by' => $by->id,
            ])->save();

            WorkerPosition::query()->updateOrCreate(
                ['tag_id' => $tag->id],
                [
                    'worker_id' => $worker->id,
                    'zone_id' => null,
                    'last_seen_at' => now()->subYears(10),
                    'is_on_site' => false,
                ],
            );

            AuditLog::query()->create([
                'event_type' => 'config_changed',
                'user_id' => $by->id,
                'route' => request()->path(),
                'payload' => [
                    'target' => 'tag_assign',
                    'tag_id' => $tag->id,
                    'worker_id' => $worker->id,
                ],
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);

            return $tag->fresh() ?? $tag;
        });
    }

    public function unassign(RfidTag $tag, ?User $by = null): RfidTag
    {
        return DB::transaction(function () use ($tag, $by): RfidTag {
            $tag = RfidTag::query()->whereKey($tag->id)->lockForUpdate()->firstOrFail();

            if ($tag->status !== TagStatus::Assigned) {
                throw new HttpException(409, 'Tag is not assigned.');
            }

            WorkerPosition::query()->where('tag_id', $tag->id)->delete();

            $tag->forceFill([
                'worker_id' => null,
                'status' => TagStatus::InStock,
                'assigned_at' => null,
                'assigned_by' => null,
            ])->save();

            if ($by !== null) {
                AuditLog::query()->create([
                    'event_type' => 'config_changed',
                    'user_id' => $by->id,
                    'route' => request()->path(),
                    'payload' => [
                        'target' => 'tag_unassign',
                        'tag_id' => $tag->id,
                    ],
                    'ip' => request()->ip(),
                    'created_at' => now(),
                ]);
            }

            return $tag->fresh() ?? $tag;
        });
    }

    public function replace(Worker $worker, RfidTag $newTag, TagStatus $oldStatus, User $by): RfidTag
    {
        if (! in_array($oldStatus, [TagStatus::Lost, TagStatus::Damaged], true)) {
            throw new HttpException(422, 'Old tag status must be lost or damaged.');
        }

        return DB::transaction(function () use ($worker, $newTag, $oldStatus, $by): RfidTag {
            $worker = Worker::query()->whereKey($worker->id)->lockForUpdate()->firstOrFail();
            $newTag = RfidTag::query()->whereKey($newTag->id)->lockForUpdate()->firstOrFail();

            /** @var RfidTag|null $old */
            $old = RfidTag::query()
                ->where('worker_id', $worker->id)
                ->where('status', TagStatus::Assigned)
                ->lockForUpdate()
                ->first();

            if ($old === null) {
                throw new HttpException(409, 'Worker has no assigned tag to replace.');
            }

            if ($newTag->status !== TagStatus::InStock) {
                throw new HttpException(409, 'Replacement tag is not in stock.');
            }

            $position = WorkerPosition::query()->where('tag_id', $old->id)->first();

            $old->forceFill([
                'status' => $oldStatus,
                'worker_id' => null,
                'assigned_at' => null,
                'assigned_by' => null,
            ])->save();

            $newTag->forceFill([
                'worker_id' => $worker->id,
                'status' => TagStatus::Assigned,
                'assigned_at' => now(),
                'assigned_by' => $by->id,
            ])->save();

            if ($position !== null) {
                $position->forceFill(['tag_id' => $newTag->id])->save();
            } else {
                WorkerPosition::query()->create([
                    'tag_id' => $newTag->id,
                    'worker_id' => $worker->id,
                    'zone_id' => null,
                    'last_seen_at' => now()->subYears(10),
                    'is_on_site' => false,
                ]);
            }

            AuditLog::query()->create([
                'event_type' => 'config_changed',
                'user_id' => $by->id,
                'route' => request()->path(),
                'payload' => [
                    'target' => 'tag_replace',
                    'worker_id' => $worker->id,
                    'old_tag_id' => $old->id,
                    'new_tag_id' => $newTag->id,
                    'old_tag_status' => $oldStatus->value,
                ],
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);

            return $newTag->fresh() ?? $newTag;
        });
    }

    public function unassignWorkerTags(Worker $worker): void
    {
        RfidTag::query()
            ->where('worker_id', $worker->id)
            ->where('status', TagStatus::Assigned)
            ->each(function (RfidTag $tag): void {
                WorkerPosition::query()->where('tag_id', $tag->id)->delete();
                $tag->forceFill([
                    'worker_id' => null,
                    'status' => TagStatus::InStock,
                    'assigned_at' => null,
                    'assigned_by' => null,
                ])->save();
            });
    }
}
