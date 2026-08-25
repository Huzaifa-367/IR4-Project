<?php

namespace App\Models;

use App\Enums\CameraRoiSetStatus;
use App\Enums\CameraRoiStaleReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $camera_id
 * @property CameraRoiSetStatus $status
 * @property string $view_fingerprint
 * @property Carbon|null $published_at
 * @property Carbon|null $stale_at
 * @property CameraRoiStaleReason|null $stale_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 */
final class CameraRoiSet extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CameraRoiSetStatus::class,
            'stale_reason' => CameraRoiStaleReason::class,
            'published_at' => 'datetime',
            'stale_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Camera, $this>
     */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    /**
     * @return HasMany<CameraRoi, $this>
     */
    public function rois(): HasMany
    {
        return $this->hasMany(CameraRoi::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
