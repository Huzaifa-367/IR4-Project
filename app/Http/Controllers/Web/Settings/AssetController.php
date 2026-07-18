<?php

namespace App\Http\Controllers\Web\Settings;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Http\Controllers\Web\BaseController;
use App\Http\Requests\Settings\StoreAssetRequest;
use App\Http\Requests\Settings\UpdateAssetRequest;
use App\Models\Asset;
use App\Services\HardwareRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AssetController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::query()->withCount(['cameras', 'devices']);

        if ($request->filled('asset_type')) {
            $query->where('asset_type', $request->string('asset_type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $this->applyListQuery($query, $request, ['name', 'identifier', 'asset_type', 'status', 'created_at'], ['name', 'identifier'], 'name', 'asc');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('settings/assets/index', [
            'assets' => [
                'data' => $paginator->getCollection()->map(fn (Asset $asset): array => [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'identifier' => $asset->identifier,
                    'asset_type' => $asset->asset_type->value,
                    'asset_type_label' => $asset->asset_type->label(),
                    'status' => $asset->status->value,
                    'is_mobile' => $asset->is_mobile,
                    'current_location_label' => $asset->current_location_label,
                    'last_heartbeat_at' => $asset->last_heartbeat_at?->toIso8601String(),
                    'cameras_count' => $asset->cameras_count,
                    'devices_count' => $asset->devices_count,
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'assetTypes' => collect(AssetType::cases())->map(fn (AssetType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'statuses' => collect(AssetStatus::cases())->map(fn (AssetStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function store(StoreAssetRequest $request, HardwareRegistryService $hardware): RedirectResponse
    {
        $asset = $hardware->createAsset($request->validated());

        return redirect()->route('settings.assets.show', $asset);
    }

    public function show(Asset $asset): Response
    {
        $this->authorize('view', $asset);

        $asset->load(['cameras', 'devices']);

        return Inertia::render('settings/assets/show', [
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'identifier' => $asset->identifier,
                'asset_type' => $asset->asset_type->value,
                'asset_type_label' => $asset->asset_type->label(),
                'status' => $asset->status->value,
                'is_mobile' => $asset->is_mobile,
                'current_location_label' => $asset->current_location_label,
                'last_heartbeat_at' => $asset->last_heartbeat_at?->toIso8601String(),
                'cameras' => $asset->cameras->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'reference' => $c->reference,
                    'status' => $c->status->value,
                    'ai_enabled' => $c->ai_enabled,
                ]),
                'devices' => $asset->devices->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'reference' => $d->reference,
                    'device_type' => $d->device_type->value,
                    'device_type_label' => $d->device_type->label(),
                    'status' => $d->status->value,
                    'has_token' => $d->api_token_hash !== null,
                    'last_seen_at' => $d->last_seen_at?->toIso8601String(),
                ]),
            ],
            'assetTypes' => collect(AssetType::cases())->map(fn (AssetType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'statuses' => collect(AssetStatus::cases())->map(fn (AssetStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function update(UpdateAssetRequest $request, Asset $asset, HardwareRegistryService $hardware): RedirectResponse
    {
        $hardware->updateAsset($asset, $request->validated());

        return redirect()->route('settings.assets.show', $asset);
    }

    public function destroy(Asset $asset, HardwareRegistryService $hardware): RedirectResponse
    {
        $this->authorize('delete', $asset);
        $hardware->destroyAsset($asset);

        return redirect()->route('settings.assets.index');
    }
}
