<?php

namespace App\Models;

use App\Enums\TagStatus;
use App\Enums\WorkerType;
use App\Models\Concerns\HasCreatedBy;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\WorkerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string|null $nationality
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $joined_on
 * @property string|null $government_id_number
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

    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'worker_type' => WorkerType::class,
            'date_of_birth' => 'date',
            'joined_on' => 'date',
            'is_active' => 'boolean',
            'present' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Derived age from date_of_birth — never stored.
     */
    public function age(): ?int
    {
        if ($this->date_of_birth === null) {
            return null;
        }

        return $this->date_of_birth->age;
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

    /**
     * @return HasMany<RfidTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(RfidTag::class);
    }

    /**
     * @return HasOne<RfidTag, $this>
     */
    public function activeTag(): HasOne
    {
        return $this->hasOne(RfidTag::class)->where('status', TagStatus::Assigned);
    }

    /**
     * @return HasOne<WorkerPosition, $this>
     */
    public function position(): HasOne
    {
        return $this->hasOne(WorkerPosition::class);
    }

    /**
     * @return HasMany<EntryExitLog, $this>
     */
    public function entryExitLogs(): HasMany
    {
        return $this->hasMany(EntryExitLog::class);
    }

    /**
     * @return HasMany<PortableDevice, $this>
     */
    public function portableDevices(): HasMany
    {
        return $this->hasMany(PortableDevice::class);
    }

    /**
     * @return HasMany<EvacuationReportEntry, $this>
     */
    public function evacuationEntries(): HasMany
    {
        return $this->hasMany(EvacuationReportEntry::class);
    }

    /**
     * @return HasMany<WorkerDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(WorkerDocument::class);
    }
}
