# Browser camera bridge

Browsers do not support RTSP. MediaMTX pulls each private RTSP source and
publishes an HLS/WebRTC endpoint for the authenticated IR4 live wall.

## Local test feed

Start the bridge:

```sh
docker compose -f deploy/mediamtx/compose.yml up -d
```

The development configuration pulls Wowza's public RTSP test feed on demand.
Open `http://127.0.0.1:8888/test` to verify MediaMTX directly, then open IR4 at
`http://127.0.0.1:8000/live`.

## Production

Replace the `test` path with one path per camera reference and point each
`source` at its LAN RTSP URL. Keep camera credentials only in the server-side
MediaMTX configuration. Set:

```dotenv
CAMERA_BROWSER_STREAM_URL_TEMPLATE=http://media-gateway.internal:8888/{reference}
```

The browser receives only the playback URL; IR4 never serializes the raw RTSP
URL or credentials.
