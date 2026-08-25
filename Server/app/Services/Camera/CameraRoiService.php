<?php

namespace App\Services\Camera;

use App\Enums\CameraRoiSetStatus;
use App\Enums\CameraRoiStaleReason;
use App\Models\Camera;
use App\Models\CameraRoi;
use App\Models\CameraRoiSet;
use App\Models\Device;
use App\Models\User;
use App\Services\Hardware\AssetHealthService;
use App\Support\CameraPlaybackUrl;
use App\Support\HardwarePresence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CameraRoiService
{
    public function __construct(
        private readonly CameraRoiEdgeSyncService $edgeSync,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function indexRows(): array
    {
        $staleMinutes = app(AssetHealthService::class)->staleMinutesForCamera();

        return Camera::query()
            ->operational()
            ->with(['roiSet.rois', 'asset'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Camera $camera): bool => HardwarePresence::isCameraOnline($camera, $staleMinutes))
            ->map(fn (Camera $camera): array => $this->cameraSummary($camera))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function editorPayload(Camera $camera): array
    {
        $camera->loadMissing(['roiSet.rois', 'asset']);

        return [
            'camera' => [
                'id' => $camera->id,
                'uuid' => $camera->uuid,
                'name' => $camera->name,
                'reference' => $camera->reference,
                'playback_url' => CameraPlaybackUrl::forReference($camera->reference),
                'location_label' => $camera->asset?->current_location_label,
            ],
            'set' => $this->setToArray($camera->roiSet),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rois
     */
    public function saveDraft(Camera $camera, array $rois, User $user): CameraRoiSet
    {
        $normalized = $this->normalizeRois($rois);

        return DB::transaction(function () use ($camera, $normalized, $user): CameraRoiSet {
            $set = $this->ensureSet($camera, $user);
            $this->replaceRois($set, $camera, $normalized, $user);

            $status = $set->status === CameraRoiSetStatus::Active
                ? CameraRoiSetStatus::Draft
                : $set->status;

            $set->forceFill([
                'status' => $status,
                'updated_by' => $user->id,
            ])->save();

            return $set->fresh(['rois']) ?? $set;
        });
    }

    /**
     * @param  list<array<string, mixed>>|null  $rois
     */
    public function publish(Camera $camera, ?array $rois, User $user): CameraRoiSet
    {
        return DB::transaction(function () use ($camera, $rois, $user): CameraRoiSet {
            $set = $this->ensureSet($camera, $user);

            if ($rois !== null) {
                $this->replaceRois($set, $camera, $this->normalizeRois($rois), $user);
            }

            $set->load('rois');
            $enabled = $set->rois->filter(
                fn (CameraRoi $roi): bool => $roi->is_enabled && count($roi->polygon) >= 3,
            );

            if ($enabled->isEmpty()) {
                throw ValidationException::withMessages([
                    'rois' => 'Publish requires at least one enabled ROI with 3+ points.',
                ]);
            }

            $camera = $camera->fresh(['processedByDevice', 'roiSet.rois']) ?? $camera;
            $set->forceFill([
                'status' => CameraRoiSetStatus::Active,
                'view_fingerprint' => $this->fingerprintFor($camera),
                'published_at' => now(),
                'stale_at' => null,
                'stale_reason' => null,
                'updated_by' => $user->id,
            ])->save();

            $fresh = $set->fresh(['rois']) ?? $set;
            $camera->setRelation('roiSet', $fresh);
            if ($camera->processedByDevice !== null) {
                $this->edgeSync->publish($camera, $this->activePayloadForCamera($camera));
            }

            return $fresh;
        });
    }

    public function markStale(Camera $camera, CameraRoiStaleReason $reason, ?User $user = null): ?CameraRoiSet
    {
        /** @var CameraRoiSet|null $set */
        $set = CameraRoiSet::query()->where('camera_id', $camera->id)->first();
        if ($set === null) {
            return null;
        }

        // Never published — nothing for edge to invalidate.
        if ($set->status === CameraRoiSetStatus::Draft && $set->published_at === null) {
            return $set;
        }

        $set->forceFill([
            'status' => CameraRoiSetStatus::Stale,
            'stale_at' => now(),
            'stale_reason' => $reason,
            'updated_by' => $user?->id ?? $set->updated_by,
        ])->save();

        return $set->fresh(['rois']) ?? $set;
    }

    /**
     * Operator Live-wall overlay (draft / active / stale). Edge pull stays active-only.
     *
     * @return array{status: string, stale_reason: string|null, rois: list<array<string, mixed>>}|null
     */
    public function overlayForCamera(Camera $camera): ?array
    {
        $set = $camera->relationLoaded('roiSet')
            ? $camera->roiSet
            : CameraRoiSet::query()->with('rois')->where('camera_id', $camera->id)->first();

        if ($set === null) {
            return null;
        }

        $rois = $this->edgeFacingRois($set);
        if ($rois === []) {
            return null;
        }

        return [
            'status' => $set->status->value,
            'stale_reason' => $set->stale_reason?->value,
            'rois' => $rois,
        ];
    }

    /**
     * Active ROI geometry for the single camera bound to this device (1:1).
     * Caller: `GET /api/devices/{deviceUuid}/camera-rois` + matching `X-Device-Token` (token never echoed).
     *
     * @return array{
     *     device: array{id: int, uuid: string, reference: string, name: string},
     *     view_fingerprint: string|null,
     *     published_at: string|null,
     *     rois: list<array<string, mixed>>
     * }
     */
    public function activePayloadForEdgeDevice(Device $device): array
    {
        // One camera per AI device (DOC-23). If misconfigured with several, lowest id wins.
        $camera = Camera::query()
            ->where('processed_by_device_id', $device->id)
            ->with(['roiSet.rois'])
            ->orderBy('id')
            ->first();

        return $this->payloadForDeviceAndCamera($device, $camera);
    }

    /**
     * Same shape as GET camera-rois `data`, for the camera being published (SCC → Jetson push).
     *
     * @return array{
     *     device: array{id: int, uuid: string, reference: string, name: string},
     *     view_fingerprint: string|null,
     *     published_at: string|null,
     *     rois: list<array<string, mixed>>
     * }
     */
    public function activePayloadForCamera(Camera $camera): array
    {
        $camera->loadMissing(['processedByDevice', 'roiSet.rois']);
        $device = $camera->processedByDevice;
        if ($device === null) {
            throw new \InvalidArgumentException('Camera has no processed_by_device for ROI push.');
        }

        return $this->payloadForDeviceAndCamera($device, $camera);
    }

    /**
     * @return array{
     *     device: array{id: int, uuid: string, reference: string, name: string},
     *     view_fingerprint: string|null,
     *     published_at: string|null,
     *     rois: list<array<string, mixed>>
     * }
     */
    private function payloadForDeviceAndCamera(Device $device, ?Camera $camera): array
    {
        $set = $camera?->roiSet;
        $active = $set !== null && $set->status === CameraRoiSetStatus::Active;

        return [
            'device' => [
                'id' => $device->id,
                'uuid' => $device->uuid,
                'reference' => $device->reference,
                'name' => $device->name,
            ],
            'view_fingerprint' => $active ? $set->view_fingerprint : null,
            'published_at' => $active ? $set->published_at?->toIso8601String() : null,
            'rois' => $active ? $this->edgeApiRois($set) : [],
        ];
    }

    /**
     * Operator overlay shape (includes color / id).
     *
     * @return list<array<string, mixed>>
     */
    private function edgeFacingRois(CameraRoiSet $set): array
    {
        return $this->enabledGeometryRois($set)
            ->map(fn (CameraRoi $roi): array => $this->roiToArray($roi))
            ->all();
    }

    /**
     * Jetson push/pull shape (no operator-only fields).
     *
     * @return list<array<string, mixed>>
     */
    private function edgeApiRois(CameraRoiSet $set): array
    {
        return $this->enabledGeometryRois($set)
            ->map(fn (CameraRoi $roi): array => [
                'name' => $roi->name,
                'reference' => $roi->reference,
                'polygon' => $roi->polygon,
                'sort_order' => $roi->sort_order,
                'meta' => $roi->meta,
            ])
            ->all();
    }

    /**
     * @return Collection<int, CameraRoi>
     */
    private function enabledGeometryRois(CameraRoiSet $set): Collection
    {
        return $set->rois
            ->filter(fn (CameraRoi $roi): bool => $roi->is_enabled && count($roi->polygon) >= 3)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function cameraSummary(Camera $camera): array
    {
        $set = $camera->roiSet;

        return [
            'id' => $camera->id,
            'uuid' => $camera->uuid,
            'name' => $camera->name,
            'reference' => $camera->reference,
            'location_label' => $camera->asset?->current_location_label,
            'roi_status' => $set?->status->value,
            'roi_count' => $set?->rois->count() ?? 0,
            'stale_reason' => $set?->stale_reason?->value,
            'published_at' => $set?->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function setToArray(?CameraRoiSet $set): ?array
    {
        if ($set === null) {
            return null;
        }

        return [
            'id' => $set->id,
            'status' => $set->status->value,
            'view_fingerprint' => $set->view_fingerprint,
            'published_at' => $set->published_at?->toIso8601String(),
            'stale_at' => $set->stale_at?->toIso8601String(),
            'stale_reason' => $set->stale_reason?->value,
            'rois' => $set->rois
                ->map(fn (CameraRoi $roi): array => $this->roiToArray($roi))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roiToArray(CameraRoi $roi): array
    {
        return [
            'id' => $roi->id,
            'name' => $roi->name,
            'reference' => $roi->reference,
            'polygon' => $roi->polygon,
            'color' => $roi->color,
            'sort_order' => $roi->sort_order,
            'is_enabled' => $roi->is_enabled,
            'meta' => $roi->meta,
        ];
    }

    private function ensureSet(Camera $camera, User $user): CameraRoiSet
    {
        /** @var CameraRoiSet|null $existing */
        $existing = CameraRoiSet::query()->where('camera_id', $camera->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return CameraRoiSet::query()->create([
            'camera_id' => $camera->id,
            'status' => CameraRoiSetStatus::Draft,
            'view_fingerprint' => $this->fingerprintFor($camera),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function fingerprintFor(Camera $camera): string
    {
        return sha1(implode('|', [
            (string) $camera->stream_url,
            (string) config('camera_stream.browser_url_template', ''),
            $camera->reference,
            (string) $camera->ptz_generation,
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $rois
     * @return list<array<string, mixed>>
     */
    private function normalizeRois(array $rois): array
    {
        if ($rois === []) {
            throw ValidationException::withMessages([
                'rois' => 'At least one ROI is required when saving.',
            ]);
        }

        $seenRefs = [];
        $normalized = [];

        foreach (array_values($rois) as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $reference = trim((string) ($row['reference'] ?? ''));
            $color = strtolower(trim((string) ($row['color'] ?? '#22d3ee')));

            if ($name === '') {
                throw ValidationException::withMessages([
                    "rois.$index.name" => 'ROI name is required.',
                ]);
            }

            if ($reference === '') {
                $reference = $this->slugReference($name, $index);
            }

            if (! preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/', $reference)) {
                throw ValidationException::withMessages([
                    "rois.$index.reference" => 'Reference must be lowercase alphanumeric with _ or - (2–63 chars).',
                ]);
            }

            if (isset($seenRefs[$reference])) {
                throw ValidationException::withMessages([
                    "rois.$index.reference" => 'ROI references must be unique per camera.',
                ]);
            }
            $seenRefs[$reference] = true;

            if (! preg_match('/^#[0-9a-f]{6}$/', $color)) {
                throw ValidationException::withMessages([
                    "rois.$index.color" => 'Color must be a hex value like #22d3ee.',
                ]);
            }

            $normalized[] = [
                'name' => $name,
                'reference' => $reference,
                'polygon' => $this->normalizePolygon($row['polygon'] ?? null, $index),
                'color' => $color,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'meta' => is_array($row['meta'] ?? null) ? $row['meta'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{x: float, y: float}>
     */
    private function normalizePolygon(mixed $polygon, int $index): array
    {
        if (! is_array($polygon) || count($polygon) < 3) {
            throw ValidationException::withMessages([
                "rois.$index.polygon" => 'Each ROI needs at least 3 points.',
            ]);
        }

        $points = [];
        foreach ($polygon as $i => $point) {
            if (! is_array($point) || ! isset($point['x'], $point['y'])) {
                throw ValidationException::withMessages([
                    "rois.$index.polygon.$i" => 'Each point needs x and y.',
                ]);
            }

            $x = (float) $point['x'];
            $y = (float) $point['y'];
            if ($x < 0.0 || $x > 1.0 || $y < 0.0 || $y > 1.0) {
                throw ValidationException::withMessages([
                    "rois.$index.polygon.$i" => 'Coordinates must be normalized between 0 and 1.',
                ]);
            }

            $points[] = ['x' => round($x, 6), 'y' => round($y, 6)];
        }

        return $points;
    }

    /**
     * @param  list<array<string, mixed>>  $normalized
     */
    private function replaceRois(CameraRoiSet $set, Camera $camera, array $normalized, User $user): void
    {
        // Hard-replace: ROI rows are not versioned independently of the set.
        CameraRoi::query()->where('camera_roi_set_id', $set->id)->delete();

        foreach ($normalized as $row) {
            CameraRoi::query()->create([
                'camera_roi_set_id' => $set->id,
                'camera_id' => $camera->id,
                'name' => $row['name'],
                'reference' => $row['reference'],
                'polygon' => $row['polygon'],
                'color' => $row['color'],
                'sort_order' => $row['sort_order'],
                'is_enabled' => $row['is_enabled'],
                'meta' => $row['meta'],
                'created_by' => $user->id,
            ]);
        }
    }

    private function slugReference(string $name, int $index): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $name));
        $slug = trim($slug, '_');
        if (strlen($slug) < 2) {
            $slug = 'roi_'.($index + 1);
        }

        return substr($slug, 0, 63);
    }
}
