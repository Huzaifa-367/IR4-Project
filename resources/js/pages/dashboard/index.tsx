import { Head, Link } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { StatCard } from '@/components/ir4/stat-card';
import { ZoneMap } from '@/components/ir4/zone-map';
import { usePermissions } from '@/hooks/use-permissions';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import { dashboard } from '@/routes';
import type {
    DashboardPermissions,
    DashboardSummary,
} from '@/types/dashboard';
import type { TrackingPosition, TrackingZone } from '@/types/tracking';

type Props = {
    summary: DashboardSummary;
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

export default function DashboardIndex({
    summary: initial,
    permissions,
}: Props) {
    const [summary, setSummary] = useState(initial);
    const { can } = usePermissions();

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

    useReverbChannel({
        channel: 'tracking',
        events: ['.headcount.updated', '.positions.updated'],
        onEvent: () => {
            void fetch('/api/dashboard/summary', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((r) => r.json())
                .then(onSnapshot);
        },
        pollIntervalMs: 60_000,
    });

    useReverbChannel({
        channel: 'gas',
        events: ['.gas.reading', '.gas.alarm'],
        onEvent: () => {
            void fetch('/api/dashboard/summary', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((r) => r.json())
                .then(onSnapshot);
        },
        pollIntervalMs: 60_000,
    });

    const zones = (summary.map?.zones ?? []) as TrackingZone[];
    const positions = (summary.map?.positions ?? []) as TrackingPosition[];
    const showMap = permissions.view_tracking && can('view-tracking');

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-xs tracking-[0.08em] text-muted-foreground uppercase">
                            Control room
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Live safety picture
                        </h1>
                    </div>
                    <div className="flex items-center gap-3">
                        <LiveStatusPill status={status} />
                        <Link
                            href="/display"
                            className="text-sm text-primary underline"
                        >
                            Open display
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4 [&>*]:min-w-0">
                    {summary.headcount && (
                        <StatCard
                            label="Total manpower"
                            value={summary.headcount.total_on_site}
                            href={showMap ? '/tracking' : undefined}
                        />
                    )}
                    {summary.alerts && (
                        <StatCard
                            label="Open alerts"
                            value={
                                summary.alerts.open_critical +
                                summary.alerts.open_warning
                            }
                            delta={`${summary.alerts.open_warning} warning · ${summary.alerts.open_critical} critical`}
                            deltaTone={
                                summary.alerts.open_critical > 0
                                    ? 'crit'
                                    : 'neutral'
                            }
                            pulseCrit={summary.alerts.open_critical > 0}
                            href="/alerts"
                        />
                    )}
                    {permissions.view_incidents && summary.incidents && (
                        <StatCard
                            label="Open incidents"
                            value={summary.incidents.open}
                            delta={`${summary.incidents.under_review} under review`}
                            href="/incidents"
                        />
                    )}
                    {permissions.view_lsr && summary.lsr && (
                        <StatCard
                            label="Open LSR"
                            value={summary.lsr.open}
                            href="/lsr-violations"
                        />
                    )}
                    {permissions.view_equipment && summary.equipment && (
                        <StatCard
                            label="Overdue equipment"
                            value={summary.equipment.overdue}
                            delta={`${summary.equipment.due_soon} due soon · ${summary.equipment.checked_out} checked out`}
                            deltaTone={
                                summary.equipment.overdue > 0
                                    ? 'crit'
                                    : 'neutral'
                            }
                            href="/equipment"
                        />
                    )}
                    {permissions.view_ppe && summary.ppe_today && (
                        <StatCard
                            label="PPE today"
                            value={summary.ppe_today.total}
                            delta={`${summary.ppe_today.trend_delta >= 0 ? '+' : ''}${summary.ppe_today.trend_delta} vs yesterday`}
                            deltaTone={
                                summary.ppe_today.trend_delta > 0
                                    ? 'crit'
                                    : 'ok'
                            }
                            href="/ppe/violations"
                        />
                    )}
                    {summary.weather && (
                        <StatCard
                            label="Weather"
                            value={
                                summary.weather.temperature_c !== null
                                    ? `${summary.weather.temperature_c}°C`
                                    : '—'
                            }
                            delta={
                                summary.weather.updated_at
                                    ? `RH ${summary.weather.humidity_pct ?? '—'}% · wind ${summary.weather.wind_speed_ms ?? '—'} m/s`
                                    : 'No weather feed'
                            }
                            deltaTone={
                                summary.weather.updated_at &&
                                summary.weather.stale
                                    ? 'crit'
                                    : 'neutral'
                            }
                            href="/environment"
                        />
                    )}
                    {permissions.view_reports && summary.last_report && (
                        <StatCard
                            label="Last report"
                            value={summary.last_report.report_number}
                            delta={summary.last_report.status}
                            href={`/reports/${summary.last_report.id}`}
                        />
                    )}
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    {showMap && (
                        <div className="rounded-[14px] border border-border bg-card p-4 xl:col-span-2">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-medium tracking-wide uppercase text-muted-foreground">
                                    Live zone map
                                </h2>
                                <Link
                                    href="/tracking"
                                    className="text-xs text-primary underline"
                                >
                                    Tracking
                                </Link>
                            </div>
                            <ZoneMap
                                zones={zones}
                                positions={positions}
                                occupancy={summary.headcount?.by_zone}
                            />
                        </div>
                    )}

                    <div className="space-y-4">
                        {permissions.view_tracking &&
                            summary.headcount?.by_zone && (
                                <div className="rounded-[14px] border border-border bg-card p-4">
                                    <h2 className="mb-3 text-sm font-medium tracking-wide uppercase text-muted-foreground">
                                        Zone headcount
                                    </h2>
                                    <ul className="space-y-2 text-sm">
                                        {summary.headcount.by_zone.map(
                                            (zone) => (
                                                <li
                                                    key={zone.zone_id}
                                                    className="flex justify-between gap-2"
                                                >
                                                    <span>{zone.zone_name}</span>
                                                    <span className="font-mono tabular-nums">
                                                        {zone.count}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                        {summary.headcount.by_zone.length ===
                                            0 && (
                                            <li className="text-muted-foreground">
                                                No on-site workers
                                            </li>
                                        )}
                                    </ul>
                                </div>
                            )}

                        {permissions.view_gas && summary.gas && (
                            <div className="rounded-[14px] border border-border bg-card p-4">
                                <h2 className="mb-3 text-sm font-medium tracking-wide uppercase text-muted-foreground">
                                    Gas / CO₂
                                </h2>
                                <ul className="space-y-2 text-sm">
                                    {summary.gas.panels.map((panel) => (
                                        <li
                                            key={panel.device_id}
                                            className="flex items-center justify-between gap-2"
                                        >
                                            <span>
                                                {panel.asset ??
                                                    `Device #${panel.device_id}`}
                                            </span>
                                            <span
                                                className={
                                                    panel.status === 'crit'
                                                        ? 'text-[color:var(--crit,#F0506E)]'
                                                        : panel.status ===
                                                            'warn'
                                                          ? 'text-[color:var(--warn,#F5A524)]'
                                                          : 'text-[color:var(--ok,#34D399)]'
                                                }
                                            >
                                                {panel.status.toUpperCase()}
                                                {panel.co2_ppm !== null
                                                    ? ` · CO₂ ${panel.co2_ppm}`
                                                    : ''}
                                            </span>
                                        </li>
                                    ))}
                                    {summary.gas.panels.length === 0 && (
                                        <li className="text-muted-foreground">
                                            No gas devices
                                        </li>
                                    )}
                                </ul>
                            </div>
                        )}

                        {summary.system_health && (
                            <div className="rounded-[14px] border border-border bg-card p-4">
                                <h2 className="mb-3 text-sm font-medium tracking-wide uppercase text-muted-foreground">
                                    System health
                                </h2>
                                <ul className="space-y-2 text-sm">
                                    {summary.system_health
                                        .slice(0, 8)
                                        .map((asset) => (
                                            <li
                                                key={asset.asset_id}
                                                className="flex justify-between gap-2"
                                            >
                                                <span>{asset.asset}</span>
                                                <span
                                                    className={
                                                        asset.status === 'red'
                                                            ? 'text-[color:var(--crit,#F0506E)]'
                                                            : asset.status ===
                                                                'amber'
                                                              ? 'text-[color:var(--warn,#F5A524)]'
                                                              : 'text-[color:var(--ok,#34D399)]'
                                                    }
                                                >
                                                    {asset.status}
                                                </span>
                                            </li>
                                        ))}
                                    {summary.system_health.length === 0 && (
                                        <li className="text-muted-foreground">
                                            No registered assets
                                        </li>
                                    )}
                                </ul>
                            </div>
                        )}

                        {summary.alerts && (
                            <div className="rounded-[14px] border border-border bg-card p-4">
                                <h2 className="mb-3 text-sm font-medium tracking-wide uppercase text-muted-foreground">
                                    Latest alerts
                                </h2>
                                <ul className="space-y-2 text-sm">
                                    {summary.alerts.latest.map((alert) => (
                                        <li key={alert.id}>
                                            <span
                                                className={
                                                    alert.severity ===
                                                    'critical'
                                                        ? 'text-[color:var(--crit,#F0506E)]'
                                                        : 'text-[color:var(--warn,#F5A524)]'
                                                }
                                            >
                                                [{alert.severity}]
                                            </span>{' '}
                                            {alert.title}
                                        </li>
                                    ))}
                                    {summary.alerts.latest.length === 0 && (
                                        <li className="text-muted-foreground">
                                            No open alerts
                                        </li>
                                    )}
                                </ul>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

DashboardIndex.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
