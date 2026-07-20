import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { AnalyticalChart } from '@/components/ir4/analytical-chart';
import { CardHeading } from '@/components/ir4/card-heading';
import { HorizontalBars } from '@/components/ir4/horizontal-bars';
import { MetricRow } from '@/components/ir4/metric-row';
import { RangeToggle } from '@/components/ir4/range-toggle';
import { StatCard } from '@/components/ir4/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import { ppeInfo } from '@/lib/analytics-info';
import { buildTrendChartData, trendChartSeries } from '@/lib/trend-chart';
import { visitFilters } from '@/lib/visit-filters';
import { ViolationTypeLabels } from '@/types/enums';
import type { PpeDashboardSnapshot } from '@/types/ppe';

type RangeValue = 'day' | 'week' | 'custom';

type Props = {
    snapshot: PpeDashboardSnapshot;
    filters: {
        range: string;
        from: string;
        to: string;
    };
    unreviewedCount: number;
    canExport: boolean;
};

const RANGE_OPTIONS = [
    { value: 'day' as const, label: '24h' },
    { value: 'week' as const, label: '7d' },
    { value: 'custom' as const, label: 'Custom' },
];

export default function PpeTrendsIndex({
    snapshot,
    filters,
    unreviewedCount,
    canExport,
}: Props) {
    const [range, setRange] = usePropSyncedState<RangeValue>(
        (filters.range as RangeValue) || 'day',
    );
    const [from, setFrom] = usePropSyncedState(filters.from);
    const [to, setTo] = usePropSyncedState(filters.to);

    const chartData = useMemo(
        () => buildTrendChartData(snapshot.trend.series, range),
        [snapshot.trend.series, range],
    );
    const chartSeries = useMemo(
        () => trendChartSeries(snapshot.trend.series),
        [snapshot.trend.series],
    );

    const byType = useMemo(
        () =>
            Object.entries(snapshot.by_type).map(([type, count]) => ({
                label:
                    ViolationTypeLabels[
                        type as keyof typeof ViolationTypeLabels
                    ] ?? type,
                value: count,
            })),
        [snapshot.by_type],
    );

    const byCamera = useMemo(
        () =>
            snapshot.by_camera.map((row) => ({
                label: row.camera_ref || `Camera #${row.camera_id}`,
                value: row.count,
            })),
        [snapshot.by_camera],
    );

    const applyRange = (nextRange: RangeValue): void => {
        setRange(nextRange);

        if (nextRange === 'custom') {
            return;
        }

        visitFilters(
            '/ppe',
            { range: nextRange },
            { only: ['snapshot', 'filters'] },
        );
    };

    const applyCustomRange = (): void => {
        visitFilters(
            '/ppe',
            { range: 'custom', from, to },
            { only: ['snapshot', 'filters'] },
        );
    };

    const violationMetric = snapshot.metrics[0];

    return (
        <>
            <Head title="PPE Trends" />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Control room</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            PPE Trends
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            Violation density, false-positive rate, and
                            per-camera breakdown
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="secondary" size="sm">
                            <Link href="/ppe/violations">Violations</Link>
                        </Button>
                        {canExport ? (
                            <>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        submitExport('csv', filters)
                                    }
                                >
                                    CSV
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        submitExport('pdf', filters)
                                    }
                                >
                                    PDF
                                </Button>
                            </>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Total violations"
                        value={snapshot.total}
                        delta={`${snapshot.excluded_false_positives} false positives excluded`}
                        sparkline={violationMetric?.sparkline}
                        info={ppeInfo.total}
                    />
                    <StatCard
                        label="Unreviewed"
                        value={snapshot.unreviewed_in_range}
                        delta={`${unreviewedCount} open overall`}
                        deltaTone={
                            snapshot.unreviewed_in_range > 0 ? 'crit' : 'ok'
                        }
                        info={ppeInfo.unreviewed}
                    />
                    <StatCard
                        label="False-positive rate"
                        value={`${(snapshot.false_positive_rate * 100).toFixed(1)}%`}
                        delta="within selected range"
                        info={ppeInfo.falsePositive}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)] xl:col-span-8">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="Violation Trend"
                                info={ppeInfo.trend}
                                description={
                                    <>
                                        {snapshot.trend.source} data ·{' '}
                                        {chartData.length} points
                                    </>
                                }
                            >
                                <div className="flex flex-wrap items-center gap-2">
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
                                emptyLabel="No violations in this range"
                            />
                        </CardContent>
                    </Card>

                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)] xl:col-span-4">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="Range Statistics"
                                info={ppeInfo.rangeStats}
                                description="Bucket min, average, and max"
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
                                            {metric.current ?? '—'}
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
                                                        : `${metric.min}`,
                                            },
                                            {
                                                label: 'Average',
                                                value:
                                                    metric.avg === null
                                                        ? '—'
                                                        : `${metric.avg}`,
                                            },
                                            {
                                                label: 'Max',
                                                value:
                                                    metric.max === null
                                                        ? '—'
                                                        : `${metric.max}`,
                                            },
                                        ]}
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)]">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="Violations by type"
                                info={ppeInfo.byType}
                                description={
                                    <>
                                        {snapshot.excluded_false_positives} false
                                        positives excluded
                                    </>
                                }
                            />
                        </CardHeader>
                        <CardContent className="px-4 md:px-5">
                            <HorizontalBars items={byType} />
                        </CardContent>
                    </Card>

                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)]">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="Violations by camera"
                                info={ppeInfo.byCamera}
                                description="this range"
                            />
                        </CardHeader>
                        <CardContent className="px-4 md:px-5">
                            <HorizontalBars items={byCamera} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function submitExport(
    format: 'csv' | 'pdf',
    filters: { from: string; to: string },
): void {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/ppe/violations/export';
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    form.innerHTML = `
        <input name="_token" value="${token ?? ''}" />
        <input name="format" value="${format}" />
        <input name="from" value="${filters.from}" />
        <input name="to" value="${filters.to}" />
    `;
    document.body.appendChild(form);
    form.submit();
    form.remove();
}
