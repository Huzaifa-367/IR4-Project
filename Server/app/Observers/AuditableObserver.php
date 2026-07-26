<?php

namespace App\Observers;

use App\Enums\AuditEvent;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

final class AuditableObserver
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function created(Model $model): void
    {
        $values = $this->exceptExcluded($model, $model->getAttributes());
        $this->audit->record(
            AuditEvent::Created,
            $model,
            $this->describe('Created', $model),
            newValues: $values,
            maskedFields: $this->maskedFields($model),
        );
    }

    public function updated(Model $model): void
    {
        $changes = $this->exceptExcluded($model, $model->getChanges());

        if ($changes === []) {
            return;
        }

        $oldValues = [];
        foreach (array_keys($changes) as $attribute) {
            $oldValues[$attribute] = $model->getRawOriginal($attribute);
        }

        $this->audit->record(
            AuditEvent::ConfigChanged,
            $model,
            $this->describe('Changed', $model),
            $oldValues,
            $changes,
            maskedFields: $this->maskedFields($model),
        );
    }

    public function deleted(Model $model): void
    {
        $this->audit->record(
            AuditEvent::Deleted,
            $model,
            $this->describe('Deleted', $model),
            oldValues: $this->exceptExcluded($model, $model->getAttributes()),
            maskedFields: $this->maskedFields($model),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function exceptExcluded(Model $model, array $values): array
    {
        if (! method_exists($model, 'getAuditExcludedAttributes')) {
            return $values;
        }
        /** @var list<string> $excluded */
        $excluded = $model->getAuditExcludedAttributes();

        return array_diff_key($values, array_flip($excluded));
    }

    /**
     * @return list<string>
     */
    private function maskedFields(Model $model): array
    {
        if (! method_exists($model, 'getAuditMaskedAttributes')) {
            return [];
        }
        /** @var list<string> $fields */
        $fields = $model->getAuditMaskedAttributes();

        return $fields;
    }

    private function describe(string $verb, Model $model): string
    {
        return sprintf('%s %s #%s', $verb, class_basename($model), (string) $model->getKey());
    }
}
