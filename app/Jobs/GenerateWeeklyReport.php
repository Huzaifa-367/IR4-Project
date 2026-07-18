<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WeeklyReport;
use App\Services\WeeklyReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

final class GenerateWeeklyReport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $periodStart,
        public string $periodEnd,
        public ?int $userId = null,
        public bool $auto = false,
        public ?int $supersedesReportId = null,
    ) {
        $this->onQueue('reports');
    }

    public function handle(WeeklyReportService $reports): void
    {
        $by = $this->userId !== null ? User::query()->find($this->userId) : null;
        $supersedes = $this->supersedesReportId !== null
            ? WeeklyReport::query()->find($this->supersedesReportId)
            : null;

        $reports->generate(
            start: Carbon::parse($this->periodStart),
            end: Carbon::parse($this->periodEnd),
            by: $by,
            auto: $this->auto,
            supersedes: $supersedes,
        );
    }
}
