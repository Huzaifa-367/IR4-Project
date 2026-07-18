<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Enums\ViolationType;
use Database\Factories\PpeViolationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Anonymous PPE site event — never carries worker identity (DOC-10).
 *
 * @property int $id
 * @property int $camera_id
 * @property ViolationType $violation_type
 * @property Carbon $detected_at
 * @property int $worker_count
 * @property string $snapshot_path
 * @property string|null $confidence
 * @property string|null $location_label
 * @property int|null $alert_id
 * @property ReviewStatus $review_status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property bool $is_backfill
 * @property string $event_uid
 */
final class PpeViolation extends Model
{
    /** @use HasFactory<PpeViolationFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'violation_type' => ViolationType::class,
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

    /**
     * @return HasMany<IncidentEvidence, $this>
     */
    public function incidentEvidence(): HasMany
    {
        return $this->hasMany(IncidentEvidence::class);
    }

    /**
     * @return HasMany<LsrViolation, $this>
     */
    public function lsrViolations(): HasMany
    {
        return $this->hasMany(LsrViolation::class);
    }
}
