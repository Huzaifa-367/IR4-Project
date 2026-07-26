<?php

namespace App\Events;

use App\Models\EvacuationReport;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EvacuationTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public EvacuationReport $report,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tracking')];
    }

    public function broadcastAs(): string
    {
        return 'EvacuationTriggered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'report_id' => $this->report->id,
            'triggered_at' => $this->report->triggered_at->toIso8601String(),
        ];
    }
}
