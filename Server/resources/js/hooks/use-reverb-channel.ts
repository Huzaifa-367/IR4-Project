import { useEcho, useConnectionStatus } from '@laravel/echo-react';
import { useCallback, useEffect, useRef } from 'react';
import { useAuth, useSharedSettings } from '@/hooks/use-auth';

export type ReverbLiveStatus = 'live' | 'reconnecting' | 'offline';

export function combineReverbStatus(
    ...statuses: ReverbLiveStatus[]
): ReverbLiveStatus {
    if (statuses.includes('offline')) {
        return 'offline';
    }

    if (statuses.includes('reconnecting')) {
        return 'reconnecting';
    }

    return 'live';
}

type UseReverbChannelOptions<TPayload> = {
    channel: string;
    events: string[];
    onEvent: (payload: TPayload) => void;
    /** Snapshot URL polled while socket is down and on reconnect. */
    snapshotUrl?: string;
    onSnapshot?: (data: unknown) => void;
    pollIntervalMs?: number;
    /**
     * Keep polling even while Reverb is connected (needed when ingest can be
     * backfill-only with no broadcast, e.g. gas buffer flush).
     */
    pollWhileLive?: boolean;
};

export type UseReverbChannelResult = {
    status: ReverbLiveStatus;
    refresh: () => Promise<void>;
};

/** True when Vite baked a Reverb key (on-prem). False on Hostinger-style deploys. */
export function isReverbClientEnabled(): boolean {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    return typeof key === 'string' && key.length > 0;
}

function mapConnectionStatus(
    connection: ReturnType<typeof useConnectionStatus>,
): ReverbLiveStatus {
    if (connection === 'connected') {
        return 'live';
    }

    if (connection === 'connecting' || connection === 'reconnecting') {
        return 'reconnecting';
    }

    return 'offline';
}

/**
 * Subscribe to a private Reverb channel with LIVE / RECONNECTING / offline
 * status and optional poll-fallback snapshot (DOC-08 §5.4–5.5).
 *
 * Mount only when the user is authenticated (parent should gate render).
 */
export function useReverbChannel<TPayload = unknown>({
    channel,
    events,
    onEvent,
    snapshotUrl,
    onSnapshot,
    pollIntervalMs,
    pollWhileLive = false,
}: UseReverbChannelOptions<TPayload>): UseReverbChannelResult {
    const { isAuthenticated } = useAuth();
    const { poll_fallback_seconds: pollFallbackSeconds } = useSharedSettings();
    const resolvedPollIntervalMs =
        pollIntervalMs ?? Math.max(5, pollFallbackSeconds) * 1000;
    const reverbEnabled = isReverbClientEnabled();
    const connection = useConnectionStatus();
    // Without a Reverb key, null broadcaster reports "connected" — force offline
    // so DOC-08 poll fallback keeps running on Hostinger.
    const status: ReverbLiveStatus =
        isAuthenticated && reverbEnabled
            ? mapConnectionStatus(connection)
            : 'offline';
    const prevStatus = useRef<ReverbLiveStatus>(status);
    const onEventRef = useRef(onEvent);

    useEffect(() => {
        onEventRef.current = onEvent;
    });

    useEcho(channel, events, (payload: TPayload) => {
        if (!reverbEnabled) {
            return;
        }

        onEventRef.current(payload);
    });

    const refresh = useCallback(async (): Promise<void> => {
        if (!isAuthenticated || !snapshotUrl || !onSnapshot) {
            return;
        }

        try {
            const response = await fetch(snapshotUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            onSnapshot(await response.json());
        } catch {
            // Poll failures keep RECONNECTING / offline visible via socket status.
        }
    }, [isAuthenticated, snapshotUrl, onSnapshot]);

    useEffect(() => {
        if (!isAuthenticated || !snapshotUrl) {
            return;
        }

        void refresh();

        if (status === 'live' && !pollWhileLive) {
            return;
        }

        const id = window.setInterval(() => {
            void refresh();
        }, resolvedPollIntervalMs);

        return () => window.clearInterval(id);
    }, [
        isAuthenticated,
        status,
        snapshotUrl,
        resolvedPollIntervalMs,
        refresh,
        pollWhileLive,
    ]);

    useEffect(() => {
        if (prevStatus.current !== 'live' && status === 'live' && snapshotUrl) {
            void refresh();
        }

        prevStatus.current = status;
    }, [status, snapshotUrl, refresh]);

    return { status, refresh };
}
