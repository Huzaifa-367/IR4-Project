<?php

namespace App\Services;

use App\Models\Camera;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps MediaMTX path configs in sync with Camera.stream_url so operators only
 * edit RTSP in the dashboard and the live wall uses /{reference} playback.
 */
final class CameraStreamGatewayService
{
    public function isConfigured(): bool
    {
        return (string) config('camera_stream.mediamtx.api_url') !== '';
    }

    public function sync(Camera $camera): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $name = $this->pathName($camera->reference);
        $payload = [
            'source' => $camera->stream_url,
            'sourceOnDemand' => (bool) config('camera_stream.mediamtx.source_on_demand', true),
        ];

        try {
            $client = $this->client();
            $replace = $client->post($this->url('/v3/config/paths/replace/'.rawurlencode($name)), $payload);
            if ($replace->successful()) {
                return;
            }

            // Path may not exist yet — add it.
            $add = $client->post($this->url('/v3/config/paths/add/'.rawurlencode($name)), $payload);
            if ($add->successful()) {
                return;
            }

            Log::warning('MediaMTX camera stream sync failed', [
                'reference' => $camera->reference,
                'replace_status' => $replace->status(),
                'add_status' => $add->status(),
                'body' => $add->body(),
            ]);
        } catch (Throwable $e) {
            Log::warning('MediaMTX camera stream sync error: '.$e->getMessage(), [
                'reference' => $camera->reference,
            ]);
        }
    }

    public function remove(string $reference): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $name = $this->pathName($reference);

        try {
            $this->client()->delete($this->url('/v3/config/paths/delete/'.rawurlencode($name)));
        } catch (Throwable $e) {
            Log::warning('MediaMTX camera stream delete error: '.$e->getMessage(), [
                'reference' => $reference,
            ]);
        }
    }

    /**
     * Push every registered camera RTSP URL into MediaMTX.
     *
     * @return array{synced: int, skipped: bool}
     */
    public function syncAll(): array
    {
        if (! $this->isConfigured()) {
            return ['synced' => 0, 'skipped' => true];
        }

        $count = 0;
        Camera::query()->orderBy('id')->each(function (Camera $camera) use (&$count): void {
            $this->sync($camera);
            $count++;
        });

        return ['synced' => $count, 'skipped' => false];
    }

    private function pathName(string $reference): string
    {
        $name = trim($reference);
        if ($name === '') {
            return 'unnamed';
        }

        // MediaMTX path segment: keep readable refs, strip path separators.
        return str_replace(['/', '\\'], '-', $name);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('camera_stream.mediamtx.api_url'), '/').$path;
    }

    private function client(): PendingRequest
    {
        $request = Http::timeout((int) config('camera_stream.mediamtx.timeout', 5))
            ->acceptJson()
            ->asJson();

        $user = config('camera_stream.mediamtx.api_user');
        $pass = config('camera_stream.mediamtx.api_pass');
        if (is_string($user) && $user !== '') {
            $request = $request->withBasicAuth($user, (string) $pass);
        }

        return $request;
    }
}
