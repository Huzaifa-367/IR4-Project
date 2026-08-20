<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Enums\DeviceType;
use App\Http\Controllers\Web\BaseController;
use App\Models\Device;
use App\Models\TagReading;
use App\Models\Zone;
use App\Support\RfidSignal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class ReadingsController extends BaseController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('view-tracking'), 403);

        $canIdentity = $user->can('view-worker-identity');

        $query = TagReading::query()->with([
            'tag.worker',
            'zone:id,name',
            'reader:id,name,reference',
        ]);

        $zoneFilter = $request->string('zone_id')->toString();
        if ($zoneFilter === 'unbound') {
            $query->whereNull('zone_id');
        } elseif ($zoneFilter !== '' && ctype_digit($zoneFilter)) {
            $query->where('zone_id', (int) $zoneFilter);
        }

        if ($request->filled('reader_id')) {
            $query->where('reader_device_id', $request->integer('reader_id'));
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

        $proximity = $request->string('proximity')->toString();
        if ($proximity === 'near') {
            $query->where('rssi', '>=', RfidSignal::NEAR_RSSI_DBM);
        } elseif ($proximity === 'mid') {
            $query->where('rssi', '>=', RfidSignal::MID_RSSI_DBM)
                ->where('rssi', '<', RfidSignal::NEAR_RSSI_DBM);
        } elseif ($proximity === 'far') {
            $query->whereNotNull('rssi')
                ->where('rssi', '<', RfidSignal::MID_RSSI_DBM);
        }

        $search = $request->string('search')->trim()->toString();
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->whereHas('tag', function (Builder $tag) use ($like): void {
                    $tag->where('tag_uid', 'like', $like);
                })->orWhereHas('reader', function (Builder $reader) use ($like): void {
                    $reader->where('reference', 'like', $like)
                        ->orWhere('name', 'like', $like);
                });
            });
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['recorded_at', 'rssi', 'antenna'],
            searchable: [],
            defaultSort: 'recorded_at',
            defaultDirection: 'desc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('tracking/readings/index', [
            'readings' => [
                'data' => $paginator->getCollection()->map(function (TagReading $reading) use ($canIdentity): array {
                    $worker = $reading->tag?->worker;
                    $label = $canIdentity && $worker !== null
                        ? $worker->name
                        : ($worker?->anonymizedLabel() ?? null);

                    return [
                        'id' => $reading->id,
                        'recorded_at' => $reading->recorded_at->toIso8601String(),
                        'zone_id' => $reading->zone_id,
                        'zone_name' => $reading->zone?->name,
                        'reader_id' => $reading->reader_device_id,
                        'reader_ref' => $reading->reader?->reference,
                        'reader_name' => $reading->reader?->name,
                        'tag_uid' => $reading->tag?->tag_uid,
                        'worker_label' => $label,
                        'rssi' => $reading->rssi,
                        'antenna' => $reading->antenna,
                        'proximity' => $reading->proximity()?->value,
                        'is_backfill' => $reading->is_backfill,
                    ];
                })->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'zone_id' => $zoneFilter,
                'reader_id' => $request->string('reader_id')->toString(),
                'from' => $request->filled('from') ? $request->string('from')->toString() : '',
                'to' => $request->filled('to') ? $request->string('to')->toString() : '',
                'backfill' => $backfill,
                'proximity' => $proximity,
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
            'readers' => Device::query()
                ->operational()
                ->where('device_type', DeviceType::RfidReader)
                ->orderBy('name')
                ->get(['id', 'name', 'reference'])
                ->map(fn (Device $device): array => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'reference' => $device->reference,
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
