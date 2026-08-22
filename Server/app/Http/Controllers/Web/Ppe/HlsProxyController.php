<?php

namespace App\Http\Controllers\Web\Ppe;

use App\Http\Controllers\Web\BaseController;
use App\Services\HlsProxyException;
use App\Services\HlsProxyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Same-origin /hls proxy entry point — logic lives in {@see HlsProxyService}.
 */
final class HlsProxyController extends BaseController
{
    public function __invoke(Request $request, HlsProxyService $hls, ?string $path = null): Response|StreamedResponse
    {
        try {
            $suffix = $hls->normalizeSuffix($path);
            $upstream = $hls->fetch($request, $path);
        } catch (HlsProxyException $e) {
            abort($e->status, $e->getMessage());
        }

        if (! $upstream->successful()) {
            abort(
                $upstream->status() >= 400 ? $upstream->status() : 502,
                'MediaMTX HLS upstream error.',
            );
        }

        $headers = $hls->responseHeaders($upstream, $suffix);

        if ($hls->isPlaylist($suffix)) {
            return response($upstream->body(), $upstream->status(), $headers);
        }

        return $hls->streamBody($upstream, $headers);
    }
}
