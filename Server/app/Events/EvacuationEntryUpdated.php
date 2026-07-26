<?php

namespace App\Events;

use App\Models\EvacuationReportEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EvacuationEntryUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public EvacuationReportEntry $entry,
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
        return 'EvacuationEntryUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'entry_id' => $this->entry->id,
            'report_id' => $this->entry->evacuation_report_id,
            'worker_id' => $this->entry->worker_id,
            'muster_status' => $this->entry->muster_status->value,
            'accounted_source' => $this->entry->accounted_source?->value,
            'accounted_at' => $this->entry->accounted_at?->toIso8601String(),
        ];
    }
}
