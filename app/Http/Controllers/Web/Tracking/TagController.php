<?php

namespace App\Http\Controllers\Web\Tracking;

use App\Enums\TagStatus;
use App\Http\Controllers\Web\BaseController;
use App\Models\RfidTag;
use App\Models\Worker;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TagController extends BaseController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('view-tracking'), 403);

        $query = RfidTag::query()->with('worker');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $this->applyListQuery(
            $query,
            $request,
            sortable: ['tag_uid', 'status', 'assigned_at'],
            searchable: ['tag_uid'],
            defaultSort: 'tag_uid',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('hardware/tags/index', [
            'tags' => [
                'data' => $paginator->getCollection()->map(fn (RfidTag $tag) => [
                    'id' => $tag->id,
                    'tag_uid' => $tag->tag_uid,
                    'status' => $tag->status->value,
                    'status_label' => $tag->status->label(),
                    'worker_id' => $tag->worker_id,
                    'worker_name' => $tag->worker?->name,
                    'assigned_at' => $tag->assigned_at?->toIso8601String(),
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => [
                'status' => $request->string('status')->toString(),
                'search' => $request->string('search')->toString(),
            ],
            'statuses' => collect(TagStatus::cases())->map(fn (TagStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'workers' => Worker::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'spareCount' => RfidTag::query()->where('status', TagStatus::InStock)->count(),
            'canManage' => $request->user()?->can('manage-tags') ?? false,
        ]);
    }

    public function store(Request $request, TagService $tags): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-tags'), 403);

        $data = $request->validate([
            'tag_uid' => ['required', 'string', 'max:150', 'unique:rfid_tags,tag_uid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $tags->create($data['tag_uid'], $data['notes'] ?? null);

        return redirect()->route('tracking.tags.index');
    }

    public function assign(Request $request, RfidTag $tag, TagService $tags): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-tags'), 403);

        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
        ]);

        $tags->assign($tag, Worker::query()->findOrFail($data['worker_id']), $request->user());

        return redirect()->back();
    }

    public function unassign(Request $request, RfidTag $tag, TagService $tags): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-tags'), 403);
        $tags->unassign($tag, $request->user());

        return redirect()->back();
    }

    public function replace(Request $request, Worker $worker, TagService $tags): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-tags'), 403);

        $data = $request->validate([
            'new_tag_id' => ['required', 'integer', 'exists:rfid_tags,id'],
            'old_tag_status' => ['required', 'in:lost,damaged'],
        ]);

        $tags->replace(
            $worker,
            RfidTag::query()->findOrFail($data['new_tag_id']),
            TagStatus::from($data['old_tag_status']),
            $request->user(),
        );

        return redirect()->back();
    }
}
