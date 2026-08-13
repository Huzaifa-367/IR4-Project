<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Web\BaseController;
use App\Models\Camera;
use App\Models\PpeViolation;
use App\Services\PpeViolationService;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use App\Support\HardwarePresence;
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
        $cameraStaleMinutes = (int) app(SettingsService::class)->get('health.camera_stale_minutes', 3);

        $cameras = Camera::query()
            ->with('asset')
            ->orderBy('name')
            ->get()
            ->map(fn (Camera $camera): array => [
                'id' => $camera->id,
                'uuid' => $camera->uuid,
                'name' => $camera->name,
                'reference' => $camera->reference,
                'playback_url' => $this->playbackUrl($playbackUrlTemplate, $camera->reference),
                'ai_enabled' => $camera->ai_enabled,
                'status' => $camera->status->value,
                'is_online' => HardwarePresence::isCameraOnline($camera, $cameraStaleMinutes),
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

    private function playbackUrl(mixed $template, string $reference): ?string
    {
        if (! is_string($template) || $template === '') {
            return null;
        }

        $url = str_replace('{reference}', rawurlencode($reference), $template);
        // MediaMTX HLS reader expects a trailing slash on path roots.
        if (! str_contains(parse_url($url, PHP_URL_PATH) ?: $url, '.') && ! str_ends_with($url, '/')) {
            $url .= '/';
        }

        return $url;
    }
}
