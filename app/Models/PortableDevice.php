<?php

namespace App\Models;

use App\Enums\PortableDeviceStatus;
use Database\Factories\PortableDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $worker_id
 * @property string $device_type
 * @property string|null $make_model
 * @property string|null $serial_number
 * @property string|null $approval_reference
 * @property PortableDeviceStatus $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $revoked_by
 * @property Carbon|null $revoked_at
 * @property string|null $revoke_reason
 */
final class PortableDevice extends Model
{
    /** @use HasFactory<PortableDeviceFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PortableDeviceStatus::class,
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
