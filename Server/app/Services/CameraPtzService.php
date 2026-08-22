<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\Camera;
use App\Models\User;
use App\Support\RtspStreamEndpoint;
use Illuminate\Http\Client\PendingRequest;
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
            $by,
        );
    }

    public function stop(Camera $camera, User $by): bool
    {
        return $this->sendContinuous($camera, 0, 0, 0, $by);
    }

    private function sendContinuous(Camera $camera, int $pan, int $tilt, int $zoom, User $by): bool
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

        try {
            $response = $this->client($endpoint)->withBody($body, 'application/xml')->put($url);

            if (! $response->successful()) {
                $this->lastError = sprintf(
                    'Camera PTZ HTTP %d: %s',
                    $response->status(),
                    $this->shortBody($response->body()),
                );

                Log::warning('Camera PTZ command failed', [
                    'camera_id' => $camera->id,
                    'camera_ref' => $camera->reference,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            app(AuditService::class)->record(
                event: AuditEvent::ConfigChanged,
                auditable: $camera,
                description: $pan === 0 && $tilt === 0 && $zoom === 0
                    ? 'PTZ stop'
                    : 'PTZ move',
                newValues: [
                    'target' => 'camera_ptz',
                    'camera_id' => $camera->id,
                    'camera_ref' => $camera->reference,
                    'pan' => $pan,
                    'tilt' => $tilt,
                    'zoom' => $zoom,
                ],
                user: $by,
            );

            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Camera PTZ command error: '.$e->getMessage(), [
                'camera_id' => $camera->id,
                'camera_ref' => $camera->reference,
            ]);

            return false;
        }
    }

    private function client(RtspStreamEndpoint $endpoint): PendingRequest
    {
        $request = Http::timeout(3)->connectTimeout(2);

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
