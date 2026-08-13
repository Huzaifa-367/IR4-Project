<?php

namespace App\Events;

use App\Models\Permit;
use App\Services\PermitService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Permit board delta (DOC-22 §10.3). Payload is the slim board card — no crew names.
 */
final class PermitUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Permit $permit) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('permits')];
    }

    public function broadcastAs(): string
    {
        return 'PermitUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'permit' => app(PermitService::class)->toBoardCard($this->permit),
        ];
    }
}
