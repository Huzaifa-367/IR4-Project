<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property Carbon $bucket_start
 * @property int $sample_count
 */
final class GasReadingRollup extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bucket_start' => 'datetime',
            'lel_min' => 'decimal:2',
            'lel_avg' => 'decimal:2',
            'lel_max' => 'decimal:2',
            'h2s_min' => 'decimal:2',
            'h2s_avg' => 'decimal:2',
            'h2s_max' => 'decimal:2',
            'o2_min' => 'decimal:2',
            'o2_avg' => 'decimal:2',
            'o2_max' => 'decimal:2',
            'co_min' => 'decimal:2',
            'co_avg' => 'decimal:2',
            'co_max' => 'decimal:2',
            'co2_min' => 'decimal:2',
            'co2_avg' => 'decimal:2',
            'co2_max' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
