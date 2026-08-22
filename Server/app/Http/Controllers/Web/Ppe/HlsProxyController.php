<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Http\Controllers\Web\BaseController;
use App\Services\CameraStreamGatewayService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Same-origin reverse proxy for MediaMTX HLS so /live works on HTTPS
 * (browsers block http://host:8888 inside an https:// IR4 page).
 *
 * MediaMTX v1.20+ uses a cookie-check redirect on first HLS hit; follow that
 * server-side so hls.js never sees an empty 302 without Set-Cookie.
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

        $target = $this->buildUpstreamTarget($upstream, $suffix, $request->getQueryString());

        try {
            $upstreamResponse = $this->fetchUpstream($request, $target, $suffix);
        } catch (Throwable $e) {
            abort(502, 'MediaMTX HLS proxy failed: '.$e->getMessage());
        }

        if (! $upstreamResponse->successful()) {
            abort($upstreamResponse->status() >= 400 ? $upstreamResponse->status() : 502, 'MediaMTX HLS upstream error.');
        }

        $headers = $this->responseHeaders($upstreamResponse, $suffix);

        if ($this->isPlaylistPath($suffix)) {
            return response($upstreamResponse->body(), $upstreamResponse->status(), $headers);
        }

        $psrBody = $upstreamResponse->toPsrResponse()->getBody();

        return response()->stream(function () use ($psrBody): void {
            while (! $psrBody->eof()) {
                echo $psrBody->read(8192);
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            }
        }, $upstreamResponse->status(), $headers);
    }

    private function buildUpstreamTarget(string $upstream, string $suffix, ?string $queryString): string
    {
        $target = rtrim($upstream, '/').'/';
        if ($suffix !== '') {
            $target .= $suffix;
            if (! str_contains($suffix, '.') && ! str_ends_with($target, '/')) {
                $target .= '/';
            }
        }

        if ($queryString) {
            $target .= '?'.$queryString;
        }

        return $target;
    }

    private function fetchUpstream(Request $request, string $target, string $suffix): HttpClientResponse
    {
        $isPlaylist = $this->isPlaylistPath($suffix);

        return Http::withOptions([
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'track_redirects' => true,
            ],
            'cookies' => new CookieJar,
            'stream' => ! $isPlaylist,
        ])
            ->timeout(60)
            ->connectTimeout(5)
            ->withHeaders($this->forwardHeaders($request))
            ->send($request->method(), $target, [
                'body' => $request->getContent(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function responseHeaders(HttpClientResponse $upstreamResponse, string $suffix): array
    {
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

        return $headers;
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
