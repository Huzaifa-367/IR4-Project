<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Enums\ReturnStatus;
use App\Models\Concerns\HasCreatedBy;
use Database\Factories\EquipmentCheckoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class EquipmentCheckout extends Model
{
    use HasPublicUuid;

    /** @use HasFactory<EquipmentCheckoutFactory> */
    use HasCreatedBy, HasFactory, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'expected_return_at' => 'datetime',
            'returned_at' => 'datetime',
            'return_status' => ReturnStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
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
    public function checkedOutByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
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
    public function returnedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    public function isOpen(): bool
    {
        return $this->returned_at === null;
    }
}
