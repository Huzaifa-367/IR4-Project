<?php

namespace App\Services;

use App\Enums\TagStatus;
use App\Models\AuditLog;
use App\Models\RfidTag;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPosition;
use Illuminate\Support\Facades\DB;
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
