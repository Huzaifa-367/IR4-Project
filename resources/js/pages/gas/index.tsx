import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AnalyticalChart } from '@/components/ir4/analytical-chart';
import { CardHeading } from '@/components/ir4/card-heading';
import { GasChannelGauges } from '@/components/ir4/gas-channel-gauges';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { MetricRow } from '@/components/ir4/metric-row';
import { RangeToggle } from '@/components/ir4/range-toggle';
import { StatCard } from '@/components/ir4/stat-card';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import { gasInfo } from '@/lib/analytics-info';
import { buildTrendChartData, trendChartSeries } from '@/lib/trend-chart';
import { visitFilters } from '@/lib/visit-filters';
import { GasTypeLabels } from '@/types/enums';
import type { GasDashboardSnapshot, GasLivePanel, GasThreshold } from '@/types/gas';

const ALL_DEVICES = 'all';

type RangeValue = 'day' | 'week' | 'custom';

type Props = {
    snapshot: GasDashboardSnapshot;
    panels: GasLivePanel[];
    filters: {
        device_id: string;
        range: string;
        from: string;
        to: string;
    };
    devices: Array<{ id: number; name: string; reference: string }>;
    thresholds: Record<string, GasThreshold>;
    canManageThresholds: boolean;
    canAcknowledge: boolean;
};

const RANGE_OPTIONS = [
    { value: 'day' as const, label: '24h' },
    { value: 'week' as const, label: '7d' },
    { value: 'custom' as const, label: 'Custom' },
];

type ChannelStatus = 'ok' | 'warn' | 'crit';

function channelStatus(
    value: number | null,
    gasKey: string,
    thresholds: Record<string, GasThreshold>,
): ChannelStatus {
    if (value === null) {
        return 'ok';
    }

    const t = thresholds[gasKey];

    if (!t) {
        return 'ok';
    }

    if (t.direction === 'below') {
        if (value <= t.alarm_level) {
            return 'crit';
        }

        if (value <= t.warning_level) {
            return 'warn';
        }

        return 'ok';
    }

    if (value >= t.alarm_level) {
        return 'crit';
    }

    if (value >= t.warning_level) {
        return 'warn';
    }

    return 'ok';
}

function worstOf(a: ChannelStatus, b: ChannelStatus): ChannelStatus {
    if (a === 'crit' || b === 'crit') {
        return 'crit';
    }

    if (a === 'warn' || b === 'warn') {
        return 'warn';
    }

    return 'ok';
}

function panelGauges(
    panel: GasLivePanel,
    thresholds: Record<string, GasThreshold>,
) {
    const gauges = [];

    if (panel.lel_pct !== null) {
        gauges.push({
            label: 'LEL',
            source: '',
            value: panel.lel_pct,
            unit: '%',
            warn: thresholds.lel?.warning_level ?? null,
            alarm: thresholds.lel?.alarm_level ?? null,
            status: channelStatus(panel.lel_pct, 'lel', thresholds),
        });
    }

    if (panel.h2s_ppm !== null) {
        gauges.push({
            label: 'H₂S',
            source: '',
            value: panel.h2s_ppm,
            unit: 'ppm',
            warn: thresholds.h2s?.warning_level ?? null,
            alarm: thresholds.h2s?.alarm_level ?? null,
            status: channelStatus(panel.h2s_ppm, 'h2s', thresholds),
        });
    }

    if (panel.o2_pct !== null) {
        gauges.push({
            label: 'O₂',
            source: '',
            value: panel.o2_pct,
            unit: '%vol',
            warn: thresholds.o2_low?.warning_level ?? null,
            alarm: thresholds.o2_low?.alarm_level ?? null,
            status: worstOf(
                channelStatus(panel.o2_pct, 'o2_low', thresholds),
                channelStatus(panel.o2_pct, 'o2_high', thresholds),
            ),
        });
    }

    if (panel.co_ppm !== null) {
        gauges.push({
            label: 'CO',
            source: '',
            value: panel.co_ppm,
            unit: 'ppm',
            warn: thresholds.co?.warning_level ?? null,
            alarm: thresholds.co?.alarm_level ?? null,
            status: channelStatus(panel.co_ppm, 'co', thresholds),
        });
    }

    if (panel.co2_ppm !== null) {
        gauges.push({
            label: 'CO₂',
            source: '',
            value: panel.co2_ppm,
            unit: 'ppm',
            warn: thresholds.co2?.warning_level ?? null,
            alarm: thresholds.co2?.alarm_level ?? null,
            status: channelStatus(panel.co2_ppm, 'co2', thresholds),
        });
    }

    return gauges;
}

export default function GasDashboard({
    snapshot: initialSnapshot,
    panels: initialPanels,
    filters,
    devices,
    thresholds,
    canManageThresholds,
}: Props) {
    const [panels, setPanels] = useState(initialPanels);
    const [deviceId, setDeviceId] = usePropSyncedState(
        filters.device_id || ALL_DEVICES,
    );
    const [range, setRange] = usePropSyncedState<RangeValue>(
        (filters.range as RangeValue) || 'day',
    );
    const [from, setFrom] = usePropSyncedState(filters.from);
    const [to, setTo] = usePropSyncedState(filters.to);

    const { status } = useReverbChannel({
        channel: 'gas',
        events: ['.GasLiveUpdated'],
        onEvent: (payload: unknown) => {
            const p = payload as { panel: GasLivePanel };
            setPanels((prev) => {
                const next = prev.filter(
                    (x) => x.device_id !== p.panel.device_id,
                );

                return [...next, p.panel].sort((a, b) =>
                    a.device_name.localeCompare(b.device_name),
                );
            });
            router.reload({ only: ['snapshot'] });
        },
        snapshotUrl: '/gas/api/live',
        onSnapshot: (data) => {
            const json = data as { data: { panels: GasLivePanel[] } };
            setPanels(json.data.panels);
        },
        pollIntervalMs: 15_000,
    });

    const snapshot = initialSnapshot;
    const chartData = useMemo(
        () => buildTrendChartData(snapshot.trend.series, range),
        [snapshot.trend.series, range],
    );
    const chartSeries = useMemo(
        () => trendChartSeries(snapshot.trend.series),
        [snapshot.trend.series],
    );

    const metricByKey = new Map(
        snapshot.metrics.map((metric) => [metric.key, metric]),
    );
    const lel = metricByKey.get('lel');
    const h2s = metricByKey.get('h2s');
    const o2 = metricByKey.get('o2');

    const applyFilters = (
        patch: Partial<{
            device_id: string;
            range: string;
            from: string;
            to: string;
        }>,
    ): void => {
        const nextDevice = patch.device_id ?? deviceId;
        const nextRange = (patch.range ?? range) as RangeValue;

        visitFilters(
            '/gas',
            {
                device_id:
                    nextDevice === ALL_DEVICES ? undefined : nextDevice,
                range: nextRange,
                from: nextRange === 'custom' ? (patch.from ?? from) : undefined,
                to: nextRange === 'custom' ? (patch.to ?? to) : undefined,
            },
            { only: ['snapshot', 'filters'] },
        );
    };

    const applyRange = (nextRange: RangeValue): void => {
        setRange(nextRange);

        if (nextRange === 'custom') {
            return;
        }

        applyFilters({ range: nextRange });
    };

    const applyCustomRange = (): void => {
        applyFilters({ range: 'custom', from, to });
    };

    const deviceLabel =
        deviceId === ALL_DEVICES
            ? 'All devices'
            : (devices.find((d) => String(d.id) === deviceId)?.name ??
              'Selected device');

    return (
        <>
            <Head title="Gas" />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Control room</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            Gas
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            {panels.length} detectors ·{' '}
                            {snapshot.open_alarms} open alarms · {deviceLabel}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <LiveStatusPill status={status} />
                        <Button asChild size="sm" variant="secondary">
                            <Link href="/gas/alarms">Alarms</Link>
                        </Button>
                        {canManageThresholds ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href="/settings/gas-thresholds">
                                    Thresholds
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                {snapshot.open_alarms > 0 ? (
                    <div className="flex items-center gap-2 rounded-[var(--radius-sm)] border border-[color:var(--crit)]/40 bg-[color:var(--crit-bg)] px-4 py-3 text-sm text-[color:var(--crit)]">
                        {snapshot.open_alarms} open gas alarm
                        {snapshot.open_alarms === 1 ? '' : 's'} — check device
                        panels below.
                    </div>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="LEL"
                        value={
                            lel?.current === null || lel?.current === undefined
                                ? '—'
                                : `${lel.current}%`
                        }
                        delta={
                            lel !== undefined &&
                            lel.min !== null &&
                            lel.max !== null
                                ? `${lel.min}–${lel.max}%`
                                : 'No readings'
                        }
                        sparkline={lel?.sparkline}
                        info={gasInfo.lel}
                    />
                    <StatCard
                        label="H₂S"
                        value={
                            h2s?.current === null || h2s?.current === undefined
                                ? '—'
                                : `${h2s.current} ppm`
                        }
                        delta={
                            h2s !== undefined && h2s.max !== null
                                ? `${h2s.max} ppm range max`
                                : 'No readings'
                        }
                        sparkline={h2s?.sparkline}
                        info={gasInfo.h2s}
                    />
                    <StatCard
                        label="O₂"
                        value={
                            o2?.current === null || o2?.current === undefined
                                ? '—'
                                : `${o2.current}%vol`
                        }
                        delta={
                            o2?.avg != null
                                ? `${o2.avg}%vol range avg`
                                : 'No readings'
                        }
                        sparkline={o2?.sparkline}
                        info={gasInfo.o2}
                    />
                    <StatCard
                        label="Detector health"
                        value={`${snapshot.panel_health.current}/${snapshot.panel_health.total}`}
                        delta={`${snapshot.panel_health.stale} stale`}
                        deltaTone={
                            snapshot.panel_health.stale > 0 ? 'crit' : 'ok'
                        }
                        pulseCrit={snapshot.panel_health.stale > 0}
                        info={gasInfo.health}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)] xl:col-span-8">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="Gas Trend"
                                info={gasInfo.trend}
                                description={
                                    <>
                                        All channels · {snapshot.trend.source}{' '}
                                        data · {chartData.length} points
                                    </>
                                }
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <SearchableSelect
                                        value={deviceId}
                                        onValueChange={(value) => {
                                            setDeviceId(value);
                                            applyFilters({
                                                device_id: value,
                                            });
                                        }}
                                        placeholder="Device"
                                        triggerClassName="h-8 w-44"
                                        options={[
                                            {
                                                value: ALL_DEVICES,
                                                label: 'All devices',
                                            },
                                            ...devices.map((d) => ({
                                                value: String(d.id),
                                                label: d.name,
                                            })),
                                        ]}
                                    />
                                    <RangeToggle
                                        options={RANGE_OPTIONS}
                                        value={range}
                                        onChange={applyRange}
                                        aria-label="Trend duration"
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
                                </div>
                            </CardHeading>
                        </CardHeader>
                        <CardContent className="px-2 md:px-4">
                            <AnalyticalChart
                                data={chartData}
                                series={chartSeries}
                                height={320}
                                emptyLabel="No readings in this range"
                            />
                        </CardContent>
                    </Card>

                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)] xl:col-span-4">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="Range Statistics"
                                info={gasInfo.rangeStats}
                                description="Min, average, and max per channel"
                            />
                        </CardHeader>
                        <CardContent className="flex flex-col gap-5 px-4 md:px-5">
                            {snapshot.metrics.map((metric) => (
                                <div
                                    key={metric.key}
                                    className="flex flex-col gap-2"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-medium">
                                            {metric.label}
                                        </span>
                                        <span className="font-mono text-sm tabular-nums">
                                            {metric.current ?? '—'}{' '}
                                            {metric.current !== null
                                                ? metric.unit
                                                : ''}
                                        </span>
                                    </div>
                                    <MetricRow
                                        className="grid-cols-3"
                                        items={[
                                            {
                                                label: 'Min',
                                                value:
                                                    metric.min === null
                                                        ? '—'
                                                        : `${metric.min}${metric.unit}`,
                                            },
                                            {
                                                label: 'Average',
                                                value:
                                                    metric.avg === null
                                                        ? '—'
                                                        : `${metric.avg}${metric.unit}`,
                                            },
                                            {
                                                label: 'Max',
                                                value:
                                                    metric.max === null
                                                        ? '—'
                                                        : `${metric.max}${metric.unit}`,
                                            },
                                        ]}
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)]">
                    <CardHeader className="px-4 md:px-5">
                        <CardHeading
                            title="Live Detector Fleet"
                            info={gasInfo.fleet}
                            description="Latest readings per registered device"
                        />
                    </CardHeader>
                    <CardContent className="grid gap-3 px-4 md:grid-cols-2 md:px-5 xl:grid-cols-3">
                        {panels.map((panel) => (
                            <div
                                key={panel.device_id}
                                className="flex flex-col gap-4 rounded-[var(--radius-sm)] border border-border bg-surface-2 p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="truncate text-sm font-semibold">
                                            {panel.device_name}
                                        </h3>
                                        <p className="truncate font-mono text-[11px] text-text-faint">
                                            {panel.device_ref}
                                            {panel.asset_label
                                                ? ` · ${panel.asset_label}`
                                                : ''}
                                        </p>
                                    </div>
                                    <StatusPill
                                        label={
                                            panel.is_stale ? 'Stale' : 'Live'
                                        }
                                        tone={panel.is_stale ? 'warn' : 'ok'}
                                    />
                                </div>
                                <GasChannelGauges
                                    gauges={panelGauges(panel, thresholds)}
                                />
                                {panel.open_alarms.length > 0 ? (
                                    <ul className="space-y-1 text-xs text-[color:var(--crit)]">
                                        {panel.open_alarms.map((a) => (
                                            <li
                                                key={`${a.gas_type}-${a.level}`}
                                            >
                                                {GasTypeLabels[
                                                    a.gas_type as keyof typeof GasTypeLabels
                                                ] ?? a.gas_type}{' '}
                                                {a.level}
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                            </div>
                        ))}
                        {panels.length === 0 ? (
                            <div className="col-span-full py-10 text-center text-sm text-text-faint">
                                No gas or CO₂ detectors registered
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
