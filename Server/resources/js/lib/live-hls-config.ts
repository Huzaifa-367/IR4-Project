import type { HlsConfig } from 'hls.js';
import type Hls from 'hls.js';

/**
 * Live-wall HLS. Prefer stability over LL-HLS — Tailscale / remote MediaMTX
 * cannot sustain part-hold-back without blank frames.
 */
export const liveWallHlsConfig: Partial<HlsConfig> = {
    enableWorker: true,
    lowLatencyMode: false,
    backBufferLength: 30,
    maxBufferLength: 30,
    maxMaxBufferLength: 60,
    liveSyncDurationCount: 3,
    liveMaxLatencyDurationCount: 10,
    liveDurationInfinity: true,
};

export function nudgeHlsToLiveEdge(
    hls: Hls | null,
    video: HTMLVideoElement | null,
): void {
    if (hls === null || video === null) {
        return;
    }

    const liveEdge = hls.liveSyncPosition;

    if (typeof liveEdge === 'number' && Number.isFinite(liveEdge)) {
        video.currentTime = liveEdge;
    }
}
