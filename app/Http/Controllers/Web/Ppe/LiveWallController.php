<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Enums\HardwareStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Web\BaseController;
use App\Models\Camera;
use App\Models\PpeViolation;
use App\Services\PpeViolationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class LiveWallController extends BaseController
{
    public function __invoke(Request $request): InertiaResponse
    {
        abort_unless($request->user()?->can('view-live-cameras'), 403);

        $display = $request->boolean('display');
        $playbackUrlTemplate = config('camera_stream.browser_url_template');

        $cameras = Camera::query()
            ->with('asset')
            ->orderBy('name')
            ->get()
            ->map(fn (Camera $camera): array => [
                'id' => $camera->id,
                'name' => $camera->name,
                'reference' => $camera->reference,
                'playback_url' => is_string($playbackUrlTemplate) && $playbackUrlTemplate !== ''
                    ? str_replace('{reference}', rawurlencode($camera->reference), $playbackUrlTemplate)
                    : null,
                'ai_enabled' => $camera->ai_enabled,
                'status' => $camera->status->value,
                'is_online' => $camera->status === HardwareStatus::Online
                    || ($camera->last_frame_at !== null && $camera->last_frame_at->greaterThan(now()->subMinutes(5))),
                'last_frame_at' => $camera->last_frame_at?->toIso8601String(),
                'location_label' => $camera->asset?->current_location_label,
            ]);

        return Inertia::render($display ? 'display/live' : 'live/index', [
            'cameras' => $cameras,
            'displayMode' => $display,
            'canViewPpe' => $request->user()?->can('view-ppe') ?? false,
        ]);
    }

    public function snapshot(Request $request, PpeViolationService $ppe): JsonResponse
    {
        abort_unless($request->user()?->can('view-live-cameras'), 403);

        $recent = PpeViolation::query()
            ->with('camera')
            ->where('review_status', ReviewStatus::Unreviewed)
            ->where('is_backfill', false)
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get()
            ->map(fn (PpeViolation $v) => $ppe->toArray($v))
            ->values();

        return ApiResponse::ok(['violations' => $recent]);
    }
}
