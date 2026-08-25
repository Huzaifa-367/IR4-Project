<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $camera_roi_set_id
 * @property int $camera_id
 * @property string $name
 * @property string $reference
 * @property list<array{x: float, y: float}> $polygon
 * @property string $color
 * @property int $sort_order
 * @property bool $is_enabled
 * @property array<string, mixed>|null $meta
 * @property int|null $created_by
 */
final class CameraRoi extends Model
{
    // Rows are hard-replaced on each save/publish — no soft-delete lifecycle.

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'polygon' => 'array',
            'is_enabled' => 'boolean',
            'meta' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CameraRoiSet, $this>
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(CameraRoiSet::class, 'camera_roi_set_id');
    }

    /**
     * @return BelongsTo<Camera, $this>
     */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
