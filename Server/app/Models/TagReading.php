<?php

namespace App\Models;

use Database\Factories\TagReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tag_id
 * @property int $reader_device_id
 * @property int|null $zone_id
 * @property Carbon $recorded_at
 * @property Carbon $received_at
 * @property int|null $rssi
 * @property bool $is_backfill
 * @property bool $clock_skew
 * @property string $event_uid
 */
final class TagReading extends Model
{
    /** @use HasFactory<TagReadingFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
            'is_backfill' => 'boolean',
            'clock_skew' => 'boolean',
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
     * @return BelongsTo<Device, $this>
     */
    public function reader(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'reader_device_id');
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
