<?php

namespace App\Models\Concerns;

use App\Observers\AuditableObserver;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(static fn ($model) => app(AuditableObserver::class)->created($model));
        static::updated(static fn ($model) => app(AuditableObserver::class)->updated($model));
        static::deleted(static fn ($model) => app(AuditableObserver::class)->deleted($model));
    }

    /**
     * @return list<string>
     */
    public function getAuditMaskedAttributes(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function getAuditExcludedAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at'];
    }
}
