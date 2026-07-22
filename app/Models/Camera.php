<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Enums\CameraType;
use App\Enums\HardwareStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\CameraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_id
 * @property string $name
 * @property string $reference
 * @property CameraType $camera_type
 * @property int|null $processed_by_device_id
 * @property string $stream_url
 * @property bool $ai_enabled
 * @property HardwareStatus $status
 * @property Carbon|null $last_frame_at
 * @property array<string, mixed>|null $meta
 */
final class Camera extends Model
{
    use HasPublicUuid;

    /** @use HasFactory<CameraFactory> */
    use Auditable, HasFactory;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return list<string>
     */
    public function getAuditMaskedAttributes(): array
    {
        return ['stream_url'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'camera_type' => CameraType::class,
            'ai_enabled' => 'boolean',
            'status' => HardwareStatus::class,
            'last_frame_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function processedByDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'processed_by_device_id');
    }

    /**
     * @return HasMany<PpeViolation, $this>
     */
    public function ppeViolations(): HasMany
    {
        return $this->hasMany(PpeViolation::class);
    }

    /**
     * @return HasMany<HseIncident, $this>
     */
    public function hseIncidents(): HasMany
    {
        return $this->hasMany(HseIncident::class);
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
