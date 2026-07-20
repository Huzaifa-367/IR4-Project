<?php

namespace App\Models;

use App\Models\Concerns\HasCreatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WorkOrder extends Model
{
    use HasCreatedBy, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return HasMany<Permit, $this>
     */
    public function permits(): HasMany
    {
        return $this->hasMany(Permit::class);
    }
}
