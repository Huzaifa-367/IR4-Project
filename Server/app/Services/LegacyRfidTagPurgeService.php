<?php

namespace App\Services;

use App\Enums\TagStatus;
use App\Models\EntryExitLog;
use App\Models\RfidTag;
use App\Models\TagReading;
use App\Models\WorkerPosition;
use App\Support\LegacyRfidTagUids;
use Illuminate\Support\Facades\DB;

final class LegacyRfidTagPurgeService
{
    /**
     * @return list<array{legacy_uid: string, legacy_id: int, replacement_uid: string, readings_moved: int, positions_moved: int, entry_logs_moved: int, worker_reassigned: bool}>
     */
    public function purge(): array
    {
        $legacyTags = RfidTag::query()
            ->where(function ($query): void {
                $query->where('tag_uid', 'like', 'E280%')
                    ->orWhereIn('tag_uid', LegacyRfidTagUids::known());
            })
            ->orderBy('id')
            ->get();

        $summary = [];

        DB::transaction(function () use ($legacyTags, &$summary): void {
            foreach ($legacyTags as $legacy) {
                $replacement = $this->resolveReplacementTag($legacy->tag_uid);
                $readingsMoved = TagReading::query()
                    ->where('tag_id', $legacy->id)
                    ->update(['tag_id' => $replacement->id]);

                $positionsMoved = 0;
                $legacyPosition = WorkerPosition::query()->where('tag_id', $legacy->id)->first();
                if ($legacyPosition !== null) {
                    WorkerPosition::query()->where('tag_id', $replacement->id)->delete();
                    $legacyPosition->forceFill(['tag_id' => $replacement->id])->save();
                    $positionsMoved = 1;
                }

                $entryLogsMoved = EntryExitLog::query()
                    ->where('tag_id', $legacy->id)
                    ->update(['tag_id' => $replacement->id]);

                $workerReassigned = false;
                if ($legacy->worker_id !== null && $legacy->status === TagStatus::Assigned) {
                    $replacement->forceFill([
                        'worker_id' => $legacy->worker_id,
                        'status' => TagStatus::Assigned,
                        'assigned_at' => $legacy->assigned_at,
                        'assigned_by' => $legacy->assigned_by,
                    ])->save();
                    $workerReassigned = true;
                }

                $legacy->forceDelete();

                $summary[] = [
                    'legacy_uid' => $legacy->tag_uid,
                    'legacy_id' => $legacy->id,
                    'replacement_uid' => $replacement->tag_uid,
                    'readings_moved' => $readingsMoved,
                    'positions_moved' => $positionsMoved,
                    'entry_logs_moved' => $entryLogsMoved,
                    'worker_reassigned' => $workerReassigned,
                ];
            }
        });

        return $summary;
    }

    private function resolveReplacementTag(string $legacyUid): RfidTag
    {
        $epc = LegacyRfidTagUids::replacementEpc($legacyUid);

        return RfidTag::query()->firstOrCreate(
            ['tag_uid' => $epc],
            ['status' => TagStatus::InStock],
        );
    }
}
