<?php

namespace App\Models;

use App\Enums\WorkerType;
use App\Models\Concerns\HasCreatedBy;
use Database\Factories\WorkerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tracked site personnel — never authenticates (DOC-04). Distinct from User.
 *
 * @property int $id
 * @property string $name
 * @property string|null $employee_code
 * @property string|null $badge_number
 * @property string $contractor
 * @property string|null $role_title
 * @property WorkerType $worker_type
 * @property string|null $phone
 * @property string|null $photo_path
 * @property string|null $notes
 * @property bool $is_active
 * @property bool $present
 * @property Carbon|null $last_seen_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class Worker extends Model
{
    /** @use HasFactory<WorkerFactory> */
    use HasCreatedBy, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'worker_type' => WorkerType::class,
            'is_active' => 'boolean',
            'present' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function anonymizedLabel(): string
    {
        return "Worker #{$this->id}";
    }

    /**
     * @return HasMany<EquipmentCheckout, $this>
     */
    public function equipmentCheckouts(): HasMany
    {
        return $this->hasMany(EquipmentCheckout::class);
    }

    /**
     * @return HasMany<IncidentPersonnel, $this>
     */
    public function incidentPersonnel(): HasMany
    {
        return $this->hasMany(IncidentPersonnel::class);
    }

    /**
     * @return BelongsToMany<HseIncident, $this>
     */
    public function hseIncidents(): BelongsToMany
    {
        return $this->belongsToMany(HseIncident::class, 'incident_personnel')
            ->withPivot('involvement')
            ->withTimestamps();
    }

    /**
     * @return HasMany<LsrViolation, $this>
     */
    public function lsrViolations(): HasMany
    {
        return $this->hasMany(LsrViolation::class);
    }
}
