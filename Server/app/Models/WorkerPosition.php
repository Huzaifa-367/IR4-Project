<?php

namespace App\Models;

use Database\Factories\WorkerPositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tag_id
 * @property int $worker_id
 * @property int|null $zone_id
 * @property Carbon $last_seen_at
 * @property bool $is_on_site
 */
final class WorkerPosition extends Model
{
    /** @use HasFactory<WorkerPositionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'is_on_site' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RfidTag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(RfidTag::class, 'tag_id');
    }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
