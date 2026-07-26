import { useEcho, useConnectionStatus } from '@laravel/echo-react';
import { useCallback, useEffect, useRef } from 'react';
import { useAuth, useSharedSettings } from '@/hooks/use-auth';

export type ReverbLiveStatus = 'live' | 'reconnecting' | 'offline';

type UseReverbChannelOptions<TPayload> = {
    channel: string;
    events: string[];
    onEvent: (payload: TPayload) => void;
    /** Snapshot URL polled while socket is down and on reconnect. */
    snapshotUrl?: string;
    onSnapshot?: (data: unknown) => void;
    pollIntervalMs?: number;
};

export type UseReverbChannelResult = {
    status: ReverbLiveStatus;
    refresh: () => Promise<void>;
};

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
}: UseReverbChannelOptions<TPayload>): UseReverbChannelResult {
    const { isAuthenticated } = useAuth();
    const { poll_fallback_seconds: pollFallbackSeconds } = useSharedSettings();
    const resolvedPollIntervalMs =
        pollIntervalMs ?? Math.max(5, pollFallbackSeconds) * 1000;
    const connection = useConnectionStatus();
    const status = isAuthenticated
        ? mapConnectionStatus(connection)
        : 'offline';
    const prevStatus = useRef<ReverbLiveStatus>(status);
    const onEventRef = useRef(onEvent);

    useEffect(() => {
        onEventRef.current = onEvent;
    });

    useEcho(channel, events, (payload: TPayload) => {
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

        if (status === 'live') {
            return;
        }

        const id = window.setInterval(() => {
            void refresh();
        }, resolvedPollIntervalMs);

        return () => window.clearInterval(id);
    }, [isAuthenticated, status, snapshotUrl, resolvedPollIntervalMs, refresh]);

    useEffect(() => {
        if (prevStatus.current !== 'live' && status === 'live' && snapshotUrl) {
            void refresh();
        }

        prevStatus.current = status;
    }, [status, snapshotUrl, refresh]);

    return { status, refresh };
}
