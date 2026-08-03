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

    public function sync(Camera $camera): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $name = $this->pathName($camera->reference);
        $payload = [
            // Encode user/password so passwords containing @/: work (ffplay is lenient; MediaMTX is not).
            'source' => $this->encodeRtspSource($camera->stream_url),
            'sourceOnDemand' => (bool) config('camera_stream.mediamtx.source_on_demand', true),
        ];

        try {
            $client = $this->client();
            $replace = $client->post($this->url('/v3/config/paths/replace/'.rawurlencode($name)), $payload);
            if ($replace->successful()) {
                return true;
            }

            // Path may not exist yet — add it.
            $add = $client->post($this->url('/v3/config/paths/add/'.rawurlencode($name)), $payload);
            if ($add->successful()) {
                return true;
            }

            Log::warning('MediaMTX camera stream sync failed', [
                'reference' => $camera->reference,
                'api_url' => (string) config('camera_stream.mediamtx.api_url'),
                'replace_status' => $replace->status(),
                'add_status' => $add->status(),
                'body' => $add->body(),
            ]);

            return false;
        } catch (Throwable $e) {
            Log::warning('MediaMTX camera stream sync error: '.$e->getMessage(), [
                'reference' => $camera->reference,
                'api_url' => (string) config('camera_stream.mediamtx.api_url'),
            ]);

            return false;
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
     * @return array{synced: int, failed: int, skipped: bool, errors: list<string>}
     */
    public function syncAll(): array
    {
        if (! $this->isConfigured()) {
            return ['synced' => 0, 'failed' => 0, 'skipped' => true, 'errors' => []];
        }

        $synced = 0;
        $failed = 0;
        /** @var list<string> $errors */
        $errors = [];

        Camera::query()->orderBy('id')->each(function (Camera $camera) use (&$synced, &$failed, &$errors): void {
            if ($this->sync($camera)) {
                $synced++;

                return;
            }

            $failed++;
            $errors[] = $camera->reference;
        });

        return [
            'synced' => $synced,
            'failed' => $failed,
            'skipped' => false,
            'errors' => $errors,
        ];
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

    /**
     * URL-encode RTSP userinfo so passwords with @ / : / # still parse correctly.
     *
     * Example:
     *   rtsp://admin:UNity@320@@192.168.1.64:554/Streaming/Channels/101
     * → rtsp://admin:UNity%40320%40@192.168.1.64:554/Streaming/Channels/101
     */
    public function encodeRtspSource(string $url): string
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^(rtsps?://)(.+)$#i', $url, $match)) {
            return $url;
        }

        $scheme = $match[1];
        $rest = $match[2];
        $slash = strpos($rest, '/');
        $authority = $slash === false ? $rest : substr($rest, 0, $slash);
        $pathAndQuery = $slash === false ? '' : substr($rest, $slash);

        $at = strrpos($authority, '@');
        if ($at === false) {
            return $url;
        }

        $userInfo = substr($authority, 0, $at);
        $hostPort = substr($authority, $at + 1);
        if ($hostPort === '' || ! str_contains($userInfo, ':')) {
            // user-only or empty host — leave unchanged
            $user = rawurlencode(rawurldecode($userInfo));

            return $scheme.$user.'@'.$hostPort.$pathAndQuery;
        }

        $colon = strpos($userInfo, ':');
        $user = substr($userInfo, 0, (int) $colon);
        $pass = substr($userInfo, (int) $colon + 1);

        return $scheme
            .rawurlencode(rawurldecode($user))
            .':'
            .rawurlencode(rawurldecode($pass))
            .'@'
            .$hostPort
            .$pathAndQuery;
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
