<?php

namespace App\Models;

use Database\Factories\GasReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property int|null $asset_id
 * @property Carbon $recorded_at
 * @property Carbon $received_at
 * @property string|null $lel_pct
 * @property string|null $h2s_ppm
 * @property string|null $o2_pct
 * @property string|null $co_ppm
 * @property string|null $co2_ppm
 * @property bool $is_backfill
 * @property bool $clock_skew
 * @property string $event_uid
 */
final class GasReading extends Model
{
    /** @use HasFactory<GasReadingFactory> */
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
            'lel_pct' => 'decimal:2',
            'h2s_ppm' => 'decimal:2',
            'o2_pct' => 'decimal:2',
            'co_ppm' => 'decimal:2',
            'co2_ppm' => 'decimal:2',
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
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
