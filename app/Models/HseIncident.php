<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Concerns\HasCreatedBy;
use Database\Factories\HseIncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class HseIncident extends Model
{
    use HasPublicUuid;

    /** @use HasFactory<HseIncidentFactory> */
    use HasCreatedBy, HasFactory, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => IncidentSource::class,
            'status' => IncidentStatus::class,
            'incident_type' => IncidentType::class,
            'severity' => IncidentSeverity::class,
            'occurred_at' => 'datetime',
            'classified_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Alert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
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
    public function classifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<IncidentPersonnel, $this>
     */
    public function personnel(): HasMany
    {
        return $this->hasMany(IncidentPersonnel::class);
    }

    /**
     * @return BelongsToMany<Worker, $this>
     */
    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'incident_personnel')
            ->withPivot('involvement')
            ->withTimestamps();
    }

    /**
     * @return HasMany<IncidentEvidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(IncidentEvidence::class);
    }
}
