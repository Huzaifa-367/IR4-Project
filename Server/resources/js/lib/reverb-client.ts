/**
 * Browser Reverb endpoint. Lerd proxies `/app` (WebSocket) and `/apps`
 * (broadcast HTTP) onto the same site vhost as Laravel, so the workstation
 * must open the socket on the origin it already used for the dashboard —
 * not 127.0.0.1, localhost, or a hostname baked at `npm run build`.
 */
export type ReverbPageLocation = Pick<
    Location,
    'hostname' | 'port' | 'protocol'
>;

export type ReverbEchoClientOptions = {
    broadcaster: 'reverb';
    key: string;
    wsHost: string;
    wsPort: number;
    wssPort: number;
    forceTLS: boolean;
    enabledTransports: ['ws', 'wss'];
};

export function resolveReverbSocketPort(
    location: ReverbPageLocation,
): number {
    if (location.port !== '') {
        return Number(location.port);
    }

    return location.protocol === 'https:' ? 443 : 80;
}

export function buildReverbEchoOptions(
    key: string,
    location: ReverbPageLocation,
): ReverbEchoClientOptions {
    const forceTLS = location.protocol === 'https:';
    const port = resolveReverbSocketPort(location);

    return {
        broadcaster: 'reverb',
        key,
        wsHost: location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
    };
}
