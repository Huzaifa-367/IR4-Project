<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Enums\RoiViolationType;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Anonymous camera ROI intrusion event — never carries worker identity (DOC-23).
 *
 * @property int $id
 * @property string $uuid
 * @property int $camera_id
 * @property int|null $device_id
 * @property int|null $camera_roi_id
 * @property string $roi_reference
 * @property RoiViolationType $event_type
 * @property Carbon $detected_at
 * @property string|null $confidence
 * @property string|null $snapshot_path
 * @property string|null $location_label
 * @property int|null $alert_id
 * @property ReviewStatus $review_status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property bool $is_backfill
 * @property string $event_uid
 */
final class RoiViolation extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => RoiViolationType::class,
            'review_status' => ReviewStatus::class,
            'detected_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_backfill' => 'boolean',
            'confidence' => 'decimal:2',
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
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<CameraRoi, $this>
     */
    public function cameraRoi(): BelongsTo
    {
        return $this->belongsTo(CameraRoi::class);
    }

    /**
     * @return BelongsTo<Alert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
