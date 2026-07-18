<?php

namespace App\Models;

use Database\Factories\EnvironmentalReadingFactory;
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
 * @property string|null $temperature_c
 * @property string|null $humidity_pct
 * @property string|null $wind_speed_ms
 * @property array<string, float|int>|null $extra
 * @property bool $is_backfill
 * @property bool $clock_skew
 * @property string $event_uid
 */
final class EnvironmentalReading extends Model
{
    /** @use HasFactory<EnvironmentalReadingFactory> */
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
            'temperature_c' => 'decimal:2',
            'humidity_pct' => 'decimal:2',
            'wind_speed_ms' => 'decimal:2',
            'extra' => 'array',
            'is_backfill' => 'boolean',
            'clock_skew' => 'boolean',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
