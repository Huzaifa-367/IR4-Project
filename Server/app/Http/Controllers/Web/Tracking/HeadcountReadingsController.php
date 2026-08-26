<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Http\Controllers\Web\BaseController;
use App\Models\Camera;
use App\Models\CameraHeadcountReading;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class HeadcountReadingsController extends BaseController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('view-tracking'), 403);

        $query = CameraHeadcountReading::query()->with([
            'camera:id,name,reference',
            'zone:id,name',
        ]);

        $zoneFilter = $request->string('zone_id')->toString();
        if ($zoneFilter === 'unbound') {
            $query->whereNull('zone_id');
        } elseif ($zoneFilter !== '' && ctype_digit($zoneFilter)) {
            $query->where('zone_id', (int) $zoneFilter);
        }

        if ($request->filled('camera_id')) {
            $query->where('camera_id', $request->integer('camera_id'));
        }

        $from = $this->parseBound($request->string('from')->toString(), false);
        if ($from !== null) {
            $query->where('recorded_at', '>=', $from);
        }

        $to = $this->parseBound($request->string('to')->toString(), true);
        if ($to !== null) {
            $query->where('recorded_at', '<=', $to);
        }

        $backfill = $request->string('backfill')->toString();
        if ($backfill === 'live') {
            $query->where('is_backfill', false);
        } elseif ($backfill === 'backfill') {
            $query->where('is_backfill', true);
        }

        $search = $request->string('search')->trim()->toString();
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->whereHas('camera', function (Builder $camera) use ($like): void {
                    $camera->where('reference', 'like', $like)
                        ->orWhere('name', 'like', $like);
                })->orWhereHas('zone', function (Builder $zone) use ($like): void {
                    $zone->where('name', 'like', $like);
                });
            });
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['recorded_at', 'count'],
            searchable: [],
            defaultSort: 'recorded_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('tracking/headcount-readings/index', [
            'readings' => [
                'data' => $paginator->getCollection()->map(fn (CameraHeadcountReading $reading): array => [
                    'id' => $reading->id,
                    'recorded_at' => $reading->recorded_at->toIso8601String(),
                    'zone_id' => $reading->zone_id,
                    'zone_name' => $reading->zone?->name,
                    'camera_id' => $reading->camera_id,
                    'camera_ref' => $reading->camera?->reference,
                    'camera_name' => $reading->camera?->name,
                    'count' => (int) $reading->count,
                    'is_backfill' => $reading->is_backfill,
                ])->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'zone_id' => $zoneFilter,
                'camera_id' => $request->string('camera_id')->toString(),
                'from' => $request->filled('from') ? $request->string('from')->toString() : '',
                'to' => $request->filled('to') ? $request->string('to')->toString() : '',
                'backfill' => $backfill,
                'search' => $search,
            ],
            'zones' => Zone::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Zone $zone): array => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                ])
                ->all(),
            'cameras' => Camera::query()
                ->orderBy('name')
                ->get(['id', 'name', 'reference'])
                ->map(fn (Camera $camera): array => [
                    'id' => $camera->id,
                    'name' => $camera->name,
                    'reference' => $camera->reference,
                ])
                ->all(),
        ]);
    }

    private function parseBound(string $value, bool $isEnd): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        if (strlen($value) <= 10) {
            return $isEnd ? $parsed->endOfDay() : $parsed->startOfDay();
        }

        return $parsed;
    }
}
