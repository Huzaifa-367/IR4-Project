import Hls from 'hls.js';
import { Maximize2, Minimize2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { LiveCameraPtzControls } from '@/components/ir4/live-camera-ptz-controls';
import { Button } from '@/components/ui/button';
import { liveWallHlsConfig, nudgeHlsToLiveEdge } from '@/lib/live-hls-config';

type Props = {
    playbackUrl: string;
    title: string;
    cameraUuid?: string;
    cameraName?: string;
    ptzUrl?: string | null;
    canControlPtz?: boolean;
    /** Fired once when the feed goes blank / stalls — parent hides the player. */
    onDown?: () => void;
};

const STARTUP_GRACE_MS = 6_000;
const STALL_MS = 12_000;
const BLANK_SAMPLE_MS = 2_000;
const BLANK_HOLD_MS = 8_000;
const LUMA_BLANK_MAX = 8;

/**
 * Play MediaMTX HLS via same-origin /hls/{reference}/index.m3u8 (or absolute
 * :8888 URL). Avoids MediaMTX HTML reader iframes, which break under /hls
 * reverse-proxy (absolute paths / Location redirects drop the /hls prefix).
 */
function resolvePlaylistUrl(playbackUrl: string): string {
    const base = playbackUrl.trim();

    if (base === '') {
        return '';
    }

    if (/\.m3u8(\?|$)/i.test(base)) {
        return base;
    }

    return base.endsWith('/') ? `${base}index.m3u8` : `${base}/index.m3u8`;
}

function sampleIsBlank(video: HTMLVideoElement): boolean | null {
    if (video.videoWidth < 8 || video.videoHeight < 8) {
        return null;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 16;
    canvas.height = 9;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });

    if (ctx === null) {
        return null;
    }

    try {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        let luma = 0;
        const samples = pixels.length / 4;

        for (let i = 0; i < pixels.length; i += 4) {
            luma +=
                0.2126 * pixels[i] +
                0.7152 * pixels[i + 1] +
                0.0722 * pixels[i + 2];
        }

        return luma / samples < LUMA_BLANK_MAX;
    } catch {
        return null;
    }
}

export function LiveCameraFeed({
    playbackUrl,
    title,
    cameraUuid,
    cameraName,
    ptzUrl = null,
    canControlPtz = false,
    onDown,
}: Props) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const hlsRef = useRef<Hls | null>(null);
    const lastFrameAtRef = useRef(0);
    const blankSinceRef = useRef<number | null>(null);
    const downNotifiedRef = useRef(false);
    const onDownRef = useRef(onDown);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const playlistUrl = resolvePlaylistUrl(playbackUrl);

    useEffect(() => {
        onDownRef.current = onDown;
    }, [onDown]);

    const notifyDown = useCallback((): void => {
        if (downNotifiedRef.current) {
            return;
        }

        downNotifiedRef.current = true;
        onDownRef.current?.();
    }, []);

    useEffect(() => {
        const onFsChange = (): void => {
            setIsFullscreen(
                document.fullscreenElement === containerRef.current,
            );
        };

        document.addEventListener('fullscreenchange', onFsChange);

        return () => {
            document.removeEventListener('fullscreenchange', onFsChange);
        };
    }, []);

    const toggleFullscreen = useCallback(async (): Promise<void> => {
        const el = containerRef.current;

        if (!el) {
            return;
        }

        try {
            if (document.fullscreenElement === el) {
                await document.exitFullscreen();
            } else {
                await el.requestFullscreen();
            }
        } catch {
            // Browser may deny fullscreen without a user gesture or support.
        }
    }, []);

    const nudgeLiveEdge = useCallback((): void => {
        nudgeHlsToLiveEdge(hlsRef.current, videoRef.current);
    }, []);

    useEffect(() => {
        const video = videoRef.current;

        if (!video || playlistUrl === '') {
            return;
        }

        downNotifiedRef.current = false;
        lastFrameAtRef.current = Date.now();
        blankSinceRef.current = null;
        let hls: Hls | null = null;
        let cancelled = false;

        const markActivity = (): void => {
            lastFrameAtRef.current = Date.now();
        };

        const tearDown = (): void => {
            if (hls !== null) {
                hls.destroy();
                hls = null;
            }

            hlsRef.current = null;
            video.removeAttribute('src');
            video.load();
        };

        const onFatal = (): void => {
            if (cancelled) {
                return;
            }

            tearDown();
            notifyDown();
        };

        if (Hls.isSupported()) {
            hls = new Hls(liveWallHlsConfig);
            hlsRef.current = hls;
            hls.loadSource(playlistUrl);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, () => {
                markActivity();
                void video.play().catch(() => undefined);
                nudgeHlsToLiveEdge(hls, video);
            });
            hls.on(Hls.Events.FRAG_BUFFERED, () => {
                markActivity();

                if (video.paused) {
                    void video.play().catch(() => undefined);
                }
            });
            hls.on(Hls.Events.ERROR, (_event, data) => {
                if (!data.fatal || hls === null) {
                    return;
                }

                if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                    hls.startLoad();

                    return;
                }

                if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                    hls.recoverMediaError();

                    return;
                }

                onFatal();
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = playlistUrl;
            void video.play().catch(() => undefined);
        } else {
            onFatal();
        }

        const startedAt = Date.now();
        const watchdog = window.setInterval(() => {
            if (cancelled || downNotifiedRef.current) {
                return;
            }

            const now = Date.now();

            if (now - startedAt < STARTUP_GRACE_MS) {
                return;
            }

            if (now - lastFrameAtRef.current >= STALL_MS) {
                tearDown();
                notifyDown();

                return;
            }

            const blank = sampleIsBlank(video);

            if (blank === true) {
                blankSinceRef.current ??= now;

                if (now - blankSinceRef.current >= BLANK_HOLD_MS) {
                    tearDown();
                    notifyDown();
                }

                return;
            }

            blankSinceRef.current = null;
        }, BLANK_SAMPLE_MS);

        return () => {
            cancelled = true;
            window.clearInterval(watchdog);
            tearDown();
        };
    }, [playlistUrl, notifyDown]);

    const showPtzControls =
        isFullscreen &&
        canControlPtz &&
        cameraUuid !== undefined &&
        ptzUrl !== null &&
        ptzUrl !== '';

    return (
        <div
            ref={containerRef}
            className="group relative size-full bg-black"
            onDoubleClick={() => {
                void toggleFullscreen();
            }}
        >
            <video
                ref={videoRef}
                className="size-full object-contain"
                title={title}
                muted
                autoPlay
                playsInline
                controls={false}
            />
            <div className="pointer-events-none absolute inset-x-0 bottom-0 flex justify-end bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
                <Button
                    type="button"
                    size="icon"
                    variant="secondary"
                    className="pointer-events-auto size-8"
                    aria-label={
                        isFullscreen
                            ? `Exit fullscreen: ${title}`
                            : `Fullscreen: ${title}`
                    }
                    onClick={(event) => {
                        event.stopPropagation();
                        void toggleFullscreen();
                    }}
                >
                    {isFullscreen ? (
                        <Minimize2 className="size-4" />
                    ) : (
                        <Maximize2 className="size-4" />
                    )}
                </Button>
            </div>
            {showPtzControls && (
                <LiveCameraPtzControls
                    cameraUuid={cameraUuid}
                    cameraName={cameraName ?? title}
                    ptzUrl={ptzUrl}
                    enabled={canControlPtz}
                    onInteract={nudgeLiveEdge}
                    className="absolute bottom-4 left-4 z-10"
                />
            )}
        </div>
    );
}
