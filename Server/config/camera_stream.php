<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Browser playback URL
    |--------------------------------------------------------------------------
    |
    | Browsers cannot consume RTSP. MediaMTX pulls the private camera stream
    | and publishes a browser-safe HLS/WebRTC endpoint. The camera reference
    | placeholder supports one gateway path per production camera.
    |
    */
    'browser_url_template' => env(
        'CAMERA_BROWSER_STREAM_URL_TEMPLATE',
        env('APP_ENV') === 'local' ? 'http://127.0.0.1:8888/test' : null,
    ),
];
