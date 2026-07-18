<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property AlertType $alert_type
 * @property AlertSeverity $severity
 * @property string $title
 * @property array<string, mixed>|null $payload
 * @property string|null $alertable_type
 * @property int|null $alertable_id
 * @property AlertStatus $status
 * @property Carbon $raised_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 * @property bool $audible
 * @property string|null $dedupe_key
 * @property int $occurrences
 */
final class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alert_type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'payload' => 'array',
            'raised_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'audible' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return HasMany<HseIncident, $this>
     */
    public function hseIncidents(): HasMany
    {
        return $this->hasMany(HseIncident::class);
    }

    /**
     * @return HasMany<LsrViolation, $this>
     */
    public function lsrViolations(): HasMany
    {
        return $this->hasMany(LsrViolation::class);
    }

    public function isOpen(): bool
    {
        return $this->status === AlertStatus::Open;
    }

    public function isResolved(): bool
    {
        return $this->status === AlertStatus::Resolved;
    }
}
