<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StandbyPoleIngest
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, json: array<string, mixed>}
     */
    public function postJson(string $token, string $path, array $body): array
    {
        $response = Http::withOptions(['verify' => false])
            ->withHeaders([
                'X-Device-Token' => $token,
                'Accept' => 'application/json',
            ])
            ->timeout(20)
            ->post(rtrim($this->baseUrl, '/').$path, $body);

        return $this->pack($response);
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    public function heartbeat(string $deviceUuid, string $token): array
    {
        $response = Http::withOptions(['verify' => false])
            ->withHeaders([
                'X-Device-Token' => $token,
                'Accept' => 'application/json',
            ])
            ->timeout(20)
            ->post(rtrim($this->baseUrl, '/').'/api/devices/'.$deviceUuid.'/heartbeat', [
                'status' => 'online',
            ]);

        return $this->pack($response);
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    private function pack(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            $json = ['raw' => $response->body()];
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 240),
            );
        }

        /** @var array<string, mixed> $json */
        return ['status' => $response->status(), 'json' => $json];
    }
}
