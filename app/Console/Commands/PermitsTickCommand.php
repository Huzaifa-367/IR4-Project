<?php

namespace App\Console\Commands;

use App\Services\PermitDetectionService;
use App\Services\PermitService;
use Illuminate\Console\Command;

final class PermitsTickCommand extends Command
{
    protected $signature = 'ir4:permits-tick';

    protected $description = 'Expire permits, suspend stale gas tests, and run PTW cross-detection';

    public function handle(PermitService $permits, PermitDetectionService $detection): int
    {
        $expired = $permits->expireOverdue();
        $suspended = $permits->suspendStaleGasTests();
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
