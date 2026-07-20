<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PermitTypeGasChannel extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warn_below' => 'decimal:3',
            'warn_above' => 'decimal:3',
            'alarm_below' => 'decimal:3',
            'alarm_above' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<PermitType, $this>
     */
    public function permitType(): BelongsTo
    {
        return $this->belongsTo(PermitType::class);
    }
}
