<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ReaderZoneBindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property int $zone_id
 * @property Carbon $bound_from
 * @property Carbon|null $bound_until
 * @property int|null $bound_by
 * @property string|null $note
 */
final class ReaderZoneBinding extends Model
{
    /** @use HasFactory<ReaderZoneBindingFactory> */
    use Auditable, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bound_from' => 'datetime',
            'bound_until' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->bound_until === null;
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function reader(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function binder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bound_by');
    }
}
