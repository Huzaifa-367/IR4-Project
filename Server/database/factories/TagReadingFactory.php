<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\RfidTag;
use App\Models\TagReading;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TagReading>
 */
class TagReadingFactory extends Factory
{
    protected $model = TagReading::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'tag_id' => RfidTag::factory(),
            'reader_device_id' => Device::factory(),
            'zone_id' => null,
            'recorded_at' => $now,
            'received_at' => $now,
            'rssi' => null,
            'is_backfill' => false,
            'clock_skew' => false,
            'event_uid' => (string) Str::uuid(),
        ];
    }
}
