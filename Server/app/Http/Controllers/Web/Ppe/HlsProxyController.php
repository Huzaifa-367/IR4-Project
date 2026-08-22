<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Http\Controllers\Web\BaseController;
use App\Services\CameraStreamGatewayService;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
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
 * MediaMTX v1.20+ gates LL-HLS child playlists and segments behind a
 * cookieCheck handshake on index.m3u8. Reuse that cookie per camera in the
 * operator session so parallel hls.js fetches stay authorized.
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

        $cameraReference = $this->cameraReferenceFromSuffix($suffix);
        $target = $this->buildUpstreamTarget($upstream, $suffix, $request->getQueryString());

        try {
            $upstreamResponse = $this->fetchUpstream($request, $upstream, $target, $suffix, $cameraReference);
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

    private function fetchUpstream(
        Request $request,
        string $upstream,
        string $target,
        string $suffix,
        ?string $cameraReference,
    ): HttpClientResponse {
        $sessionKey = $cameraReference === null ? null : 'hls_mtx_cookie.'.$cameraReference;
        $jar = $this->loadCookieJar($request, $upstream, $sessionKey);

        if (
            $cameraReference !== null
            && $sessionKey !== null
            && count($jar) === 0
            && ! $this->isMasterPlaylist($suffix)
        ) {
            $this->primeMediaMtxCookie($jar, $upstream, $cameraReference);
            $this->storeCookieJar($request, $sessionKey, $jar);
        }

        $response = $this->sendUpstream($request, $target, $suffix, $jar);

        if ($response->status() === 401 && $cameraReference !== null && $sessionKey !== null) {
            $request->session()->forget($sessionKey);
            $jar = new CookieJar;
            $this->primeMediaMtxCookie($jar, $upstream, $cameraReference);
            $response = $this->sendUpstream($request, $target, $suffix, $jar);
        }

        if ($sessionKey !== null && $response->successful()) {
            $this->storeCookieJar($request, $sessionKey, $jar);
        }

        return $response;
    }

    private function sendUpstream(
        Request $request,
        string $target,
        string $suffix,
        CookieJar $jar,
    ): HttpClientResponse {
        $isPlaylist = $this->isPlaylistPath($suffix);

        return Http::withOptions([
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'track_redirects' => true,
            ],
            'cookies' => $jar,
            'stream' => ! $isPlaylist,
        ])
            ->timeout(60)
            ->connectTimeout(5)
            ->withHeaders($this->forwardHeaders($request))
            ->send($request->method(), $target, [
                'body' => $request->getContent(),
            ]);
    }

    private function primeMediaMtxCookie(CookieJar $jar, string $upstream, string $cameraReference): void
    {
        Http::withOptions([
            'allow_redirects' => true,
            'cookies' => $jar,
        ])
            ->timeout(10)
            ->connectTimeout(5)
            ->get(rtrim($upstream, '/').'/'.$cameraReference.'/index.m3u8');
    }

    private function loadCookieJar(Request $request, string $upstream, ?string $sessionKey): CookieJar
    {
        if ($sessionKey === null) {
            return new CookieJar;
        }

        $host = parse_url($upstream, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return new CookieJar;
        }

        /** @var list<array<string, mixed>> $stored */
        $stored = $request->session()->get($sessionKey, []);
        $jar = new CookieJar;

        foreach ($stored as $cookieData) {
            $jar->setCookie(new SetCookie($cookieData));
        }

        return $jar;
    }

    private function storeCookieJar(Request $request, string $sessionKey, CookieJar $jar): void
    {
        $stored = [];

        foreach ($jar as $cookie) {
            $stored[] = $cookie->toArray();
        }

        $request->session()->put($sessionKey, $stored);
    }

    private function cameraReferenceFromSuffix(string $suffix): ?string
    {
        if ($suffix === '') {
            return null;
        }

        $reference = explode('/', $suffix, 2)[0];

        return $reference !== '' ? $reference : null;
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

    private function isMasterPlaylist(string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }

        return str_ends_with(strtolower($suffix), 'index.m3u8');
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
