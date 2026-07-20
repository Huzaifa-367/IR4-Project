import { Head, Link, router } from '@inertiajs/react';
import { Download, Siren } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { AnalyticalChart } from '@/components/ir4/analytical-chart';
import { DonutChart, RadialGauge } from '@/components/ir4/donut-chart';
import { GasChannelGauges } from '@/components/ir4/gas-channel-gauges';
import { GeoZoneMapView } from '@/components/ir4/geo-zone-map';
import { HorizontalBars } from '@/components/ir4/horizontal-bars';
import { LiveFeed } from '@/components/ir4/live-feed';
import { MetricRow } from '@/components/ir4/metric-row';
import { MiniProgress } from '@/components/ir4/mini-progress';
import { Panel } from '@/components/ir4/panel';
import { PpeHeatmap } from '@/components/ir4/ppe-heatmap';
import { RangeToggle } from '@/components/ir4/range-toggle';
import { StatCard } from '@/components/ir4/stat-card';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import { dashboardInfo } from '@/lib/dashboard-info';
import { visitFilters } from '@/lib/visit-filters';
import { dashboard } from '@/routes';
import type {
    DashboardPermissions,
    DashboardRange,
    DashboardSummary,
} from '@/types/dashboard';
import { systemHealthAssets } from '@/types/dashboard';
import type { TrackingZone } from '@/types/tracking';

type Props = {
    summary: DashboardSummary;
    permissions: DashboardPermissions;
    filters?: {
        range: string;
        from: string;
        to: string;
    };
};

const RANGE_OPTIONS = [
    { value: 'today' as const, label: 'Today' },
    { value: 'yesterday' as const, label: 'Yesterday' },
    { value: 'week' as const, label: '7d' },
    { value: 'custom' as const, label: 'Custom' },
];

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

function formatClock(iso?: string): string {
    const d = iso ? new Date(iso) : new Date();

    return d.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

export default function DashboardIndex({
    summary: initial,
    permissions,
    filters,
}: Props) {
    const [defaultDay] = useState(() => new Date().toISOString().slice(0, 10));
    const [summary, setSummary] = usePropSyncedState(initial);
    const [range, setRange] = usePropSyncedState<DashboardRange>(
        (filters?.range as DashboardRange) || 'today',
    );
    const [from, setFrom] = usePropSyncedState(filters?.from ?? defaultDay);
    const [to, setTo] = usePropSyncedState(filters?.to ?? defaultDay);
    const [alertFilter, setAlertFilter] = useState<'all' | 'crit'>('all');
    const [clock, setClock] = useState(() => formatClock(initial.meta?.as_of));
    const { can } = usePermissions();

    const onSnapshot = useCallback((payload: unknown) => {
        setSummary(unwrapSummary(payload));
    }, [setSummary]);

    const summaryQuery = useCallback(
        (nextRange: DashboardRange = range, nextFrom = from, nextTo = to) => {
            const params = new URLSearchParams({ range: nextRange });

            if (nextRange === 'custom') {
                params.set('from', nextFrom);
                params.set('to', nextTo);
            }

            return `/api/dashboard/summary?${params.toString()}`;
        },
        [range, from, to],
    );

    const fetchSummary = useCallback(
        (nextRange: DashboardRange = range, nextFrom = from, nextTo = to) => {
            void fetch(summaryQuery(nextRange, nextFrom, nextTo), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((r) => r.json())
                .then(onSnapshot);
        },
        [summaryQuery, range, from, to, onSnapshot],
    );

    useReverbChannel({
        channel: 'alerts',
        events: ['.alert.raised', '.alert.updated'],
        onEvent: () => fetchSummary(),
        snapshotUrl: summaryQuery(),
        onSnapshot,
        pollIntervalMs: 60_000,
    });

    useReverbChannel({
        channel: 'tracking',
        events: ['.headcount.updated', '.positions.updated'],
        onEvent: () => fetchSummary(),
        pollIntervalMs: 60_000,
    });

    useReverbChannel({
        channel: 'gas',
        events: ['.gas.reading', '.gas.alarm'],
        onEvent: () => fetchSummary(),
        pollIntervalMs: 60_000,
    });

    useEffect(() => {
        const id = window.setInterval(() => {
            setClock(formatClock());
        }, 1000);

        return () => window.clearInterval(id);
    }, []);

    const applyRange = (nextRange: DashboardRange): void => {
        setRange(nextRange);

        if (nextRange === 'custom') {
            return;
        }

        visitFilters(
            '/dashboard',
            { range: nextRange },
            { only: ['summary', 'filters'] },
        );
    };

    const applyCustomRange = (): void => {
        visitFilters(
            '/dashboard',
            { range: 'custom', from, to },
            { only: ['summary', 'filters'] },
        );
    };

    const rangeLabel =
        summary.meta?.range_label ?? 'Today';
    const zones = (summary.map?.zones ?? []) as TrackingZone[];
    const showMap = permissions.view_tracking && can('view-tracking');
    const showGas = permissions.view_gas && can('view-gas');
    const healthAssets = systemHealthAssets(summary.system_health);
    const healthMeta = Array.isArray(summary.system_health)
        ? null
        : summary.system_health;

    const gasChartData = useMemo(() => {
        const labels = summary.gas?.trend?.labels ?? [];

        return labels.map((row) => row);
    }, [summary.gas?.trend?.labels]);

    const gasSeries = useMemo(
        () =>
            (summary.gas?.trend?.series ?? []).map((s, i) => ({
                key: s.key,
                label: s.label,
                color: s.color ?? (i === 0 ? 'var(--viz-1)' : 'var(--viz-6)'),
                type: (i === 0 ? 'area' : 'line') as 'area' | 'line',
            })),
        [summary.gas?.trend?.series],
    );

    const headcountFlowData = useMemo(
        () =>
            (summary.headcount?.flow ?? []).map((p) => ({
                label: p.label,
                on_site: p.on_site,
                entries: p.entries,
                exits: p.exits,
            })),
        [summary.headcount?.flow],
    );

    const zoneDonut = useMemo(
        () =>
            (summary.headcount?.by_zone ?? [])
                .filter((z) => z.count > 0)
                .map((z) => ({ label: z.zone_name, value: z.count })),
        [summary.headcount?.by_zone],
    );

    const alertItems = useMemo(() => {
        const latest = summary.alerts?.latest ?? [];
        const filtered =
            alertFilter === 'crit'
                ? latest.filter((a) => a.severity === 'critical')
                : latest;

        return filtered.map((alert) => {
            const payload = alert.payload ?? {};
            const metaParts = [
                typeof payload.asset === 'string' ? payload.asset : null,
                typeof payload.zone_name === 'string'
                    ? payload.zone_name
                    : null,
                typeof payload.device_name === 'string'
                    ? payload.device_name
                    : null,
                alert.alert_type_label,
            ].filter(Boolean);

            return {
                id: alert.id,
                title: alert.title,
                severity: alert.severity,
                meta: metaParts.join(' · ') || alert.status,
                raisedAt: alert.raised_at,
                href: '/alerts',
            };
        });
    }, [summary.alerts?.latest, alertFilter]);

    const deltaManpower = summary.headcount?.delta_vs_range_start ?? 0;
    const openRecords = summary.open_records ?? [];
    const canEvacuate =
        permissions.trigger_evacuation || can('trigger-evacuation');

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Control room</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            Site Safety Analytics
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            {rangeLabel} · live as of{' '}
                            <span className="font-mono tabular-nums">
                                {clock}
                            </span>
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <RangeToggle
                            options={RANGE_OPTIONS}
                            value={range}
                            onChange={applyRange}
                            aria-label="Dashboard range"
                        />
                        {range === 'custom' ? (
                            <>
                                <Input
                                    type="date"
                                    value={from}
                                    onChange={(event) =>
                                        setFrom(event.target.value)
                                    }
                                    className="h-8 w-[9.5rem]"
                                    aria-label="From date"
                                />
                                <Input
                                    type="date"
                                    value={to}
                                    onChange={(event) =>
                                        setTo(event.target.value)
                                    }
                                    className="h-8 w-[9.5rem]"
                                    aria-label="To date"
                                />
                                <Button
                                    size="sm"
                                    className="h-8"
                                    onClick={applyCustomRange}
                                >
                                    Apply
                                </Button>
                            </>
                        ) : null}
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/display">
                                <Download className="size-3.5" />
                                Display
                            </Link>
                        </Button>
                        {canEvacuate ? (
                            <Button
                                size="sm"
                                className="bg-[color:var(--crit)] text-white hover:bg-[color:var(--crit)]/90"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Trigger site evacuation? This opens a live muster report.',
                                        )
                                    ) {
                                        router.post('/tracking/evacuation');
                                    }
                                }}
                            >
                                <Siren className="size-3.5" />
                                Evacuate
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 [&>*]:min-w-0">
                    {summary.headcount ? (
                        <StatCard
                            label="Total Manpower"
                            value={summary.headcount.total_on_site}
                            href={showMap ? '/tracking' : undefined}
                            info={dashboardInfo.manpower}
                            delta={`${deltaManpower >= 0 ? '▲ +' : '▼ '}${Math.abs(deltaManpower)} vs range start`}
                            deltaTone={deltaManpower >= 0 ? 'ok' : 'neutral'}
                            sparkline={summary.headcount.sparkline}
                        />
                    ) : null}
                    {summary.alerts ? (
                        <StatCard
                            label="Open Alerts"
                            value={summary.alerts.open_critical}
                            href="/alerts"
                            info={dashboardInfo.alerts}
                            pulseCrit={summary.alerts.open_critical > 0}
                            sparkline={summary.alerts.sparkline}
                            deltaTone={
                                summary.alerts.open_critical > 0
                                    ? 'crit'
                                    : 'neutral'
                            }
                        >
                            <div className="mt-1 flex flex-wrap gap-1.5">
                                <StatusPill
                                    label={`${summary.alerts.open_critical} Critical`}
                                    tone="crit"
                                />
                                <StatusPill
                                    label={`${summary.alerts.open_warning} Warning`}
                                    tone="warn"
                                />
                            </div>
                        </StatCard>
                    ) : null}
                    {permissions.view_ppe && summary.ppe_today ? (
                        <StatCard
                            label="PPE Compliance"
                            value={`${summary.ppe_today.compliance_pct ?? '—'}%`}
                            href="/ppe/violations"
                            info={dashboardInfo.ppeCompliance}
                            delta={`${(summary.ppe_today.compliance_delta ?? 0) >= 0 ? '▲ +' : '▼ '}${Math.abs(summary.ppe_today.compliance_delta ?? 0)}% vs prior period`}
                            deltaTone={
                                (summary.ppe_today.compliance_delta ?? 0) >= 0
                                    ? 'ok'
                                    : 'crit'
                            }
                            sparkline={summary.ppe_today.sparkline}
                        />
                    ) : null}
                    {healthMeta || healthAssets.length > 0 ? (
                        <StatCard
                            label="System Health"
                            value={`${healthMeta?.uptime_pct ?? (healthAssets.length ? Math.round((healthAssets.filter((a) => a.status === 'green').length / healthAssets.length) * 1000) / 10 : 100)}%`}
                            info={dashboardInfo.systemHealth}
                            delta={`${healthMeta?.online ?? healthAssets.filter((a) => a.status === 'green').length}/${healthMeta?.total ?? healthAssets.length} online`}
                            deltaTone="ok"
                            sparkline={healthMeta?.sparkline}
                        />
                    ) : null}
                </div>

                <section className="flex flex-col gap-4">
                    <div>
                        <p className="eyebrow">Live operations</p>
                        <h2 className="text-sm font-semibold text-text">
                            Presence & alerts
                        </h2>
                    </div>
                <div className="grid gap-4 xl:grid-cols-12">
                    {showMap ? (
                        <Panel
                            title="Live Zone Map"
                            subtitle="RFID zone-level presence · live"
                            info={dashboardInfo.zoneMap}
                            className="xl:col-span-8"
                            action={
                                <Link
                                    href="/tracking"
                                    className="text-xs text-[color:var(--accent)] hover:underline"
                                >
                                    Tracking ›
                                </Link>
                            }
                        >
                            <div className="relative">
                                <GeoZoneMapView
                                    zones={zones}
                                    occupancy={summary.headcount?.by_zone}
                                    onSelect={(zone) =>
                                        router.visit(
                                            `/settings/zones/${zone.id}`,
                                        )
                                    }
                                />
                                <div className="mt-3 grid grid-cols-3 gap-3 rounded-[var(--radius-sm)] border border-border bg-surface-2 px-3 py-2">
                                    <div>
                                        <p className="eyebrow">On Site</p>
                                        <p className="font-mono text-lg tabular-nums">
                                            {summary.headcount?.total_on_site ??
                                                0}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="eyebrow">Zones</p>
                                        <p className="font-mono text-lg tabular-nums">
                                            {summary.map?.zone_count ??
                                                zones.length}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="eyebrow">In Red</p>
                                        <p className="font-mono text-lg text-[color:var(--crit)] tabular-nums">
                                            {summary.map?.in_red ?? 0}
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-2 flex flex-wrap gap-3 text-[11px] text-text-dim">
                                    <span>
                                        <i className="mr-1 inline-block size-2 rounded-full bg-[color:var(--accent)]" />
                                        Work
                                    </span>
                                    <span>
                                        <i className="mr-1 inline-block size-2 rounded-full bg-[color:var(--warn)]" />
                                        Height
                                    </span>
                                    <span>
                                        <i className="mr-1 inline-block size-2 rounded-full bg-[color:var(--crit)]" />
                                        Restricted
                                    </span>
                                    <span>
                                        <i className="mr-1 inline-block size-2 rounded-full bg-[color:var(--ok)]" />
                                        Muster
                                    </span>
                                </div>
                            </div>
                        </Panel>
                    ) : null}

                    <Panel
                        title="Alert Feed"
                        subtitle="Live · newest first"
                        info={dashboardInfo.alertFeed}
                        className={showMap ? 'xl:col-span-4' : 'xl:col-span-12'}
                        action={
                            <RangeToggle
                                value={alertFilter}
                                onChange={setAlertFilter}
                                options={[
                                    { value: 'all', label: 'All' },
                                    { value: 'crit', label: 'Crit' },
                                ]}
                                aria-label="Alert severity filter"
                            />
                        }
                    >
                        <LiveFeed items={alertItems} />
                    </Panel>
                </div>
                </section>

                {(showGas || summary.gas) && (
                    <section className="flex flex-col gap-4">
                        <div>
                            <p className="eyebrow">Gas & atmosphere</p>
                            <h2 className="text-sm font-semibold text-text">
                                Trends for {rangeLabel}
                            </h2>
                        </div>
                    <div className="grid gap-4 xl:grid-cols-12">
                        {showGas ? (
                            <Panel
                                title="Gas Trend — all channels"
                                subtitle={`${rangeLabel} · LEL · H₂S · O₂ · CO · CO₂`}
                                info={dashboardInfo.gasTrend}
                                className="xl:col-span-8"
                            >
                                <div className="mb-2 flex flex-wrap gap-3 text-xs text-text-dim">
                                    {(summary.gas?.trend?.series ?? []).map(
                                        (s) => (
                                            <span
                                                key={s.key}
                                                className="inline-flex items-center gap-1.5"
                                            >
                                                <i
                                                    className="size-2 rounded-full"
                                                    style={{
                                                        background:
                                                            s.color ??
                                                            'var(--viz-1)',
                                                    }}
                                                />
                                                {s.label}{' '}
                                                <b className="font-mono text-text">
                                                    {s.latest !== null &&
                                                    s.latest !== undefined
                                                        ? Number(
                                                              s.latest,
                                                          ).toFixed(1)
                                                        : '—'}
                                                </b>
                                            </span>
                                        ),
                                    )}
                                </div>
                                <AnalyticalChart
                                    data={gasChartData}
                                    series={gasSeries}
                                    height={250}
                                    emptyLabel="No gas readings in this range"
                                />
                            </Panel>
                        ) : null}
                        <Panel
                            title="Live Gas Panels"
                            subtitle={`${summary.gas?.channel_gauges?.length ?? 0} channels · live vs thresholds`}
                            info={dashboardInfo.gasPanels}
                            className={
                                showGas ? 'xl:col-span-4' : 'xl:col-span-12'
                            }
                        >
                            <GasChannelGauges
                                gauges={summary.gas?.channel_gauges ?? []}
                            />
                        </Panel>
                    </div>
                    </section>
                )}

                <section className="flex flex-col gap-4">
                    <div>
                        <p className="eyebrow">Compliance</p>
                        <h2 className="text-sm font-semibold text-text">
                            Safety score, PPE & LSR · {rangeLabel}
                        </h2>
                    </div>
                <div className="grid gap-4 xl:grid-cols-12">
                    {summary.safety_score ? (
                        <Panel
                            title="Site Safety Score"
                            subtitle="composite"
                            info={dashboardInfo.safetyScore}
                            className="xl:col-span-4"
                        >
                            <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                                <RadialGauge
                                    value={summary.safety_score.score}
                                    max={100}
                                    label="Safety"
                                    sublabel="of 100"
                                />
                                <div className="w-full flex-1 space-y-2">
                                    {(
                                        [
                                            [
                                                'PPE',
                                                summary.safety_score.components
                                                    .ppe,
                                                'ok',
                                            ],
                                            [
                                                'Zone',
                                                summary.safety_score.components
                                                    .zone,
                                                'ok',
                                            ],
                                            [
                                                'Equip',
                                                summary.safety_score.components
                                                    .equipment,
                                                'warn',
                                            ],
                                        ] as const
                                    ).map(([label, value, tone]) => (
                                        <div
                                            key={label}
                                            className="grid grid-cols-[48px_1fr_40px] items-center gap-2 text-xs"
                                        >
                                            <span className="text-text-dim">
                                                {label}
                                            </span>
                                            <MiniProgress
                                                value={value}
                                                max={100}
                                                tone={tone}
                                            />
                                            <span className="text-right font-mono tabular-nums">
                                                {value}%
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="mt-4">
                                <MetricRow
                                    items={[
                                        {
                                            label: 'PPE Today',
                                            value: summary.safety_score
                                                .ppe_today,
                                        },
                                        {
                                            label: 'Open LSR',
                                            value: summary.safety_score
                                                .open_lsr,
                                        },
                                        {
                                            label: 'Overdue Eq.',
                                            value: summary.safety_score
                                                .overdue_equipment,
                                            deltaTone:
                                                summary.safety_score
                                                    .overdue_equipment > 0
                                                    ? 'crit'
                                                    : 'ok',
                                        },
                                    ]}
                                />
                            </div>
                        </Panel>
                    ) : null}

                    {permissions.view_ppe && summary.ppe_today?.heatmap ? (
                        <Panel
                            title="PPE Violations by Hour"
                            subtitle={`density heatmap · ${rangeLabel}`}
                            info={dashboardInfo.ppeHeatmap}
                            className="xl:col-span-4"
                        >
                            <PpeHeatmap
                                types={summary.ppe_today.heatmap.types}
                                hours={summary.ppe_today.heatmap.hours}
                                cells={summary.ppe_today.heatmap.cells}
                            />
                        </Panel>
                    ) : null}

                    {permissions.view_lsr && summary.lsr ? (
                        <Panel
                            title="LSR by Category"
                            subtitle={rangeLabel}
                            info={dashboardInfo.lsrCategory}
                            className="xl:col-span-4"
                        >
                            <HorizontalBars
                                items={summary.lsr.by_category.map((row) => ({
                                    label: row.label,
                                    value: row.total ?? row.open,
                                }))}
                                emptyLabel="No LSR in this range"
                            />
                        </Panel>
                    ) : null}
                </div>
                </section>

                <section className="flex flex-col gap-4">
                    <div>
                        <p className="eyebrow">Workforce</p>
                        <h2 className="text-sm font-semibold text-text">
                            Headcount & readiness
                        </h2>
                    </div>
                <div className="grid gap-4 xl:grid-cols-12">
                    {showMap && headcountFlowData.length > 0 ? (
                        <Panel
                            title="Headcount & Flow"
                            subtitle={`${rangeLabel} · peak ${summary.headcount?.peak ?? '—'}`}
                            info={dashboardInfo.headcountFlow}
                            className="xl:col-span-5"
                        >
                            <AnalyticalChart
                                data={headcountFlowData}
                                series={[
                                    {
                                        key: 'on_site',
                                        label: 'On site',
                                        color: 'var(--viz-3)',
                                        type: 'area',
                                    },
                                ]}
                                height={220}
                            />
                        </Panel>
                    ) : null}

                    {showMap && zoneDonut.length > 0 ? (
                        <Panel
                            title="Workers by Zone"
                            subtitle={`live distribution · ${summary.headcount?.total_on_site ?? 0} on site`}
                            info={dashboardInfo.workersByZone}
                            className="xl:col-span-4"
                        >
                            <DonutChart
                                data={zoneDonut}
                                centerLabel="On site"
                                height={160}
                            />
                        </Panel>
                    ) : null}

                    {showMap && summary.evacuation ? (
                        <Panel
                            title="Evacuation Readiness"
                            subtitle="last drill accounting"
                            info={dashboardInfo.evacuation}
                            className="xl:col-span-3"
                        >
                            <div className="flex justify-center">
                                <RadialGauge
                                    value={summary.evacuation.accounted}
                                    max={Math.max(1, summary.evacuation.total)}
                                    label="Accounted"
                                    sublabel={
                                        summary.evacuation.total > 0
                                            ? `${summary.evacuation.accounted}/${summary.evacuation.total}`
                                            : 'No drills yet'
                                    }
                                />
                            </div>
                            <MetricRow
                                className="mt-3 sm:grid-cols-2"
                                items={[
                                    {
                                        label: 'Muster reader',
                                        value: summary.evacuation.muster_reader,
                                    },
                                    {
                                        label: 'Gate exit',
                                        value: summary.evacuation.gate_exit,
                                    },
                                ]}
                            />
                        </Panel>
                    ) : null}
                </div>
                </section>

                {openRecords.length > 0 ? (
                    <Panel
                        title="Open Incidents & LSR"
                        subtitle={`${openRecords.length} open · mandatory action-taken to close`}
                        info={dashboardInfo.openRecords}
                        action={
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/incidents">View all ›</Link>
                            </Button>
                        }
                    >
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[880px] text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border text-[11px] tracking-wide text-text-faint uppercase">
                                        <th className="px-2 py-2 font-medium">
                                            Record
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Type
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Severity
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Zone
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Owner
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Action progress
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Age
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {openRecords.map((row) => (
                                        <tr
                                            key={`${row.kind}-${row.id}`}
                                            className="border-b border-border/60"
                                        >
                                            <td className="px-2 py-2.5">
                                                <Link
                                                    href={row.href}
                                                    className="font-mono text-[12px] text-[color:var(--accent)] hover:underline"
                                                >
                                                    {row.record}
                                                </Link>
                                            </td>
                                            <td className="px-2 py-2.5 text-text-dim">
                                                {row.type}
                                            </td>
                                            <td className="px-2 py-2.5">
                                                <StatusPill
                                                    label={row.severity_label}
                                                    tone={
                                                        row.severity.includes(
                                                            'crit',
                                                        ) ||
                                                        row.severity === 'high'
                                                            ? 'crit'
                                                            : 'warn'
                                                    }
                                                />
                                            </td>
                                            <td className="px-2 py-2.5 text-text-dim">
                                                {row.zone}
                                            </td>
                                            <td className="px-2 py-2.5">
                                                <span className="inline-flex items-center gap-2 text-text-dim">
                                                    <span className="flex size-6 items-center justify-center rounded-full bg-surface-3 text-[10px] font-semibold text-text">
                                                        {row.owner_initials}
                                                    </span>
                                                    {row.owner}
                                                </span>
                                            </td>
                                            <td className="px-2 py-2.5">
                                                <StatusPill
                                                    label={row.status_label}
                                                    tone="info"
                                                    showDot={false}
                                                />
                                            </td>
                                            <td className="px-2 py-2.5">
                                                <div className="flex items-center gap-2">
                                                    <MiniProgress
                                                        value={
                                                            row.action_progress
                                                        }
                                                        max={100}
                                                        tone={
                                                            row.action_progress >=
                                                            100
                                                                ? 'ok'
                                                                : row.action_progress >=
                                                                    50
                                                                  ? 'accent'
                                                                  : 'warn'
                                                        }
                                                        className="min-w-[80px]"
                                                    />
                                                    <span className="font-mono text-[11px] text-text-faint">
                                                        {row.action_progress}%
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-2 py-2.5 font-mono text-[12px] text-text-faint tabular-nums">
                                                {row.age}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                ) : null}
            </div>
        </>
    );
}

DashboardIndex.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
