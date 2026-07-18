<?php

namespace App\Models;

use App\Enums\MaintenanceType;
use App\Models\Concerns\HasCreatedBy;
use Database\Factories\EquipmentMaintenanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class EquipmentMaintenance extends Model
{
    /** @use HasFactory<EquipmentMaintenanceFactory> */
    use HasCreatedBy, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'maintenance_type' => MaintenanceType::class,
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
