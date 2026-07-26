<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

final class CreatedByObserver
{
    public function creating(Model $model): void
    {
        if ($model->getAttribute('created_by') !== null) {
            return;
        }

        $userId = auth()->id();

        if ($userId !== null) {
            $model->setAttribute('created_by', $userId);
        }
    }
}
