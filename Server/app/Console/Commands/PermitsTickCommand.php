<?php

namespace App\Console\Commands;

use App\Services\PermitDetectionService;
use App\Services\PermitService;
use Illuminate\Console\Command;

/**
 * Scheduled Permit-to-Work maintenance tick (DOC-22).
 *
 * Expires overdue permits, suspends stale gas tests, and runs RFID/gas
 * cross-detection (suggests LSR via alerts — never auto-creates LSR rows).
 * Wired from the scheduler (DOC-01 §A8).
 *
 * Usage:
 *   php artisan ir4:permits-tick
 */
final class PermitsTickCommand extends Command
{
    protected $signature = 'ir4:permits-tick';

    protected $description = 'Expire permits, suspend stale gas tests, and run PTW cross-detection';

    public function handle(PermitService $permits, PermitDetectionService $detection): int
    {
        $expired = $permits->expireOverdue();
        $suspended = $permits->suspendStaleGasTests();
        // Cross-detection returns counts of alerts raised this pass.
        $alerts = $detection->run();

        $this->info(sprintf(
            'Expired %d, suspended %d, alerts raised: work_without_permit=%d simops=%d fire_watch=%d',
            $expired,
            $suspended,
            $alerts['work_without_permit'],
            $alerts['simops'],
            $alerts['fire_watch'],
        ));

        return self::SUCCESS;
    }
}
