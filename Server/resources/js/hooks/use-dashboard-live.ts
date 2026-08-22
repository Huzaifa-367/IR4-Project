import { useCallback } from 'react';
import {
    combineReverbStatus,
    useReverbChannel,
} from '@/hooks/use-reverb-channel';
import type { ReverbLiveStatus } from '@/hooks/use-reverb-channel';
import { applyDashboardEvent } from '@/lib/dashboard-live';
import type { DashboardSummary } from '@/types/dashboard';

type Options = {
    snapshotUrl: string;
    onSnapshot: (payload: unknown) => void;
    setSummary: (
        next:
            | DashboardSummary
            | ((current: DashboardSummary) => DashboardSummary),
    ) => void;
};

export function useDashboardLive({
    snapshotUrl,
    onSnapshot,
    setSummary,
}: Options): ReverbLiveStatus {
    const onLiveEvent = useCallback(
        (payload: unknown) => {
            setSummary((current) => applyDashboardEvent(current, payload));
        },
        [setSummary],
    );

    const alertsLive = useReverbChannel({
        channel: 'alerts',
        events: ['.AlertRaised', '.AlertUpdated'],
        onEvent: onLiveEvent,
        snapshotUrl,
        onSnapshot,
        pollIntervalMs: 60_000,
    });

    const trackingLive = useReverbChannel({
        channel: 'tracking',
        events: ['.HeadcountUpdated', '.PositionsUpdated'],
        onEvent: onLiveEvent,
        pollIntervalMs: 60_000,
    });

    const gasLive = useReverbChannel({
        channel: 'gas',
        events: ['.GasLiveUpdated'],
        onEvent: onLiveEvent,
        pollIntervalMs: 60_000,
    });

    const systemLive = useReverbChannel({
        channel: 'system',
        events: ['.DeviceStatusChanged'],
        onEvent: onLiveEvent,
        pollIntervalMs: 60_000,
    });

    const environmentLive = useReverbChannel({
        channel: 'environment',
        events: ['.EnvironmentUpdated'],
        onEvent: onLiveEvent,
        pollIntervalMs: 60_000,
    });

    return combineReverbStatus(
        alertsLive.status,
        trackingLive.status,
        gasLive.status,
        systemLive.status,
        environmentLive.status,
    );
}
