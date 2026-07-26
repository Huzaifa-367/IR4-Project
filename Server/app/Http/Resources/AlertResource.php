<?php

namespace App\Http\Resources;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
final class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Alert $alert */
        $alert = $this->resource;
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        return [
            'id' => $alert->id,
            'uuid' => $alert->uuid,
            'alert_type' => $alert->alert_type->value,
            'alert_type_label' => $alert->alert_type->label(),
            'severity' => $alert->severity->value,
            'title' => $alert->title,
            'payload' => $this->stripPayloadIdentity($alert->payload ?? [], $canSeeIdentity),
            'status' => $alert->status->value,
            'raised_at' => $alert->raised_at->toIso8601String(),
            'acknowledged_by' => $alert->acknowledged_by,
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'audible' => $alert->audible,
            'dedupe_key' => $alert->dedupe_key,
            'occurrences' => $alert->occurrences,
            'alertable_type' => $alert->alertable_type,
            'alertable_id' => $alert->alertable_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function stripPayloadIdentity(array $payload, bool $canSeeIdentity): array
    {
        if ($canSeeIdentity) {
            return $payload;
        }

        if (isset($payload['worker_name'])) {
            $id = $payload['worker_id'] ?? null;
            $payload['worker_name'] = $id !== null ? "Worker #{$id}" : 'Worker';
            $payload['worker_label'] = $payload['worker_name'];
        }

        if (isset($payload['worker_label']) && isset($payload['worker_id']) && ! isset($payload['worker_name'])) {
            $payload['worker_label'] = "Worker #{$payload['worker_id']}";
        }

        unset($payload['phone'], $payload['badge_number'], $payload['employee_code'], $payload['photo_url']);

        return $payload;
    }
}
