<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MapTileController extends BaseController
{
    /**
     * Serve bundled offline basemap archives with HTTP Range support.
     *
     * PMTiles reads small byte ranges out of a large local archive, which
     * requires real byte-serving (206 Partial Content) — something PHP's
     * built-in dev server does not do for plain public/ static files.
     * Symfony's BinaryFileResponse handles Range/If-Range correctly, so we
     * route these specific files through the app instead of serving them
     * directly from public/.
     */
    public function __invoke(Request $request, string $filename): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-z0-9_-]+\.pmtiles$/i', $filename) === 1, 404);

        $path = storage_path("app/maps/{$filename}");

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
