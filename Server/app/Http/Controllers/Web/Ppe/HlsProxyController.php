<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Http\Controllers\Web\BaseController;
use App\Services\CameraStreamGatewayService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Same-origin reverse proxy for MediaMTX HLS so /live works on HTTPS
 * (browsers block http://host:8888 inside an https:// IR4 page).
 *
 * Streams the upstream body (does not buffer whole .ts/.m4s segments in PHP)
 * so the live wall stays smoother under load.
 */
final class HlsProxyController extends BaseController
{
    public function __invoke(Request $request, CameraStreamGatewayService $streams, ?string $path = null): Response|StreamedResponse
    {
        $upstream = $streams->hlsUpstreamBaseUrl();
        if ($upstream === '') {
            abort(503, 'MediaMTX HLS upstream is not configured.');
        }

        $suffix = trim((string) $path, '/');
        if ($suffix !== '' && preg_match('#^[A-Za-z0-9._/-]+$#', $suffix) !== 1) {
            abort(400, 'Invalid HLS path.');
        }

        $target = rtrim($upstream, '/').'/';
        if ($suffix !== '') {
            $target .= $suffix;
            // MediaMTX reader pages expect a trailing slash for path roots.
            if (! str_contains($suffix, '.') && ! str_ends_with($target, '/')) {
                $target .= '/';
            }
        }

        if ($request->getQueryString()) {
            $target .= '?'.$request->getQueryString();
        }

        try {
            $upstreamResponse = Http::withOptions([
                'allow_redirects' => false,
                'stream' => true,
            ])
                ->timeout(60)
                ->connectTimeout(5)
                ->withHeaders($this->forwardHeaders($request))
                ->send($request->method(), $target, [
                    'body' => $request->getContent(),
                ]);
        } catch (Throwable $e) {
            abort(502, 'MediaMTX HLS proxy failed: '.$e->getMessage());
        }

        $headers = [];
        foreach (['Content-Type', 'Cache-Control', 'Accept-Ranges'] as $header) {
            $value = $upstreamResponse->header($header);
            if (is_string($value) && $value !== '') {
                $headers[$header] = $value;
            }
        }

        if ($this->isPlaylistPath($suffix)) {
            $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        }

        $location = $upstreamResponse->header('Location');
        if (is_string($location) && $location !== '') {
            $headers['Location'] = $this->rewriteLocationForProxy($location);
        }

        $status = $upstreamResponse->status();
        $psrBody = $upstreamResponse->toPsrResponse()->getBody();

        return response()->stream(function () use ($psrBody): void {
            while (! $psrBody->eof()) {
                echo $psrBody->read(8192);
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            }
        }, $status, $headers);
    }

    /**
     * MediaMTX redirects use root paths (/CAM-…/). Prefix /hls so the browser
     * stays on the same-origin proxy.
     */
    private function rewriteLocationForProxy(string $location): string
    {
        if (preg_match('#^https?://[^/]+(/.*)$#i', $location, $match) === 1) {
            $location = $match[1];
        }

        if (str_starts_with($location, '/hls/') || str_starts_with($location, '/hls?')) {
            return $location;
        }

        if (str_starts_with($location, '/')) {
            return '/hls'.$location;
        }

        return $location;
    }

    private function isPlaylistPath(string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }

        return str_ends_with(strtolower($suffix), '.m3u8');
    }

    /**
     * @return array<string, string>
     */
    private function forwardHeaders(Request $request): array
    {
        $headers = [
            'Accept' => $request->header('Accept', '*/*') ?? '*/*',
        ];

        $range = $request->header('Range');
        if (is_string($range) && $range !== '') {
            $headers['Range'] = $range;
        }

        return $headers;
    }
}
