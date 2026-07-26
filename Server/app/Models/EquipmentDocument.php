<?php

namespace App\Models;

use App\Models\Concerns\HasCreatedBy;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\EquipmentDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EquipmentDocument extends Model
{
    /** @use HasFactory<EquipmentDocumentFactory> */
    use HasCreatedBy, HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
