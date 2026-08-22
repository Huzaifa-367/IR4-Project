<?php

namespace App\Console\Commands;

use App\Models\RfidTag;
use App\Services\LegacyRfidTagPurgeService;
use App\Support\LegacyRfidTagUids;
use Illuminate\Console\Command;

final class PurgeLegacyRfidTagsCommand extends Command
{
    protected $signature = 'ir4:purge-legacy-rfid-tags';

    protected $description = 'Remove legacy E280 dummy RFID tags and re-point related rows to site EPCs';

    public function handle(LegacyRfidTagPurgeService $purge): int
    {
        $pending = RfidTag::query()
            ->where(function ($query): void {
                $query->where('tag_uid', 'like', 'E280%')
                    ->orWhereIn('tag_uid', LegacyRfidTagUids::known());
            })
            ->count();

        if ($pending === 0) {
            $this->info('No legacy dummy RFID tags found.');

            return self::SUCCESS;
        }

        $this->warn("Purging {$pending} legacy dummy tag(s)…");

        foreach ($purge->purge() as $row) {
            $this->line(sprintf(
                '%s → %s (readings %d, positions %d, entry/exit %d%s)',
                $row['legacy_uid'],
                $row['replacement_uid'],
                $row['readings_moved'],
                $row['positions_moved'],
                $row['entry_logs_moved'],
                $row['worker_reassigned'] ? ', worker moved' : '',
            ));
        }

        $this->info('Legacy dummy RFID tags removed.');

        return self::SUCCESS;
    }
}
