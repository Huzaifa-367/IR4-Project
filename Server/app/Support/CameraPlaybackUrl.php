<?php

namespace App\Support;

/**
 * Browser HLS/playlist URL from camera_stream.browser_url_template (Live wall / ROI editor).
 */
final class CameraPlaybackUrl
{
    public static function forReference(string $reference): ?string
    {
        $template = config('camera_stream.browser_url_template');
        if (! is_string($template) || $template === '') {
            return null;
        }

        $url = str_replace('{reference}', rawurlencode($reference), $template);
        // MediaMTX HLS reader expects a trailing slash on path roots.
        if (! str_contains(parse_url($url, PHP_URL_PATH) ?: $url, '.') && ! str_ends_with($url, '/')) {
            $url .= '/';
        }

        return $url;
    }
}
