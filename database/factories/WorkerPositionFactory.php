<?php

namespace Database\Factories;

use App\Models\RfidTag;
use App\Models\Worker;
use App\Models\WorkerPosition;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerPosition>
 */
class WorkerPositionFactory extends Factory
{
    protected $model = WorkerPosition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tag = RfidTag::factory()->assigned();

        return [
            'tag_id' => $tag,
            'worker_id' => fn (array $attrs) => RfidTag::query()->find($attrs['tag_id'])?->worker_id ?? Worker::factory(),
            'zone_id' => null,
            'last_seen_at' => now(),
            'is_on_site' => false,
        ];
    }

    public function onSite(?Zone $zone = null): static
    {
        return $this->state(fn (): array => [
            'is_on_site' => true,
            'zone_id' => $zone?->id ?? Zone::factory(),
            'last_seen_at' => now(),
        ]);
    }
}
