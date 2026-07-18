<?php

namespace Tests\Support;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait AssertsAudited
{
    protected function assertAudited(
        AuditEvent|string $event,
        ?Model $auditable = null,
        ?string $descriptionContains = null,
    ): AuditLog {
        $eventValue = $event instanceof AuditEvent ? $event->value : $event;

        $query = AuditLog::query()->where('event', $eventValue)->latest('id');

        if ($auditable !== null) {
            $query->where('auditable_type', $auditable->getMorphClass())
                ->where('auditable_id', $auditable->getKey());
        }

        /** @var AuditLog|null $log */
        $log = $query->first();

        expect($log)->not->toBeNull("Expected audit event [{$eventValue}] was not recorded.");

        if ($descriptionContains !== null) {
            expect((string) $log->description)->toContain($descriptionContains);
        }

        return $log;
    }
}
