<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PermitTypeConflict extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<PermitType, $this>
     */
    public function permitType(): BelongsTo
    {
        return $this->belongsTo(PermitType::class);
    }

    /**
     * @return BelongsTo<PermitType, $this>
     */
    public function conflictsWithType(): BelongsTo
    {
        return $this->belongsTo(PermitType::class, 'conflicts_with_type_id');
    }
}
