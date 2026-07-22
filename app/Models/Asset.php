<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Concerns\Auditable;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property AssetType $asset_type
 * @property string $name
 * @property string $identifier
 * @property AssetStatus $status
 * @property bool $is_mobile
 * @property string|null $current_location_label
 * @property Carbon|null $last_heartbeat_at
 * @property array<string, mixed>|null $meta
 */
final class Asset extends Model
{
    use HasPublicUuid;

    /** @use HasFactory<AssetFactory> */
    use Auditable, HasFactory;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_type' => AssetType::class,
            'status' => AssetStatus::class,
            'is_mobile' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return HasMany<Camera, $this>
     */
    public function cameras(): HasMany
    {
        return $this->hasMany(Camera::class);
    }

    /** @return HasMany<EnvironmentalReading, $this> */
    public function environmentalReadings(): HasMany
    {
        return $this->hasMany(EnvironmentalReading::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
