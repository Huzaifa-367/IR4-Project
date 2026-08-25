<?php

namespace App\Services\Camera;

use App\Enums\DeviceType;
use App\Models\Camera;
use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * On ROI publish: POST this camera's payload to the linked edge device's API URL.
 *
 * Source of truth: nullable `devices.config.api_url` (edge_compute only) —
 * full endpoint including path, e.g. `http://172.16.3.2:8600/rois`.
 */
final class CameraRoiEdgeSyncService
{
    private const TIMEOUT_SECONDS = 5;

    private const CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * @param  array<string, mixed>  $payload  Same shape as GET camera-rois `data`
     */
    public function publish(Camera $camera, array $payload): bool
    {
        $camera->loadMissing('processedByDevice');
        $device = $camera->processedByDevice;
        $url = $device !== null ? $this->apiUrl($device) : null;

        if ($device === null || $url === null) {
            Log::warning('ir4.camera_roi.edge_push_skipped', [
                'camera_id' => $camera->id,
                'reason' => $device === null ? 'no_processed_by_device' : 'no_api_url',
            ]);

            return false;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('ir4.camera_roi.edge_push_failed', [
                    'url' => $url,
                    'camera_id' => $camera->id,
                    'status' => $response->status(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('ir4.camera_roi.edge_push_error', [
                'url' => $url,
                'camera_id' => $camera->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function apiUrl(Device $device): ?string
    {
        if ($device->device_type !== DeviceType::EdgeCompute) {
            return null;
        }

        $url = trim((string) (is_array($device->config) ? ($device->config['api_url'] ?? '') : ''));
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'http://'.$url;
        }

        return $url;
    }
}
