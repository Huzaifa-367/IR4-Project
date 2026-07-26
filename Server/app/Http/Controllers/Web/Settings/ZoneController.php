<?php

namespace App\Http\Controllers\Web\Settings;

use App\Enums\ZoneType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\StoreZoneRequest;
use App\Http\Requests\Settings\UpdateZoneAccessListRequest;
use App\Http\Requests\Settings\UpdateZoneRequest;
use App\Http\Resources\WorkerResource;
use App\Models\Worker;
use App\Models\Zone;
use App\Services\ZoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ZoneController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Zone::class);

        $query = Zone::query()->withCount(['currentBindings', 'accessList']);

        if ($request->filled('zone_type')) {
            $query->where('zone_type', $request->string('zone_type')->toString());
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->where('is_active', true);
        }

        $this->applyListQuery($query, $request, ['name', 'zone_type', 'created_at'], ['name'], 'name', 'asc');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('settings/zones/index', [
            'zones' => [
                'data' => $paginator->getCollection()->map(fn (Zone $zone): array => [
                    'id' => $zone->id,
                    'uuid' => $zone->uuid,
                    'name' => $zone->name,
                    'zone_type' => $zone->zone_type->value,
                    'zone_type_label' => $zone->zone_type->label(),
                    'requires_authorization' => $zone->requires_authorization,
                    'requires_permit' => $zone->requires_permit,
                    'occupancy_limit' => $zone->occupancy_limit,
                    'is_active' => $zone->is_active,
                    'latitude' => $zone->latitude,
                    'longitude' => $zone->longitude,
                    'radius_meters' => $zone->radius_meters,
                    'color' => $zone->color,
                    'current_readers' => $zone->current_bindings_count,
                    'access_list_count' => $zone->access_list_count,
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'zoneTypes' => collect(ZoneType::cases())->map(fn (ZoneType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(StoreZoneRequest $request, ZoneService $zones): RedirectResponse
    {
        $zones->create($request->validated());

        return redirect()->route('settings.zones.index');
    }

    public function show(Request $request, Zone $zone): Response
    {
        $this->authorize('view', $zone);

        $zone->load([
            'currentBindings.reader:id,uuid,name,reference',
            'accessList.worker',
        ]);

        return Inertia::render('settings/zones/show', [
            'zone' => [
                'id' => $zone->id,
                'uuid' => $zone->uuid,
                'name' => $zone->name,
                'zone_type' => $zone->zone_type->value,
                'zone_type_label' => $zone->zone_type->label(),
                'requires_authorization' => $zone->requires_authorization,
                'requires_permit' => $zone->requires_permit,
                'occupancy_limit' => $zone->occupancy_limit,
                'is_active' => $zone->is_active,
                'latitude' => $zone->latitude,
                'longitude' => $zone->longitude,
                'radius_meters' => $zone->radius_meters,
                'color' => $zone->color,
                'current_readers' => $zone->currentBindings->map(fn ($b) => [
                    'binding_id' => $b->id,
                    'device_id' => $b->device_id,
                    'device_uuid' => $b->reader?->uuid,
                    'name' => $b->reader?->name,
                    'reference' => $b->reader?->reference,
                    'bound_from' => $b->bound_from?->toIso8601String(),
                ]),
                'access_list' => WorkerResource::collection(
                    $zone->accessList->pluck('worker')->filter()->values()
                )->resolve($request),
            ],
            'zoneTypes' => collect(ZoneType::cases())->map(fn (ZoneType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'workers' => Worker::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name'])
                ->map(fn (Worker $worker): array => [
                    'id' => $worker->id,
                    'uuid' => $worker->uuid,
                    'name' => $worker->name,
                ]),
        ]);
    }

    public function update(UpdateZoneRequest $request, Zone $zone, ZoneService $zones): RedirectResponse
    {
        $zones->update($zone, $request->validated());

        return redirect()->back(fallback: route('settings.zones.index'));
    }

    public function deactivate(Zone $zone, ZoneService $zones): RedirectResponse
    {
        $this->authorize('update', $zone);
        $zones->deactivate($zone);

        return redirect()->route('settings.zones.index');
    }

    public function destroy(Zone $zone, ZoneService $zones): RedirectResponse
    {
        $this->authorize('delete', $zone);
        $zones->destroy($zone);

        return redirect()->route('settings.zones.index');
    }

    public function updateAccessList(
        UpdateZoneAccessListRequest $request,
        Zone $zone,
        ZoneService $zones,
    ): RedirectResponse {
        /** @var list<int> $workerIds */
        $workerIds = $request->validated('worker_ids') ?? [];
        $zones->syncAccessList($zone, $workerIds, $request->user()?->id);

        return redirect()->route('settings.zones.show', $zone);
    }

    public function setMapPosition(Request $request, Zone $zone, ZoneService $zones): RedirectResponse
    {
        $this->authorize('update', $zone);

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:32'],
        ]);

        $zones->setMapPosition($zone, $data);

        return redirect()->route('settings.zones.show', $zone);
    }
}
