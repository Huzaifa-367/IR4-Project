<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EnvironmentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $sensor
     */
    public function __construct(
        public array $sensor,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('environment')];
    }

    public function broadcastAs(): string
    {
        return 'EnvironmentUpdated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['sensor' => $this->sensor];
    }
}
