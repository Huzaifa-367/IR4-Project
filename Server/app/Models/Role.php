<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUuid;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_system
 * @property bool $is_read_only
 * @property string|null $description
 */
final class Role extends SpatieRole
{
    use Auditable;
    use HasPublicUuid;

    protected $fillable = [
        'name',
        'guard_name',
        'is_system',
        'is_read_only',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_read_only' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_system && $this->name === 'Super Admin';
    }
}
