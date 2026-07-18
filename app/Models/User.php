<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property bool $must_change_password
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'is_active',
    'must_change_password',
    'password_changed_at',
    'last_login_at',
    'last_login_ip',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * @return list<string>
     */
    public function getAuditMaskedAttributes(): array
    {
        return ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    }

    /**
     * @return list<string>
     */
    public function getAuditExcludedAttributes(): array
    {
        return [
            'created_at',
            'updated_at',
            'deleted_at',
            'last_login_at',
            'last_login_ip',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function primaryRole(): ?Role
    {
        /** @var Role|null $role */
        $role = $this->roles()->first();

        return $role;
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function createdEquipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'created_by');
    }

    /**
     * @return HasMany<EquipmentInspection, $this>
     */
    public function createdEquipmentInspections(): HasMany
    {
        return $this->hasMany(EquipmentInspection::class, 'created_by');
    }

    /**
     * @return HasMany<EquipmentInspection, $this>
     */
    public function equipmentInspections(): HasMany
    {
        return $this->hasMany(EquipmentInspection::class, 'inspector_id');
    }

    /**
     * @return HasMany<EquipmentMaintenance, $this>
     */
    public function createdEquipmentMaintenances(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class, 'created_by');
    }

    /**
     * @return HasMany<EquipmentMaintenance, $this>
     */
    public function recordedEquipmentMaintenances(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class, 'recorded_by');
    }

    /**
     * @return HasMany<MaintenanceSchedule, $this>
     */
    public function createdMaintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class, 'created_by');
    }

    /**
     * @return HasMany<EquipmentDocument, $this>
     */
    public function createdEquipmentDocuments(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class, 'created_by');
    }

    /**
     * @return HasMany<EquipmentDocument, $this>
     */
    public function uploadedEquipmentDocuments(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class, 'uploaded_by');
    }

    /**
     * @return HasMany<EquipmentCheckout, $this>
     */
    public function createdEquipmentCheckouts(): HasMany
    {
        return $this->hasMany(EquipmentCheckout::class, 'created_by');
    }

    /**
     * @return HasMany<EquipmentCheckout, $this>
     */
    public function issuedEquipmentCheckouts(): HasMany
    {
        return $this->hasMany(EquipmentCheckout::class, 'checked_out_by');
    }

    /**
     * @return HasMany<EquipmentCheckout, $this>
     */
    public function receivedEquipmentReturns(): HasMany
    {
        return $this->hasMany(EquipmentCheckout::class, 'returned_to');
    }

    /**
     * @return HasMany<EquipmentImport, $this>
     */
    public function equipmentImports(): HasMany
    {
        return $this->hasMany(EquipmentImport::class, 'created_by');
    }

    /**
     * @return HasMany<HseIncident, $this>
     */
    public function createdHseIncidents(): HasMany
    {
        return $this->hasMany(HseIncident::class, 'created_by');
    }

    /**
     * @return HasMany<HseIncident, $this>
     */
    public function classifiedHseIncidents(): HasMany
    {
        return $this->hasMany(HseIncident::class, 'classified_by');
    }

    /**
     * @return HasMany<HseIncident, $this>
     */
    public function closedHseIncidents(): HasMany
    {
        return $this->hasMany(HseIncident::class, 'closed_by');
    }

    /**
     * @return HasMany<IncidentEvidence, $this>
     */
    public function addedIncidentEvidence(): HasMany
    {
        return $this->hasMany(IncidentEvidence::class, 'added_by');
    }

    /**
     * @return HasMany<LsrViolation, $this>
     */
    public function loggedLsrViolations(): HasMany
    {
        return $this->hasMany(LsrViolation::class, 'logged_by');
    }

    /**
     * @return HasMany<LsrViolation, $this>
     */
    public function closedLsrViolations(): HasMany
    {
        return $this->hasMany(LsrViolation::class, 'closed_by');
    }
}
