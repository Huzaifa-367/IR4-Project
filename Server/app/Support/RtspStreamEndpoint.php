<?php

namespace App\Support;

use App\Models\Camera;

/**
 * Parsed RTSP credentials/host/channel for on-LAN camera APIs (ISAPI PTZ, etc.).
 */
final readonly class RtspStreamEndpoint
{
    public function __construct(
        public string $host,
        public int $rtspPort,
        public ?string $username,
        public ?string $password,
        public int $channelId,
        public int $httpPort = 80,
    ) {}

    public static function fromCamera(Camera $camera): ?self
    {
        $parsed = self::parseStreamUrl((string) $camera->stream_url);
        if ($parsed === null) {
            return null;
        }

        $meta = is_array($camera->meta) ? $camera->meta : [];
        $ptzMeta = is_array($meta['ptz'] ?? null) ? $meta['ptz'] : [];

        $channelId = isset($ptzMeta['channel']) ? (int) $ptzMeta['channel'] : $parsed['channelId'];
        $httpPort = isset($ptzMeta['http_port']) ? (int) $ptzMeta['http_port'] : 80;

        return new self(
            host: $parsed['host'],
            rtspPort: $parsed['port'],
            username: $parsed['username'],
            password: $parsed['password'],
            channelId: max(1, $channelId),
            httpPort: max(1, min(65535, $httpPort)),
        );
    }

    public function isapiBaseUrl(): string
    {
        return sprintf('http://%s:%d', $this->host, $this->httpPort);
    }

    /**
     * @return array{host: string, port: int, username: ?string, password: ?string, channelId: int}|null
     */
    private static function parseStreamUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || preg_match('#^rtsps?://(.+)$#i', $url, $match) !== 1) {
            return null;
        }

        $rest = $match[1];
        $slash = strpos($rest, '/');
        $authority = $slash === false ? $rest : substr($rest, 0, $slash);
        $path = $slash === false ? '' : substr($rest, $slash);

        $at = strrpos($authority, '@');
        $username = null;
        $password = null;
        $hostPort = $authority;

        if ($at !== false) {
            $userInfo = substr($authority, 0, $at);
            $hostPort = substr($authority, $at + 1);
            $colon = strpos($userInfo, ':');
            if ($colon !== false) {
                $username = rawurldecode(substr($userInfo, 0, $colon));
                $password = rawurldecode(substr($userInfo, $colon + 1));
            } else {
                $username = rawurldecode($userInfo);
            }
        }

        $port = 554;
        $host = $hostPort;
        if (preg_match('#^(\[[^\]]+\]|[^:]+):(\d+)$#', $hostPort, $hostMatch) === 1) {
            $host = $hostMatch[1];
            $port = (int) $hostMatch[2];
        }

        if ($host === '') {
            return null;
        }

        $channelId = 1;
        if (preg_match('#/Streaming/Channels/(\d+)#i', $path, $channelMatch) === 1) {
            $channelId = max(1, (int) floor(((int) $channelMatch[1]) / 100));
        }

        return [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'channelId' => $channelId,
        ];
    }
}
