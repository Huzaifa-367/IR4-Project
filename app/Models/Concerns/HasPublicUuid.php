<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Public UUID for operator URL route keys. Integer `id` remains the PK/FK.
 *
 * @mixin Model
 *
 * @property string $uuid
 */
trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('uuid')) {
                $model->setAttribute('uuid', $model->getOriginal('uuid'));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
