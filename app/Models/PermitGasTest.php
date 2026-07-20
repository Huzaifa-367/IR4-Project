<?php

namespace App\Models;

use App\Enums\GasTestPhase;
use App\Enums\GasTestResult;
use App\Enums\GasTestSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_id
 * @property Carbon $tested_at
 * @property array<string, float|int|string|null> $readings
 * @property GasTestResult $result
 * @property GasTestSource $source
 * @property int|null $device_id
 * @property int|null $tested_by
 * @property GasTestPhase $phase
 */
final class PermitGasTest extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tested_at' => 'datetime',
            'readings' => 'array',
            'result' => GasTestResult::class,
            'source' => GasTestSource::class,
            'phase' => GasTestPhase::class,
        ];
    }

    /**
     * @return BelongsTo<Permit, $this>
     */
    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }
}
