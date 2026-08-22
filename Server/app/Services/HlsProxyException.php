<?php

namespace App\Services;

use RuntimeException;

final class HlsProxyException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(503, 'MediaMTX HLS upstream is not configured.');
    }

    public static function invalidPath(): self
    {
        return new self(400, 'Invalid HLS path.');
    }

    public static function upstreamFailed(string $message): self
    {
        return new self(502, 'MediaMTX HLS proxy failed: '.$message);
    }

    public static function upstreamError(int $status): self
    {
        return new self(
            $status >= 400 ? $status : 502,
            'MediaMTX HLS upstream error.',
        );
    }
}
