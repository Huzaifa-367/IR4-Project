<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Time-aware camera → zone assignment (parallel to reader_zone_bindings).
 *
 * @property int $id
 * @property int $camera_id
 * @property int $zone_id
 * @property Carbon $bound_from
 * @property Carbon|null $bound_until
 * @property int|null $bound_by
 * @property string|null $note
 */
final class CameraZoneBinding extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bound_from' => 'datetime',
            'bound_until' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->bound_until === null;
    }

    /**
     * @return BelongsTo<Camera, $this>
     */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function binder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bound_by');
    }
}
