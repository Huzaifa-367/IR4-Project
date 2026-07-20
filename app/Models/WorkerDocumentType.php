<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkerDocumentType extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_expiry' => 'boolean',
            'requires_file' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WorkerDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(WorkerDocument::class);
    }

    /**
     * @return HasMany<PermitTypeDocumentRequirement, $this>
     */
    public function permitRequirements(): HasMany
    {
        return $this->hasMany(PermitTypeDocumentRequirement::class);
    }
}
