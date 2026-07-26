<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\EntryExitSource;
use Database\Factories\EntryExitLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $worker_id
 * @property int|null $tag_id
 * @property int|null $gate_zone_id
 * @property Direction $direction
 * @property Carbon $occurred_at
 * @property EntryExitSource $source
 * @property int|null $corrected_by
 * @property string|null $correction_note
 */
final class EntryExitLog extends Model
{
    /** @use HasFactory<EntryExitLogFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'source' => EntryExitSource::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /**
     * @return BelongsTo<RfidTag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(RfidTag::class, 'tag_id');
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function gateZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'gate_zone_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
