import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { AnalyticalChart } from '@/components/ir4/analytical-chart';
import { CardHeading } from '@/components/ir4/card-heading';
import { MetricRow } from '@/components/ir4/metric-row';
import { RangeToggle } from '@/components/ir4/range-toggle';
import { StatCard } from '@/components/ir4/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import { gasInfo } from '@/lib/analytics-info';
import { buildTrendChartData, trendChartSeries } from '@/lib/trend-chart';
import type { GasDashboardSnapshot } from '@/types/gas';

const ALL_DEVICES = 'all';

type RangeValue = 'day' | 'week' | 'custom';

type Props = {
    snapshot: GasDashboardSnapshot;
    filters: {
        device_id: string;
        range: string;
        from: string;
        to: string;
    };
    devices: Array<{ id: number; name: string; reference: string }>;
};

const RANGE_OPTIONS = [
    { value: 'day' as const, label: '24h' },
    { value: 'week' as const, label: '7d' },
    { value: 'custom' as const, label: 'Custom' },
];

export default function GasTrends({ snapshot, filters, devices }: Props) {
    const [deviceId, setDeviceId] = usePropSyncedState(
        filters.device_id || ALL_DEVICES,
    );
    const [range, setRange] = usePropSyncedState<RangeValue>(
        (filters.range as RangeValue) || 'day',
    );
    const [from, setFrom] = usePropSyncedState(filters.from);
    const [to, setTo] = usePropSyncedState(filters.to);

    const chartData = useMemo(
        () => buildTrendChartData(snapshot.trend.series, range),
        [snapshot.trend.series, range],
    );
    const chartSeriesConfig = useMemo(
        () => trendChartSeries(snapshot.trend.series),
        [snapshot.trend.series],
    );

    const metricByKey = new Map(
        snapshot.metrics.map((metric) => [metric.key, metric]),
    );
    const lel = metricByKey.get('lel');
    const h2s = metricByKey.get('h2s');
    const o2 = metricByKey.get('o2');
    const co = metricByKey.get('co');
    const co2 = metricByKey.get('co2');

    const applyFilters = (patch: Partial<Props['filters']>): void => {
        router.get(
            '/gas/trends',
            {
                device_id:
                    (patch.device_id ?? deviceId) === ALL_DEVICES
                        ? undefined
                        : (patch.device_id ?? deviceId),
                range: patch.range ?? range,
                from: patch.from ?? from,
                to: patch.to ?? to,
            },
            {
                only: ['snapshot', 'filters'],
                preserveState: true,
                replace: true,
            },
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
            <Head title="Gas trends" />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Control room</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            Gas Trends
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            All channels · {snapshot.trend.source} data ·{' '}
                            {chartData.length} points · {deviceLabel}
                        </p>
                    </div>
                    <Button asChild size="sm" variant="secondary">
                        <Link href="/gas">Dashboard</Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard
                        label="LEL"
                        value={
                            lel?.current == null ? '—' : `${lel.current}%`
                        }
                        delta={
                            lel?.max != null
                                ? `${lel.max}% max`
                                : 'No readings'
                        }
                        sparkline={lel?.sparkline}
                        info={gasInfo.lel}
                    />
                    <StatCard
                        label="H₂S"
                        value={
                            h2s?.current == null
                                ? '—'
                                : `${h2s.current} ppm`
                        }
                        delta={
                            h2s?.max != null
                                ? `${h2s.max} ppm max`
                                : 'No readings'
                        }
                        sparkline={h2s?.sparkline}
                        info={gasInfo.h2s}
                    />
                    <StatCard
                        label="O₂"
                        value={
                            o2?.current == null ? '—' : `${o2.current}%vol`
                        }
                        delta={
                            o2?.avg != null
                                ? `${o2.avg}%vol avg`
                                : 'No readings'
                        }
                        sparkline={o2?.sparkline}
                        info={gasInfo.o2}
                    />
                    <StatCard
                        label="CO"
                        value={
                            co?.current == null ? '—' : `${co.current} ppm`
                        }
                        delta={
                            co?.max != null
                                ? `${co.max} ppm max`
                                : 'No readings'
                        }
                        sparkline={co?.sparkline}
                        info={gasInfo.co}
                    />
                    <StatCard
                        label="CO₂"
                        value={
                            co2?.current == null
                                ? '—'
                                : `${co2.current} ppm`
                        }
                        delta={
                            co2?.max != null
                                ? `${co2.max} ppm max`
                                : 'No readings'
                        }
                        sparkline={co2?.sparkline}
                        info={gasInfo.co2}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)] xl:col-span-8">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="All Gas Channels"
                                info={gasInfo.trend}
                                description="LEL, H₂S, O₂, CO, CO₂ on one chart"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <Select
                                        value={deviceId}
                                        onValueChange={(value) => {
                                            setDeviceId(value);
                                            applyFilters({
                                                device_id: value,
                                            });
                                        }}
                                    >
                                        <SelectTrigger className="h-8 w-44">
                                            <SelectValue placeholder="Device" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem
                                                    value={ALL_DEVICES}
                                                >
                                                    All devices
                                                </SelectItem>
                                                {devices.map((d) => (
                                                    <SelectItem
                                                        key={d.id}
                                                        value={String(d.id)}
                                                    >
                                                        {d.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
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
                                series={chartSeriesConfig}
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
            </div>
        </>
    );
}
