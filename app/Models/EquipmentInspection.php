<?php

namespace App\Models;

use App\Enums\InspectionOutcome;
use App\Models\Concerns\HasCreatedBy;
use Database\Factories\EquipmentInspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class EquipmentInspection extends Model
{
    /** @use HasFactory<EquipmentInspectionFactory> */
    use HasCreatedBy, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspected_at' => 'date',
            'outcome' => InspectionOutcome::class,
            'next_due' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
