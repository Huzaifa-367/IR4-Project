import type { HlsConfig } from 'hls.js';
import type Hls from 'hls.js';

/**
 * Low-latency HLS for the live wall / PTZ — stay near the live edge without
 * buffering so far that pan/tilt feels disconnected from the picture.
 */
export const liveWallHlsConfig: Partial<HlsConfig> = {
    enableWorker: true,
    lowLatencyMode: true,
    backBufferLength: 4,
    maxBufferLength: 6,
    maxMaxBufferLength: 10,
    liveSyncDurationCount: 1,
    liveMaxLatencyDurationCount: 4,
    liveDurationInfinity: true,
    highBufferWatchdogPeriod: 1,
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
