<?php

namespace Database\Factories;

use App\Enums\IngestStream;
use App\Models\Device;
use App\Models\IngestEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IngestEvent>
 */
class IngestEventFactory extends Factory
{
    protected $model = IngestEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $received = now();

        return [
            'device_id' => Device::factory(),
            'stream' => IngestStream::TagReadings,
            'event_uid' => (string) Str::uuid(),
            'recorded_at' => $received,
            'received_at' => $received,
            'is_backfill' => false,
            'clock_skew' => false,
            'payload' => [],
        ];
    }
}
