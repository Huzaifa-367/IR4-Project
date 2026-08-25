<?php

namespace App\Services\Camera;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * On ROI publish: POST this camera's payload to the linked camera_ai device's API URL.
 */
final class CameraRoiEdgeSyncService
{
    private const TIMEOUT_SECONDS = 5;

    private const CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * @param  array<string, mixed>  $payload  Same shape as GET camera-rois `data`
     */
    public function publish(Device $camera, array $payload): bool
    {
        $camera->loadMissing('roiSet.rois');
        $url = $this->apiUrl($camera);

        if ($url === null) {
            Log::warning('ir4.camera_roi.edge_push_skipped', [
                'camera_id' => $camera->id,
                'reason' => 'no_api_url',
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
        if (! $device->isCamera()) {
            return null;
        }

        $url = trim((string) ($device->api_url ?? ''));
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'http://'.$url;
        }

        return $url;
    }
}
