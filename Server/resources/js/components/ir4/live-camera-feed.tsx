import Hls from 'hls.js';
import { Maximize2, Minimize2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { CameraRoiCanvas } from '@/components/ir4/camera-roi-canvas';
import { LiveCameraPtzControls } from '@/components/ir4/live-camera-ptz-controls';
import { Button } from '@/components/ui/button';
import { liveWallHlsConfig, nudgeHlsToLiveEdge } from '@/lib/live-hls-config';
import type { CameraRoiOverlay } from '@/types/camera-roi';
import { CameraRoiSetStatus } from '@/types/enums';

type Props = {
    playbackUrl: string;
    title: string;
    cameraUuid?: string;
    cameraName?: string;
    ptzUrl?: string | null;
    canControlPtz?: boolean;
    /** Fired once when the feed goes blank / stalls — parent hides the player. */
    onDown?: () => void;
    roiOverlay?: CameraRoiOverlay | null;
    /** Match ROI canvas: stretch video to the container (editor / overlay). */
    fillFrame?: boolean;
};

const STARTUP_GRACE_MS = 8_000;
const STALL_MS = 25_000;
const BLANK_SAMPLE_MS = 2_000;

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

export function LiveCameraFeed({
    playbackUrl,
    title,
    cameraUuid,
    cameraName,
    ptzUrl = null,
    canControlPtz = false,
    onDown,
    roiOverlay = null,
    fillFrame = false,
}: Props) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const hlsRef = useRef<Hls | null>(null);
    const lastFrameAtRef = useRef(0);
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

            // Stall only — do not hide night/dark frames (luma “blank” false
            // positives wiped the whole live wall over Tailscale).
            if (now - lastFrameAtRef.current >= STALL_MS) {
                tearDown();
                notifyDown();
            }
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
                className={
                    fillFrame || roiOverlay
                        ? 'size-full object-fill'
                        : 'size-full object-contain'
                }
                title={title}
                muted
                autoPlay
                playsInline
                controls={false}
            />
            {roiOverlay && roiOverlay.rois.length > 0 && (
                <CameraRoiCanvas
                    rois={roiOverlay.rois}
                    selectedIndex={null}
                    editable={false}
                    stale={roiOverlay.status === CameraRoiSetStatus.Stale}
                    // Live wall: paint-only overlay — never steal clicks from
                    // fullscreen / PTZ (pointer-events-none + below chrome).
                    className="pointer-events-none z-[1]"
                />
            )}
            <div className="pointer-events-none absolute inset-x-0 bottom-0 z-[5] flex justify-end bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
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
