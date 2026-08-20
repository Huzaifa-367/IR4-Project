import { useCallback, useState } from 'react';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import { patchSystemHealth } from '@/lib/dashboard-live';
import { summary as dashboardSummary } from '@/routes/dashboard';
import type { DashboardSummary } from '@/types/dashboard';

export type SystemHealthSummary = {
    online: number;
    total: number;
    uptimePct: number | null;
    tone: 'ok' | 'warn' | 'crit' | 'muted';
    label: string;
    meta: string;
};

type DeviceStatusPayload = {
    device_id: number;
    status: string;
    device_name: string;
    asset_id?: number | null;
};

function unwrapSummary(payload: unknown): DashboardSummary | null {
    if (
        payload &&
        typeof payload === 'object' &&
        'data' in payload &&
        (payload as { data: unknown }).data &&
        typeof (payload as { data: unknown }).data === 'object'
    ) {
        return (payload as { data: DashboardSummary }).data;
    }

    return (payload as DashboardSummary) ?? null;
}

/**
 * Sidebar hardware health (DOC-05 §6.6). Snapshot/poll uses the dashboard
 * summary; online/total are individual devices (not poles). DeviceStatusChanged
 * patches asset tiles + device counters without refetching the aggregate.
 */
export function useSystemHealth(enabled: boolean): SystemHealthSummary | null {
    const [health, setHealth] = useState<DashboardSummary['system_health']>();

    const onSnapshot = useCallback((payload: unknown) => {
        setHealth(unwrapSummary(payload)?.system_health);
    }, []);

    useReverbChannel({
        channel: 'system',
        events: ['.DeviceStatusChanged'],
        onEvent: (payload: unknown) => {
            const event = payload as DeviceStatusPayload;

            if (typeof event.device_name !== 'string') {
                return;
            }

            setHealth((current) => patchSystemHealth(current, event));
        },
        snapshotUrl: enabled ? dashboardSummary.url() : undefined,
        onSnapshot,
        pollIntervalMs: 60_000,
    });

    if (!enabled) {
        return null;
    }

    const meta = !Array.isArray(health) ? health : undefined;
    // Prefer server device counts; never fall back to pole/asset greens.
    const total = meta?.total ?? 0;
    const online = meta?.online ?? 0;
    const offline = Math.max(0, total - online);

    if (total === 0) {
        return {
            online,
            total,
            uptimePct: meta?.uptime_pct ?? null,
            tone: 'muted',
            label: 'No hardware yet',
            meta: 'Register devices in Settings',
        };
    }

    if (offline === 0) {
        return {
            online,
            total,
            uptimePct: meta?.uptime_pct ?? 100,
            tone: 'ok',
            label: 'All Nominal',
            meta: 'All devices reporting',
        };
    }

    return {
        online,
        total,
        uptimePct: meta?.uptime_pct ?? null,
        tone: offline / total > 0.3 ? 'crit' : 'warn',
        label: `${offline} Offline`,
        meta: `${online}/${total} devices reporting`,
    };
}
