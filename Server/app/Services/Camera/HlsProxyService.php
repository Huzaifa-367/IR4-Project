<?php

namespace App\Services\Camera;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Same-origin reverse proxy for MediaMTX HLS (/hls/{reference}/…).
 *
 * MediaMTX v1.20+ gates LL-HLS sub-playlists behind a cookieCheck on index.m3u8.
 * Cookies are cached per camera in the operator session.
 */
final class HlsProxyService
{
    public function __construct(
        private readonly CameraStreamGatewayService $streams,
    ) {}

    /**
     * @throws HlsProxyException
     */
    public function fetch(Request $request, ?string $path): HttpClientResponse
    {
        $upstream = $this->streams->hlsUpstreamBaseUrl();
        if ($upstream === '') {
            throw HlsProxyException::notConfigured();
        }

        $suffix = $this->normalizeSuffix($path);
        $cameraReference = $this->cameraReferenceFromSuffix($suffix);
        $target = $this->buildUpstreamTarget($upstream, $suffix, $request->getQueryString());

        try {
            return $this->fetchUpstream($request, $upstream, $target, $suffix, $cameraReference);
        } catch (Throwable $e) {
            throw HlsProxyException::upstreamFailed($e->getMessage());
        }
    }

    public function normalizeSuffix(?string $path): string
    {
        $suffix = trim((string) $path, '/');

        if ($suffix !== '' && preg_match('#^[A-Za-z0-9._/-]+$#', $suffix) !== 1) {
            throw HlsProxyException::invalidPath();
        }

        return $suffix;
    }

    public function isPlaylist(string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }

        return str_ends_with(strtolower($suffix), '.m3u8');
    }

    /**
     * @return array<string, string>
     */
    public function responseHeaders(HttpClientResponse $upstreamResponse, string $suffix): array
    {
        $headers = [];

        foreach (['Content-Type', 'Cache-Control', 'Accept-Ranges'] as $header) {
            $value = $upstreamResponse->header($header);
            if (is_string($value) && $value !== '') {
                $headers[$header] = $value;
            }
        }

        if ($this->isPlaylist($suffix)) {
            $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function streamBody(HttpClientResponse $upstreamResponse, array $headers): StreamedResponse
    {
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

    private function fetchUpstream(
        Request $request,
        string $upstream,
        string $target,
        string $suffix,
        ?string $cameraReference,
    ): HttpClientResponse {
        $sessionKey = $cameraReference === null ? null : 'hls_mtx_cookie.'.$cameraReference;
        $jar = $this->loadCookieJar($request, $sessionKey);

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

    private function sendUpstream(
        Request $request,
        string $target,
        string $suffix,
        CookieJar $jar,
    ): HttpClientResponse {
        return Http::withOptions([
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'track_redirects' => true,
            ],
            'cookies' => $jar,
            'stream' => ! $this->isPlaylist($suffix),
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

    private function loadCookieJar(Request $request, ?string $sessionKey): CookieJar
    {
        if ($sessionKey === null) {
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

    private function isMasterPlaylist(string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }

        return str_ends_with(strtolower($suffix), 'index.m3u8');
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
