<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PermitTypeChecklistItem extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
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
