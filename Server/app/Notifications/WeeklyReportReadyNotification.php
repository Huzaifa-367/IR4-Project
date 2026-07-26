<?php

namespace App\Notifications;

use App\Models\WeeklyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class WeeklyReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WeeklyReport $report,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'weekly_report_ready',
            'report_id' => $this->report->id,
            'report_number' => $this->report->report_number,
            'period_start' => optional($this->report->period_start)?->toDateString(),
            'period_end' => optional($this->report->period_end)?->toDateString(),
            'message' => "Weekly report {$this->report->report_number} is ready for review.",
        ];
    }
}
