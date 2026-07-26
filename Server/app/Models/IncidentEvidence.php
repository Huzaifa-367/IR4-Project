<?php

namespace App\Models;

use App\Enums\EvidenceType;
use Database\Factories\IncidentEvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IncidentEvidence extends Model
{
    /** @use HasFactory<IncidentEvidenceFactory> */
    use HasFactory;

    protected $table = 'incident_evidence';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evidence_type' => EvidenceType::class,
            'payload' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<HseIncident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(HseIncident::class, 'hse_incident_id');
    }

    /**
     * @return BelongsTo<PpeViolation, $this>
     */
    public function ppeViolation(): BelongsTo
    {
        return $this->belongsTo(PpeViolation::class);
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
    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function isAutoCaptured(): bool
    {
        return $this->added_by === null;
    }
}
