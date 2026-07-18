<?php

namespace App\Models;

use App\Enums\GasType;
use App\Enums\ThresholdDirection;
use App\Models\Concerns\Auditable;
use Database\Factories\GasThresholdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property GasType $gas_type
 * @property string $warning_level
 * @property string $alarm_level
 * @property string $unit
 * @property ThresholdDirection $direction
 * @property bool $is_active
 * @property int|null $updated_by
 */
final class GasThreshold extends Model
{
    /** @use HasFactory<GasThresholdFactory> */
    use Auditable, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gas_type' => GasType::class,
            'direction' => ThresholdDirection::class,
            'is_active' => 'boolean',
            'warning_level' => 'decimal:2',
            'alarm_level' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
