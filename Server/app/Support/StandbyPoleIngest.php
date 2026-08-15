<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Same device-surface HTTP as EdgeCompute Ir4Client (DOC-08):
 * X-Device-Token → POST /api/ingest/* (202) and POST /api/devices/{uuid}/heartbeat (200).
 */
final class StandbyPoleIngest
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{status: int, json: array<string, mixed>}
     */
    public function postGasReadings(string $token, array $events): array
    {
        return $this->postIngest($token, '/api/ingest/gas-readings', $events);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{status: int, json: array<string, mixed>}
     */
    public function postTagReadings(string $token, array $events): array
    {
        return $this->postIngest($token, '/api/ingest/tag-readings', $events);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{status: int, json: array<string, mixed>}
     */
    public function postPpeViolations(string $token, array $events): array
    {
        return $this->postIngest($token, '/api/ingest/ppe-violations', $events);
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    public function postHeartbeat(string $deviceUuid, string $token): array
    {
        $path = '/api/devices/'.$deviceUuid.'/heartbeat';
        $response = $this->http($token)->post($this->baseUrl().$path, [
            'status' => 'online',
        ]);

        return $this->packHeartbeat($response, $path);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{status: int, json: array<string, mixed>}
     */
    private function postIngest(string $token, string $path, array $events): array
    {
        $response = $this->http($token)->post($this->baseUrl().$path, [
            'events' => $events,
        ]);

        return $this->packIngest($response, $path);
    }

    private function http(string $token): PendingRequest
    {
        return Http::withOptions(['verify' => false])
            ->withHeaders([
                'X-Device-Token' => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(20);
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    private function packIngest(Response $response, string $path): array
    {
        $packed = $this->decode($response, $path);

        if ($packed['status'] !== 202 || ! array_key_exists('accepted', $packed['json'])) {
            throw new RuntimeException(
                $this->wrongTargetMessage($path, $packed['status'], $response->body()),
            );
        }

        return $packed;
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    private function packHeartbeat(Response $response, string $path): array
    {
        $packed = $this->decode($response, $path);
        $data = $packed['json']['data'] ?? null;

        if ($packed['status'] !== 200 || ! is_array($data)) {
            throw new RuntimeException(
                $this->wrongTargetMessage($path, $packed['status'], $response->body()),
            );
        }

        return $packed;
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    private function decode(Response $response, string $path): array
    {
        if ($response->failed()) {
            throw new RuntimeException(
                'HTTP '.$response->status().' '.$this->baseUrl().$path.': '.mb_substr($response->body(), 0, 240),
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        /** @var array<string, mixed> $json */
        return ['status' => $response->status(), 'json' => $json];
    }

    private function wrongTargetMessage(string $path, int $status, string $body): string
    {
        $hint = 'Standby must use the same IR4 device APIs as EdgeCompute '
            .'(ingest 202 + accepted, heartbeat 200 + data). '
            .'Got HTTP '.$status.' from '.$this->baseUrl().$path.'. '
            .'Set IR4_BASE_URL (or APP_URL / --url=) to this Laravel app — '
            .'not a Flutter listener on :9100.';

        $snip = trim(mb_substr($body, 0, 120));
        if ($snip !== '') {
            $hint .= ' Body: '.$snip;
        }

        return $hint;
    }
}
