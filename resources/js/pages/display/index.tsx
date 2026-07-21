import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { ZoneMap } from '@/components/ir4/zone-map';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import type {
    DashboardPermissions,
    DashboardSummary,
} from '@/types/dashboard';
import type { TrackingPosition, TrackingZone } from '@/types/tracking';

type Props = {
    summary: DashboardSummary;
    cycleSeconds: number;
    permissions: DashboardPermissions;
};

function unwrapSummary(payload: unknown): DashboardSummary {
    if (
        payload &&
        typeof payload === 'object' &&
        'data' in payload &&
        (payload as { data: unknown }).data &&
        typeof (payload as { data: unknown }).data === 'object'
    ) {
        return (payload as { data: DashboardSummary }).data;
    }

    return payload as DashboardSummary;
}

export default function DisplayIndex({
    summary: initial,
    cycleSeconds,
    permissions,
}: Props) {
    const [summary, setSummary] = useState(initial);
    const [pane, setPane] = useState(0);

    const onSnapshot = useCallback((payload: unknown) => {
        setSummary(unwrapSummary(payload));
    }, []);

    const { status } = useReverbChannel({
        channel: 'alerts',
        events: ['.alert.raised', '.alert.updated'],
        onEvent: () => {
            void fetch('/api/dashboard/summary', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((r) => r.json())
                .then(onSnapshot);
        },
        snapshotUrl: '/api/dashboard/summary',
        onSnapshot,
        pollIntervalMs: 60_000,
    });

    useEffect(() => {
        const seconds = Math.max(5, cycleSeconds || 20);
        const id = window.setInterval(() => {
            setPane((current) => (current + 1) % 3);
        }, seconds * 1000);

        return () => window.clearInterval(id);
    }, [cycleSeconds]);

    const criticalAlerts =
        summary.alerts?.latest.filter(
            (alert) => alert.severity === 'critical',
        ) ?? [];
    const zones = (summary.map?.zones ?? []) as TrackingZone[];
    const positions = (summary.map?.positions ?? []) as TrackingPosition[];

    return (
        <>
            <Head title="Command display" />
            <div className="flex min-h-screen flex-col bg-[#0B0F14] text-[#E6EDF3]">
                <header className="flex items-center justify-between gap-4 border-b border-[#243040] px-6 py-4">
                    <div>
                        <p className="text-xs tracking-[0.12em] text-[#8A97A6] uppercase">
                            IR4 command display
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Live safety picture
                        </h1>
                    </div>
                    <LiveStatusPill status={status} />
                </header>

                {summary.alerts && summary.alerts.open_critical > 0 && (
                    <div className="bg-[#F0506E] px-6 py-3 text-lg font-semibold text-white">
                        {summary.alerts.open_critical} critical alert
                        {summary.alerts.open_critical === 1 ? '' : 's'} open
                        {criticalAlerts[0]
                            ? ` — ${criticalAlerts[0].title}`
                            : ''}
                    </div>
                )}

                <main className="flex flex-1 flex-col gap-6 p-6">
                    {pane === 0 && (
                        <section className="grid flex-1 gap-6 lg:grid-cols-2">
                            <div className="rounded-[14px] border border-[#243040] bg-[#131A22] p-6">
                                <p className="text-sm tracking-wide text-[#8A97A6] uppercase">
                                    Total manpower
                                </p>
                                <p className="mt-2 font-mono text-7xl font-semibold tabular-nums">
                                    {summary.headcount?.total_on_site ?? 0}
                                </p>
                            </div>
                            <div className="rounded-[14px] border border-[#243040] bg-[#131A22] p-6">
                                <p className="mb-4 text-sm tracking-wide text-[#8A97A6] uppercase">
                                    Zone headcount
                                </p>
                                <ul className="space-y-3 text-2xl">
                                    {(summary.headcount?.by_zone ?? []).map(
                                        (zone) => (
                                            <li
                                                key={zone.zone_id}
                                                className="flex justify-between gap-4"
                                            >
                                                <span>{zone.zone_name}</span>
                                                <span className="font-mono tabular-nums">
                                                    {zone.count}
                                                </span>
                                            </li>
                                        ),
                                    )}
                                    {(summary.headcount?.by_zone ?? [])
                                        .length === 0 && (
                                        <li className="text-[#8A97A6]">
                                            No on-site workers
                                        </li>
                                    )}
                                </ul>
                            </div>
                        </section>
                    )}

                    {pane === 1 && (
                        <section className="rounded-[14px] border border-[#243040] bg-[#131A22] p-6">
                            <p className="mb-4 text-sm tracking-wide text-[#8A97A6] uppercase">
                                Gas panels
                            </p>
                            {permissions.view_gas && summary.gas ? (
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {summary.gas.panels.map((panel) => (
                                        <div
                                            key={panel.device_id}
                                            className="rounded-lg border border-[#243040] bg-[#1B2530] p-4"
                                        >
                                            <p className="text-lg">
                                                {panel.asset ??
                                                    `Device #${panel.device_id}`}
                                            </p>
                                            <p
                                                className={`mt-2 font-mono text-3xl tabular-nums ${
                                                    panel.status === 'crit'
                                                        ? 'text-[#F0506E]'
                                                        : panel.status ===
                                                            'warn'
                                                          ? 'text-[#F5A524]'
                                                          : 'text-[#34D399]'
                                                }`}
                                            >
                                                {panel.status.toUpperCase()}
                                            </p>
                                            {panel.channels?.co2_ppm != null && (
                                                <p className="mt-1 text-[#8A97A6]">
                                                    CO₂ {panel.channels.co2_ppm}{' '}
                                                    ppm
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                    {summary.gas.panels.length === 0 && (
                                        <p className="text-[#8A97A6]">
                                            No gas devices registered
                                        </p>
                                    )}
                                </div>
                            ) : (
                                <p className="text-[#8A97A6]">
                                    Gas widgets unavailable for this account
                                </p>
                            )}
                        </section>
                    )}

                    {pane === 2 && (
                        <section className="rounded-[14px] border border-[#243040] bg-[#131A22] p-6">
                            <p className="mb-4 text-sm tracking-wide text-[#8A97A6] uppercase">
                                Live zone map
                            </p>
                            {permissions.view_tracking ? (
                                <ZoneMap
                                    zones={zones}
                                    positions={positions}
                                    occupancy={summary.headcount?.by_zone}
                                />
                            ) : (
                                <p className="text-[#8A97A6]">
                                    Map unavailable for this account
                                </p>
                            )}
                        </section>
                    )}
                </main>

                <footer className="overflow-hidden border-t border-[#243040] bg-[#131A22] px-6 py-3 text-sm text-[#8A97A6]">
                    <div className="animate-[ticker_40s_linear_infinite] whitespace-nowrap">
                        {(summary.alerts?.latest ?? [])
                            .map((alert) => `[${alert.severity}] ${alert.title}`)
                            .join('   ·   ') ||
                            'No recent alerts — site calm'}
                    </div>
                </footer>

                <style>{`
                    @keyframes ticker {
                        0% { transform: translateX(100%); }
                        100% { transform: translateX(-100%); }
                    }
                `}</style>
            </div>
        </>
    );
}
