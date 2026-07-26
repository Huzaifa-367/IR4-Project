<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_id
 * @property int $worker_id
 * @property string $role_code
 * @property Carbon|null $documents_verified_at
 */
final class PermitPersonnel extends Model
{
    protected $table = 'permit_personnel';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'documents_verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Permit, $this>
     */
    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class);
    }

    /**
     * @return BelongsTo<Worker, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
