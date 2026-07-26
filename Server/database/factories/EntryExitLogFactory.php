<?php

namespace Database\Factories;

use App\Enums\Direction;
use App\Enums\EntryExitSource;
use App\Models\EntryExitLog;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryExitLog>
 */
class EntryExitLogFactory extends Factory
{
    protected $model = EntryExitLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'tag_id' => null,
            'gate_zone_id' => null,
            'direction' => Direction::In,
            'occurred_at' => now(),
            'source' => EntryExitSource::GateReader,
        ];
    }
}
