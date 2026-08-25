<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\CameraType;
use App\Enums\DeviceType;
use App\Enums\HardwareStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUuid;
use App\Support\WeatherSettings;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Field hardware that authenticates via auth.device (DOC-05).
 * Cameras are devices with device_type=camera (stream_url, camera_type, …).
 *
 * @property int $id
 * @property int|null $asset_id
 * @property string $name
 * @property string $reference
 * @property string|null $serial_number
 * @property DeviceType $device_type
 * @property CameraType|null $camera_type
 * @property string|null $stream_url
 * @property bool|null $ai_enabled
 * @property HardwareStatus $status
 * @property string|null $api_token_hash
 * @property Carbon|null $token_issued_at
 * @property array<string, mixed>|null $config
 * @property string|null $api_url
 * @property string|null $printer_host
 * @property int|null $printer_port
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $last_frame_at
 * @property int|null $ptz_generation
 * @property array<string, mixed>|null $meta
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use Auditable, HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return list<string>
     */
    public function getAuditMaskedAttributes(): array
    {
        $masked = ['api_token_hash', 'config'];

        if ($this->isCamera()) {
            $masked[] = 'stream_url';
        }

        return $masked;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'device_type' => DeviceType::class,
            'camera_type' => CameraType::class,
            'ai_enabled' => 'boolean',
            'status' => HardwareStatus::class,
            'token_issued_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_frame_at' => 'datetime',
            'config' => 'array',
            'meta' => 'array',
            'printer_port' => 'integer',
            'ptz_generation' => 'integer',
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

    /**
     * @return HasMany<RoiViolation, $this>
     */
    public function roiViolations(): HasMany
    {
        return $this->hasMany(RoiViolation::class);
    }

    /** @return HasMany<EnvironmentalReading, $this> */
    public function environmentalReadings(): HasMany
    {
        return $this->hasMany(EnvironmentalReading::class);
    }

    /**
     * @return HasMany<PpeViolation, $this>
     */
    public function ppeViolations(): HasMany
    {
        return $this->hasMany(PpeViolation::class, 'camera_id');
    }

    /**
     * @return HasOne<CameraRoiSet, $this>
     */
    public function roiSet(): HasOne
    {
        return $this->hasOne(CameraRoiSet::class, 'camera_id');
    }

    public function isRetired(): bool
    {
        return $this->status === HardwareStatus::Retired;
    }

    public function usesIngestToken(): bool
    {
        return $this->device_type->usesIngestToken();
    }

    public function isCamera(): bool
    {
        return $this->device_type->isCamera();
    }

    public function isQrPrinter(): bool
    {
        return $this->device_type === DeviceType::QrPrinter;
    }

    public function hasToken(): bool
    {
        return $this->usesIngestToken()
            && $this->api_token_hash !== null
            && ! $this->isRetired();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOperational(Builder $query): Builder
    {
        return $query->whereNotIn('status', HardwareStatus::nonOperationalValues());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFieldHardware(Builder $query): Builder
    {
        return $query->where('reference', '!=', WeatherSettings::DEVICE_REFERENCE);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTokenIngest(Builder $query): Builder
    {
        return $query->whereIn('device_type', array_map(
            static fn (DeviceType $type): string => $type->value,
            array_values(array_filter(
                DeviceType::cases(),
                static fn (DeviceType $type): bool => $type->usesIngestToken(),
            )),
        ));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeHealthMonitored(Builder $query): Builder
    {
        return $query
            ->fieldHardware()
            ->tokenIngest()
            ->operational()
            ->where(function (Builder $assetQuery): void {
                $assetQuery
                    ->whereNull('asset_id')
                    ->orWhereHas(
                        'asset',
                        fn (Builder $asset) => $asset->where('status', '!=', AssetStatus::Offline->value),
                    );
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRegistryVisible(Builder $query): Builder
    {
        return $query->fieldHardware();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeStandaloneRegistry(Builder $query): Builder
    {
        return $query->whereIn(
            'device_type',
            array_map(
                static fn (DeviceType $type): string => $type->value,
                DeviceType::registryTypes(),
            ),
        );
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCameras(Builder $query): Builder
    {
        return $query->where('device_type', DeviceType::Camera->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLiveWall(Builder $query): Builder
    {
        return $query->cameras()->operational();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeStreamHealthMonitored(Builder $query): Builder
    {
        return $query->cameras()->operational();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForRegistry(Builder $query): Builder
    {
        return $query->with('asset:id,uuid,name');
    }

    /**
     * @param  Builder<static>  $query
     * @param  DeviceType|list<DeviceType>|list<string>  $types
     * @return Builder<static>
     */
    public function scopeOfType(Builder $query, DeviceType|array $types): Builder
    {
        $values = array_map(
            static fn (DeviceType|string $type): string => $type instanceof DeviceType ? $type->value : $type,
            is_array($types) ? $types : [$types],
        );

        return $query->whereIn('device_type', $values);
    }
}
