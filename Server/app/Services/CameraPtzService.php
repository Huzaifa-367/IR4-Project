<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\Camera;
use App\Models\User;
use App\Support\RtspStreamEndpoint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CameraPtzService
{
    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function move(Camera $camera, int $pan, int $tilt, int $zoom, User $by): bool
    {
        return $this->sendContinuous(
            $camera,
            $this->clampAxis($pan),
            $this->clampAxis($tilt),
            $this->clampAxis($zoom),
        );
    }

    public function stop(Camera $camera, User $by): bool
    {
        $this->lastError = '';

        $endpoint = RtspStreamEndpoint::fromCamera($camera);
        if ($endpoint === null) {
            $this->lastError = 'Camera stream URL is not configured.';

            return false;
        }

        $stopUrl = sprintf(
            '%s/ISAPI/PTZCtrl/channels/%d/stop',
            $endpoint->isapiBaseUrl(),
            $endpoint->channelId,
        );

        $stopBody = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<PTZData version=\"2.0\" xmlns=\"http://www.isapi.org/ver20/XMLSchema\">\n  <pan>0</pan>\n  <tilt>0</tilt>\n  <zoom>0</zoom>\n</PTZData>\n";

        if ($this->putIsapi($endpoint, $stopUrl, $stopBody)) {
            $this->auditCommand($camera, $by, 'stop', 0, 0, 0);

            return true;
        }

        // Some firmware only honours continuous zeros — try that before failing.
        if ($this->sendContinuous($camera, 0, 0, 0)) {
            $this->auditCommand($camera, $by, 'stop', 0, 0, 0);

            return true;
        }

        return false;
    }

    private function sendContinuous(Camera $camera, int $pan, int $tilt, int $zoom): bool
    {
        $this->lastError = '';

        $endpoint = RtspStreamEndpoint::fromCamera($camera);
        if ($endpoint === null) {
            $this->lastError = 'Camera stream URL is not configured.';

            return false;
        }

        $url = sprintf(
            '%s/ISAPI/PTZCtrl/channels/%d/continuous',
            $endpoint->isapiBaseUrl(),
            $endpoint->channelId,
        );

        $body = sprintf(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<PTZData version=\"2.0\" xmlns=\"http://www.isapi.org/ver20/XMLSchema\">\n  <pan>%d</pan>\n  <tilt>%d</tilt>\n  <zoom>%d</zoom>\n</PTZData>\n",
            $pan,
            $tilt,
            $zoom,
        );

        if (! $this->putIsapi($endpoint, $url, $body)) {
            return false;
        }

        return true;
    }

    private function putIsapi(RtspStreamEndpoint $endpoint, string $url, string $body): bool
    {
        $attempts = max(0, (int) config('camera_stream.ptz.retries', 1)) + 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->client($endpoint)->withBody($body, 'application/xml')->put($url);

                if ($this->isSuccessResponse($response)) {
                    return true;
                }

                $this->lastError = sprintf(
                    'Camera PTZ HTTP %d: %s',
                    $response->status(),
                    $this->shortBody($response->body()),
                );

                if ($attempt < $attempts && $this->shouldRetry($response)) {
                    continue;
                }

                Log::warning('Camera PTZ command failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            } catch (ConnectionException $e) {
                $this->lastError = 'Cannot reach camera: '.$e->getMessage();

                if ($attempt < $attempts) {
                    continue;
                }

                Log::warning('Camera PTZ connection error', [
                    'url' => $url,
                    'message' => $e->getMessage(),
                ]);

                return false;
            } catch (Throwable $e) {
                $this->lastError = $e->getMessage();
                Log::warning('Camera PTZ command error: '.$e->getMessage(), [
                    'url' => $url,
                ]);

                return false;
            }
        }

        return false;
    }

    private function isSuccessResponse(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $body = $response->body();

        if (preg_match('/<statusCode>\s*(\d+)\s*<\/statusCode>/', $body, $match) === 1) {
            return (int) $match[1] === 1;
        }

        // Some firmware returns an empty 200 on success.
        return trim($body) === '' || str_contains($body, '<statusString>OK</statusString>');
    }

    private function shouldRetry(Response $response): bool
    {
        return in_array($response->status(), [401, 408, 429, 500, 502, 503, 504], true);
    }

    private function auditCommand(
        Camera $camera,
        ?User $by,
        string $command,
        int $pan,
        int $tilt,
        int $zoom,
    ): void {
        if ($by === null) {
            return;
        }

        app(AuditService::class)->record(
            event: AuditEvent::ConfigChanged,
            auditable: $camera,
            description: $command === 'stop' ? 'PTZ stop' : 'PTZ move',
            newValues: [
                'target' => 'camera_ptz',
                'command' => $command,
                'camera_id' => $camera->id,
                'camera_ref' => $camera->reference,
                'pan' => $pan,
                'tilt' => $tilt,
                'zoom' => $zoom,
            ],
            user: $by,
        );
    }

    private function client(RtspStreamEndpoint $endpoint): PendingRequest
    {
        $request = Http::timeout((int) config('camera_stream.ptz.timeout', 4))
            ->connectTimeout((int) config('camera_stream.ptz.connect_timeout', 2));

        if ($endpoint->username !== null && $endpoint->username !== '') {
            $request = $request->withDigestAuth(
                $endpoint->username,
                $endpoint->password ?? '',
            );
        }

        return $request;
    }

    private function clampAxis(int $value): int
    {
        return max(-100, min(100, $value));
    }

    private function shortBody(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? $body);

        return strlen($body) > 180 ? substr($body, 0, 177).'...' : $body;
    }
}
