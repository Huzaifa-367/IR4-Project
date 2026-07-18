<?php

namespace App\Models;

use App\Enums\EvacuationStatus;
use Database\Factories\EvacuationReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property EvacuationStatus $status
 * @property Carbon $triggered_at
 * @property int $triggered_by
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property bool $force_closed
 * @property string|null $close_note
 */
final class EvacuationReport extends Model
{
    /** @use HasFactory<EvacuationReportFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EvacuationStatus::class,
            'triggered_at' => 'datetime',
            'closed_at' => 'datetime',
            'force_closed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<EvacuationReportEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(EvacuationReportEntry::class);
    }

    public function isOpen(): bool
    {
        return $this->status === EvacuationStatus::Open;
    }
}
