<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Enums\TagStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\RfidTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tag_uid
 * @property int|null $worker_id
 * @property TagStatus $status
 * @property Carbon|null $assigned_at
 * @property int|null $assigned_by
 * @property string|null $notes
 */
final class RfidTag extends Model
{
    use HasPublicUuid;

    /** @use HasFactory<RfidTagFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TagStatus::class,
            'assigned_at' => 'datetime',
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
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return HasOne<WorkerPosition, $this>
     */
    public function position(): HasOne
    {
        return $this->hasOne(WorkerPosition::class, 'tag_id');
    }

    /**
     * @return HasMany<TagReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(TagReading::class, 'tag_id');
    }

    public function isAssigned(): bool
    {
        return $this->status === TagStatus::Assigned;
    }
}
