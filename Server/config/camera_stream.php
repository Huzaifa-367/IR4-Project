<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Browser playback URL
    |--------------------------------------------------------------------------
    |
    | Browsers cannot consume RTSP. MediaMTX pulls each camera's stream_url and
    | publishes a browser-safe HLS page. IR4 syncs paths via the MediaMTX API
    | whenever a camera RTSP URL is saved in the dashboard.
    |
    | Example: http://10.0.0.5:8888/{reference}
    |
    */
    'browser_url_template' => env(
        'CAMERA_BROWSER_STREAM_URL_TEMPLATE',
        env('APP_ENV') === 'local' ? 'http://127.0.0.1:8888/{reference}' : null,
    ),

    /*
    |--------------------------------------------------------------------------
    | MediaMTX control API
    |--------------------------------------------------------------------------
    |
    | Leave api_url empty to skip sync (live wall still needs a running gateway
    | and browser_url_template). When set, create/update camera pushes RTSP
    | into MediaMTX automatically.
    |
    */
    'mediamtx' => [
        // Use `gateway` under Lerd/Podman — resolves a host that can reach MediaMTX on :9997.
        'api_url' => rtrim((string) env('MEDIAMTX_API_URL', ''), '/'),
        // Optional SCC LAN IP (e.g. 192.168.3.149). Preferred when `api_url=gateway`.
        'host_ip' => env('MEDIAMTX_HOST_IP'),
        // Optional direct HLS upstream. Default: same host as API on port 8888.
        'hls_url' => rtrim((string) env('MEDIAMTX_HLS_URL', ''), '/'),
        'api_user' => env('MEDIAMTX_API_USER'),
        'api_pass' => env('MEDIAMTX_API_PASS'),
        'timeout' => (int) env('MEDIAMTX_API_TIMEOUT', 5),
        // false = keep RTSP warm for live wall (smoother); true = connect only when viewed.
        'source_on_demand' => filter_var(
            env('MEDIAMTX_SOURCE_ON_DEMAND', false),
            FILTER_VALIDATE_BOOL,
        ),
        'rtsp_transport' => env('MEDIAMTX_RTSP_TRANSPORT', 'tcp'),
    ],
];
