<?php

namespace App\Services\Camera;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;

/**
 * MediaMTX HLS cookieCheck sets Secure;SameSite=None. Guzzle refuses to send
 * Secure cookies on http:// upstreams (Tailscale LAN), so clear Secure on store.
 */
final class MediaMtxCookieJar extends CookieJar
{
    public function setCookie(SetCookie $cookie): bool
    {
        $cookie->setSecure(false);

        return parent::setCookie($cookie);
    }
}
