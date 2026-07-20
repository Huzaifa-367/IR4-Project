<?php

namespace App\Models;

use App\Enums\ZoneType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreatedBy;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property ZoneType $zone_type
 * @property bool $requires_authorization
 * @property bool $requires_permit
 * @property int|null $occupancy_limit
 * @property string|null $map_x
 * @property string|null $map_y
 * @property string|null $map_radius
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $radius_meters
 * @property string|null $color
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use Auditable, HasCreatedBy, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zone_type' => ZoneType::class,
            'requires_authorization' => 'boolean',
            'requires_permit' => 'boolean',
            'is_active' => 'boolean',
            'map_x' => 'decimal:2',
            'map_y' => 'decimal:2',
            'map_radius' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<ReaderZoneBinding, $this>
     */
    public function bindings(): HasMany
    {
        return $this->hasMany(ReaderZoneBinding::class);
    }

    /**
     * @return HasMany<ReaderZoneBinding, $this>
     */
    public function currentBindings(): HasMany
    {
        return $this->hasMany(ReaderZoneBinding::class)->whereNull('bound_until');
    }

    /**
     * @return HasMany<ZoneAccessListEntry, $this>
     */
    public function accessList(): HasMany
    {
        return $this->hasMany(ZoneAccessListEntry::class);
    }

    /**
     * @return BelongsToMany<Worker, $this>
     */
    public function authorizedWorkers(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'zone_access_lists')
            ->withTimestamps()
            ->withPivot(['authorized_by', 'authorized_at']);
    }

    /**
     * @return HasMany<EquipmentCheckout, $this>
     */
    public function equipmentCheckouts(): HasMany
    {
        return $this->hasMany(EquipmentCheckout::class);
    }

    /**
     * @return HasMany<HseIncident, $this>
     */
    public function hseIncidents(): HasMany
    {
        return $this->hasMany(HseIncident::class);
    }

    /**
     * @return HasMany<LsrViolation, $this>
     */
    public function lsrViolations(): HasMany
    {
        return $this->hasMany(LsrViolation::class);
    }

    /**
     * @return HasMany<Permit, $this>
     */
    public function permits(): HasMany
    {
        return $this->hasMany(Permit::class);
    }
}
