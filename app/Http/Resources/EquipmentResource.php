<?php

namespace App\Http\Resources;

use App\Models\Equipment;
use App\Services\EquipmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Equipment
 */
final class EquipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Equipment $equipment */
        $equipment = $this->resource;
        $canSeeIdentity = $request->user()?->can('view-worker-identity') ?? false;

        return app(EquipmentService::class)->toArray($equipment, includeRelations: false, canSeeIdentity: $canSeeIdentity);
    }
}
