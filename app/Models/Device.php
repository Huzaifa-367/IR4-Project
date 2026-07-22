<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Field hardware that authenticates via auth.device (DOC-05).
 *
 * @property int $id
 * @property int|null $asset_id
 * @property string $name
 * @property string $reference
 * @property string|null $serial_number
 * @property DeviceType $device_type
 * @property HardwareStatus $status
 * @property string|null $api_token_hash
 * @property Carbon|null $token_issued_at
 * @property array<string, mixed>|null $config
 * @property Carbon|null $last_seen_at
 */
final class Device extends Model
{
    use HasPublicUuid;

    /** @use HasFactory<DeviceFactory> */
    use Auditable, HasFactory;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return list<string>
     */
    public function getAuditMaskedAttributes(): array
    {
        return ['api_token_hash', 'config'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'device_type' => DeviceType::class,
            'status' => HardwareStatus::class,
            'token_issued_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'config' => 'array',
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
     * @return HasMany<ReaderZoneBinding, $this>
     */
    public function zoneBindings(): HasMany
    {
        return $this->hasMany(ReaderZoneBinding::class);
    }

    /**
     * @return HasOne<ReaderZoneBinding, $this>
     */
    public function currentZoneBinding(): HasOne
    {
        return $this->hasOne(ReaderZoneBinding::class)->whereNull('bound_until');
    }

    /** @return HasMany<EnvironmentalReading, $this> */
    public function environmentalReadings(): HasMany
    {
        return $this->hasMany(EnvironmentalReading::class);
    }

    public function isRetired(): bool
    {
        return $this->status === HardwareStatus::Retired;
    }

    public function hasToken(): bool
    {
        return $this->api_token_hash !== null && ! $this->isRetired();
    }
}
