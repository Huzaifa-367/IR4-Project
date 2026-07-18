<?php

namespace App\Models;

use App\Enums\IngestStream;
use Database\Factories\IngestEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Shared Phase-5 ingest receipt / idempotency row (DOC-08).
 *
 * @property int $id
 * @property int $device_id
 * @property IngestStream $stream
 * @property string $event_uid
 * @property Carbon $recorded_at
 * @property Carbon $received_at
 * @property bool $is_backfill
 * @property bool $clock_skew
 * @property array<string, mixed>|null $payload
 */
final class IngestEvent extends Model
{
    /** @use HasFactory<IngestEventFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stream' => IngestStream::class,
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
            'is_backfill' => 'boolean',
            'clock_skew' => 'boolean',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
