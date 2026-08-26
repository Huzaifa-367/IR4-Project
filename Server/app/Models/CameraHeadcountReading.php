<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Absolute headcount sample from camera AI — zone snapped from camera binding (path ①).
 *
 * @property int $id
 * @property string $uuid
 * @property int $device_id
 * @property int|null $camera_id
 * @property int|null $zone_id
 * @property Carbon $recorded_at
 * @property Carbon $received_at
 * @property int $count
 * @property bool $is_backfill
 * @property bool $clock_skew
 * @property string $event_uid
 */
final class CameraHeadcountReading extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
            'count' => 'integer',
            'is_backfill' => 'boolean',
            'clock_skew' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<Camera, $this>
     */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
