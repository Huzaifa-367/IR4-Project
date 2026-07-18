<?php

namespace App\Models\Concerns;

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
     * @return BelongsTo<\App\Models\User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
