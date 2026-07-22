<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Enums\GasAlarmLevel;
use App\Enums\GasType;
use Database\Factories\GasAlarmFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property int|null $asset_id
 * @property GasType $gas_type
 * @property GasAlarmLevel $level
 * @property string $reading_value
 * @property string $threshold_value
 * @property Carbon $triggered_at
 * @property Carbon|null $resolved_at
 * @property int|null $alert_id
 * @property int|null $acknowledged_by
 * @property Carbon|null $acknowledged_at
 * @property bool $during_outage
 */
final class GasAlarm extends Model
{
    use HasPublicUuid;

    /** @use HasFactory<GasAlarmFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gas_type' => GasType::class,
            'level' => GasAlarmLevel::class,
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'during_outage' => 'boolean',
            'reading_value' => 'decimal:2',
            'threshold_value' => 'decimal:2',
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

    /**
     * @return BelongsTo<Alert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }
}
