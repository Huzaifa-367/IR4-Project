<?php

namespace App\Http\Resources;

use App\Models\Worker;
use App\Services\SignedStorageUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Worker
 */
final class WorkerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;
        /** @var Worker $worker */
        $worker = $this->resource;

        $photoUrl = null;
        if ($canSeeIdentity && $worker->photo_path !== null) {
            $photoUrl = app(SignedStorageUrlService::class)->temporaryUrl($worker->photo_path);
        }

        return [
            'id' => $worker->id,
            'uuid' => $worker->uuid,
            'contractor' => $worker->contractor,
            'role_title' => $worker->role_title,
            'worker_type' => $worker->worker_type->value,
            'worker_type_label' => $worker->worker_type->label(),
            'is_active' => $worker->is_active,
            'present' => $worker->present,
            'last_seen_at' => $worker->last_seen_at?->toIso8601String(),
            'notes' => $worker->notes,
            'created_at' => $worker->created_at?->toIso8601String(),
            'name' => $canSeeIdentity ? $worker->name : $worker->anonymizedLabel(),
            'badge_number' => $canSeeIdentity ? $worker->badge_number : null,
            'photo_url' => $photoUrl,
            'phone' => $canSeeIdentity ? $worker->phone : null,
            'employee_code' => $canSeeIdentity ? $worker->employee_code : null,
            'can_see_identity' => $canSeeIdentity,
        ];
    }
}
