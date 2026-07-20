<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PermitType extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_gas_test' => 'boolean',
            'requires_approver' => 'boolean',
            'requires_joint_inspection' => 'boolean',
            'allows_extended' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PermitTypeChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(PermitTypeChecklistItem::class);
    }

    /**
     * @return HasMany<PermitTypeRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(PermitTypeRole::class);
    }

    /**
     * @return HasMany<PermitTypeGasChannel, $this>
     */
    public function gasChannels(): HasMany
    {
        return $this->hasMany(PermitTypeGasChannel::class);
    }

    /**
     * @return HasMany<PermitTypeConflict, $this>
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(PermitTypeConflict::class);
    }

    /**
     * @return HasMany<PermitTypeDocumentRequirement, $this>
     */
    public function documentRequirements(): HasMany
    {
        return $this->hasMany(PermitTypeDocumentRequirement::class);
    }

    /**
     * @return HasMany<Permit, $this>
     */
    public function permits(): HasMany
    {
        return $this->hasMany(Permit::class);
    }
}
