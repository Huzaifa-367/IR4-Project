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
 * Same-origin reverse proxy for MediaMTX HLS so /live iframes work on HTTPS
 * (browsers block http://host:8888 inside an https:// IR4 page).
 */
final class HlsProxyController extends BaseController
{
    public function __invoke(Request $request, CameraStreamGatewayService $streams, ?string $path = null): Response|StreamedResponse
    {
        abort_unless($request->user()?->can('view-live-cameras'), 403);

        $upstream = $streams->hlsUpstreamBaseUrl();
        if ($upstream === '') {
            abort(503, 'MediaMTX HLS upstream is not configured.');
        }

        $suffix = trim((string) $path, '/');
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
            $upstreamResponse = Http::timeout(30)
                ->connectTimeout(5)
                ->withHeaders($this->forwardHeaders($request))
                ->send($request->method(), $target, [
                    'body' => $request->getContent(),
                ]);
        } catch (Throwable $e) {
            abort(502, 'MediaMTX HLS proxy failed: '.$e->getMessage());
        }

        $headers = [];
        foreach (['Content-Type', 'Cache-Control', 'Accept-Ranges', 'Content-Length'] as $header) {
            $value = $upstreamResponse->header($header);
            if (is_string($value) && $value !== '') {
                $headers[$header] = $value;
            }
        }

        return response($upstreamResponse->body(), $upstreamResponse->status(), $headers);
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
