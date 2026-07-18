<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property Carbon $bucket_start
 * @property array<string, array{min: float, avg: float, max: float}>|null $extra_stats
 * @property int $sample_count
 */
final class EnvironmentalRollup extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bucket_start' => 'datetime',
            'temp_min' => 'decimal:2',
            'temp_avg' => 'decimal:2',
            'temp_max' => 'decimal:2',
            'humidity_min' => 'decimal:2',
            'humidity_avg' => 'decimal:2',
            'humidity_max' => 'decimal:2',
            'wind_min' => 'decimal:2',
            'wind_avg' => 'decimal:2',
            'wind_max' => 'decimal:2',
            'extra_stats' => 'array',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
