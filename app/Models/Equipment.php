<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Models\Concerns\HasCreatedBy;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasCreatedBy, HasFactory, SoftDeletes;

    protected $table = 'equipment';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EquipmentStatus::class,
            'is_checkoutable' => 'boolean',
            'next_inspection_due' => 'date',
            'next_service_due' => 'date',
        ];
    }

    /**
     * @return HasMany<EquipmentInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(EquipmentInspection::class);
    }

    /**
     * @return HasMany<EquipmentMaintenance, $this>
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class);
    }

    /**
     * @return HasMany<MaintenanceSchedule, $this>
     */
    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    /**
     * @return HasMany<EquipmentDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class);
    }

    /**
     * @return HasMany<EquipmentCheckout, $this>
     */
    public function checkouts(): HasMany
    {
        return $this->hasMany(EquipmentCheckout::class);
    }

    /**
     * @return HasOne<EquipmentCheckout, $this>
     */
    public function openCheckout(): HasOne
    {
        return $this->hasOne(EquipmentCheckout::class)->whereNull('returned_at')->latestOfMany('checked_out_at');
    }
}
