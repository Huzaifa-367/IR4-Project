import Hls from 'hls.js';
import { useEffect, useRef, useState } from 'react';

type Props = {
    playbackUrl: string;
    title: string;
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

export function LiveCameraFeed({ playbackUrl, title }: Props) {
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const [error, setError] = useState<string | null>(null);
    const playlistUrl = resolvePlaylistUrl(playbackUrl);

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

    return (
        <div className="relative size-full bg-black">
            <video
                ref={videoRef}
                className="size-full object-contain"
                title={title}
                muted
                autoPlay
                playsInline
                controls={false}
            />
            {error !== null && (
                <div className="absolute inset-0 flex items-center justify-center bg-black/80 px-3 text-center text-xs text-text-faint">
                    {error}
                </div>
            )}
        </div>
    );
}
