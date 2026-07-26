import { useCallback, useState } from 'react';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import type { DashboardSummary } from '@/types/dashboard';
import { systemHealthAssets } from '@/types/dashboard';

export type SystemHealthSummary = {
    online: number;
    total: number;
    uptimePct: number | null;
    tone: 'ok' | 'warn' | 'crit' | 'muted';
    label: string;
    meta: string;
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
 * Sidebar-wide hardware health summary (DOC-05 §6.6), fed by the same
 * dashboard snapshot the `/dashboard` page uses. Follows the DOC-08 §5.4
 * Reverb + poll-fallback pattern: the `system` channel's `DeviceStatusChanged`
 * event triggers a refetch, a snapshot covers mount/reconnect, and a 60s
 * poll covers socket downtime.
 */
export function useSystemHealth(enabled: boolean): SystemHealthSummary | null {
    const [health, setHealth] = useState<DashboardSummary['system_health']>();

    const onSnapshot = useCallback((payload: unknown) => {
        setHealth(unwrapSummary(payload)?.system_health);
    }, []);

    const fetchHealth = useCallback(() => {
        if (!enabled) {
            return;
        }

        void fetch('/api/dashboard/summary', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            .then(onSnapshot);
    }, [enabled, onSnapshot]);

    useReverbChannel({
        channel: 'system',
        events: ['.DeviceStatusChanged'],
        onEvent: fetchHealth,
        snapshotUrl: enabled ? '/api/dashboard/summary' : undefined,
        onSnapshot,
        pollIntervalMs: 60_000,
    });

    if (!enabled) {
        return null;
    }

    const assets = systemHealthAssets(health);
    const meta = !Array.isArray(health) ? health : undefined;
    const total = meta?.total ?? assets.length;
    const online =
        meta?.online ?? assets.filter((a) => a.status === 'green').length;
    const offline = Math.max(0, total - online);

    if (total === 0) {
        return {
            online,
            total,
            uptimePct: meta?.uptime_pct ?? null,
            tone: 'muted',
            label: 'No hardware yet',
            meta: 'Register assets in Settings',
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
