import Hls from 'hls.js';
import { Maximize2, Minimize2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { LiveCameraPtzControls } from '@/components/ir4/live-camera-ptz-controls';
import { Button } from '@/components/ui/button';

type Props = {
    playbackUrl: string;
    title: string;
    cameraUuid?: string;
    ptzUrl?: string | null;
    canControlPtz?: boolean;
};

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
    ptzUrl = null,
    canControlPtz = false,
}: Props) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const playlistUrl = resolvePlaylistUrl(playbackUrl);

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

    useEffect(() => {
        const video = videoRef.current;

        if (!video || playlistUrl === '') {
            return;
        }

        setError(null);
        let hls: Hls | null = null;
        let cancelled = false;

        const onFatal = (message: string): void => {
            if (!cancelled) {
                setError(message);
            }
        };

        if (Hls.isSupported()) {
            hls = new Hls({
                enableWorker: true,
                lowLatencyMode: true,
                backBufferLength: 30,
                maxBufferLength: 20,
                maxMaxBufferLength: 40,
                liveSyncDurationCount: 3,
                liveMaxLatencyDurationCount: 10,
            });
            hls.loadSource(playlistUrl);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, () => {
                void video.play().catch(() => undefined);
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

                onFatal(data.type || 'HLS playback failed');
                hls.destroy();
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = playlistUrl;
            void video.play().catch(() => undefined);
        } else {
            onFatal('HLS is not supported in this browser');
        }

        return () => {
            cancelled = true;

            if (hls !== null) {
                hls.destroy();
            }

            video.removeAttribute('src');
            video.load();
        };
    }, [playlistUrl]);

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
                    ptzUrl={ptzUrl}
                    className="absolute bottom-4 left-4 z-10"
                />
            )}
            {error !== null && (
                <div className="absolute inset-0 flex items-center justify-center bg-black/80 px-3 text-center text-xs text-text-faint">
                    {error}
                </div>
            )}
        </div>
    );
}
