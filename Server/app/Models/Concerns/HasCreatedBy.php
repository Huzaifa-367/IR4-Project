<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opt-in for human-writable models that have a nullable created_by column.
 *
 * @property int|null $created_by
 */
trait HasCreatedBy
{
    public static function bootHasCreatedBy(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('created_by') !== null) {
                return;
            }

            $userId = auth()->id();

            if ($userId !== null) {
                $model->setAttribute('created_by', $userId);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
