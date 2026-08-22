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
    /** Continuous speed for a single click nudge (Hikvision -100..100). */
    private const NUDGE_CONTINUOUS_SPEED = 25;

    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function move(Camera $camera, int $pan, int $tilt, int $zoom, User $by): bool
    {
        $pan = $this->clampAxis($pan);
        $tilt = $this->clampAxis($tilt);
        $zoom = $this->clampAxis($zoom);

        if ($pan === 0 && $tilt === 0 && $zoom === 0) {
            return true;
        }

        [$speedPan, $speedTilt, $speedZoom] = $this->toContinuousSpeed($pan, $tilt, $zoom);

        if (! $this->sendContinuous($camera, $speedPan, $speedTilt, $speedZoom)) {
            return false;
        }

        usleep($this->nudgeDurationMicros($pan, $tilt, $zoom));
        $this->haltContinuous($camera);

        $this->auditCommand($camera, $by, 'move', $pan, $tilt, $zoom);

        return true;
    }

    public function stop(Camera $camera, User $by, bool $audit = true): bool
    {
        $this->lastError = '';

        $stopped = $this->haltContinuous($camera);

        if ($stopped && $audit) {
            $this->auditCommand($camera, $by, 'stop', 0, 0, 0);

            return true;
        }

        return $stopped;
    }

    private function haltContinuous(Camera $camera): bool
    {
        $endpoint = RtspStreamEndpoint::fromCamera($camera);
        if ($endpoint === null) {
            $this->lastError = 'Camera stream URL is not configured.';

            return false;
        }

        $stopped = $this->sendContinuous($camera, 0, 0, 0, lenient: true);

        $stopUrl = sprintf(
            '%s/ISAPI/PTZCtrl/channels/%d/stop',
            $endpoint->isapiBaseUrl(),
            $endpoint->channelId,
        );

        $stopBody = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<PTZData version=\"2.0\" xmlns=\"http://www.isapi.org/ver20/XMLSchema\">\n  <pan>0</pan>\n  <tilt>0</tilt>\n  <zoom>0</zoom>\n</PTZData>\n";

        if (! $stopped) {
            $stopped = $this->putIsapi($endpoint, $stopUrl, $stopBody, lenient: true);
        } else {
            $this->putIsapi($endpoint, $stopUrl, $stopBody, lenient: true);
        }

        return $stopped;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function toContinuousSpeed(int $pan, int $tilt, int $zoom): array
    {
        return [
            $pan === 0 ? 0 : (int) (self::NUDGE_CONTINUOUS_SPEED * ($pan > 0 ? 1 : -1)),
            $tilt === 0 ? 0 : (int) (self::NUDGE_CONTINUOUS_SPEED * ($tilt > 0 ? 1 : -1)),
            $zoom === 0 ? 0 : (int) (self::NUDGE_CONTINUOUS_SPEED * ($zoom > 0 ? 1 : -1)),
        ];
    }

    private function nudgeDurationMicros(int $pan, int $tilt, int $zoom): int
    {
        $step = max(abs($pan), abs($tilt), abs($zoom));

        // ponytail: ~50ms per degree-step unit; tune on real heads if nudges feel too long/short.
        return min(400_000, max(100_000, $step * 50_000));
    }

    private function sendContinuous(Camera $camera, int $pan, int $tilt, int $zoom, bool $lenient = false): bool
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

        if (! $this->putIsapi($endpoint, $url, $body, $lenient)) {
            return false;
        }

        return true;
    }

    private function putIsapi(
        RtspStreamEndpoint $endpoint,
        string $url,
        string $body,
        bool $lenient = false,
    ): bool {
        $attempts = max(0, (int) config('camera_stream.ptz.retries', 1)) + 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->client($endpoint)->withBody($body, 'application/xml')->put($url);

                if ($this->isSuccessResponse($response, $lenient)) {
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

    private function isSuccessResponse(Response $response, bool $lenient = false): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $body = $response->body();

        if (preg_match('/<statusCode>\s*(\d+)\s*<\/statusCode>/', $body, $match) === 1) {
            $code = (int) $match[1];

            if ($code === 1) {
                return true;
            }

            // Stop on an already-idle head is still success for operators.
            if ($lenient && in_array($code, [2, 4], true)) {
                return true;
            }

            return false;
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
