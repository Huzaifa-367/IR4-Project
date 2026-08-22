import { Head, Link } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';
import { AnalyticalChart } from '@/components/ir4/analytical-chart';
import { CardHeading } from '@/components/ir4/card-heading';
import { RangeToggle } from '@/components/ir4/range-toggle';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import { environmentInfo } from '@/lib/analytics-info';
import {
    ENVIRONMENT_METRICS,
    formatEnvironmentValue,
} from '@/lib/environment-metrics';
import { buildTrendChartData, trendChartSeries } from '@/lib/trend-chart';
import { visitFilters } from '@/lib/visit-filters';
import { dashboard } from '@/routes';
import environment from '@/routes/environment';
import settings from '@/routes/settings';
import type {
    EnvironmentCoreTrends,
    EnvironmentSensor,
} from '@/types/environment';

type RangeValue = 'day' | 'week' | 'custom';

type Props = {
    sensors: EnvironmentSensor[];
    trends: EnvironmentCoreTrends;
    filters: {
        range: string;
        from: string;
        to: string;
    };
};

const RANGE_OPTIONS = [
    { value: 'day' as const, label: '24h' },
    { value: 'week' as const, label: '7d' },
    { value: 'custom' as const, label: 'Custom' },
];

function mergeSensor(
    sensors: EnvironmentSensor[],
    incoming: EnvironmentSensor,
): EnvironmentSensor[] {
    return [
        ...sensors.filter((row) => row.device_id !== incoming.device_id),
        incoming,
    ].sort((a, b) => a.device_name.localeCompare(b.device_name));
}

export default function EnvironmentTrends({
    sensors: initialSensors,
    trends,
    filters,
}: Props) {
    const [sensors, setSensors] = usePropSyncedState(initialSensors);
    const [range, setRange] = usePropSyncedState<RangeValue>(
        (filters.range as RangeValue) || 'day',
    );
    const [from, setFrom] = usePropSyncedState(filters.from);
    const [to, setTo] = usePropSyncedState(filters.to);

    const live = sensors[0] ?? null;

    const onSnapshot = useCallback(
        (payload: unknown) => {
            const response = payload as {
                data: { sensors: EnvironmentSensor[] };
            };
            setSensors(response.data.sensors);
        },
        [setSensors],
    );

    useReverbChannel({
        channel: 'environment',
        events: ['.EnvironmentUpdated'],
        onEvent: (payload: unknown) => {
            setSensors((current) =>
                mergeSensor(
                    current,
                    (payload as { sensor: EnvironmentSensor }).sensor,
                ),
            );
        },
        snapshotUrl: environment.live.url(),
        onSnapshot,
        pollIntervalMs: 30_000,
        pollWhileLive: true,
    });

    const chartData = useMemo(
        () => buildTrendChartData(trends.series, range),
        [trends.series, range],
    );
    const chartSeries = useMemo(
        () => trendChartSeries(trends.series),
        [trends.series],
    );

    const applyFilters = (patch: {
        range?: RangeValue;
        from?: string;
        to?: string;
    }): void => {
        const nextRange = patch.range ?? range;

        visitFilters(
            environment.index.url(),
            {
                range: nextRange,
                from: nextRange === 'custom' ? (patch.from ?? from) : undefined,
                to: nextRange === 'custom' ? (patch.to ?? to) : undefined,
            },
            { only: ['sensors', 'trends', 'filters'] },
        );
    };

    const applyRange = (nextRange: RangeValue): void => {
        setRange(nextRange);

        if (nextRange !== 'custom') {
            applyFilters({ range: nextRange });
        }
    };

    return (
        <>
            <Head title="Environmental Conditions" />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Site conditions</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            Environmental Conditions
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            Ambient telemetry · display only · no environmental
                            alarms in v1
                        </p>
                    </div>
                    {live ? (
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusPill
                                label={live.is_stale ? 'Stale data' : 'Current'}
                                tone={live.is_stale ? 'warn' : 'ok'}
                            />
                            <span className="text-xs text-text-faint">
                                {live.recorded_at
                                    ? new Date(
                                          live.recorded_at,
                                      ).toLocaleString()
                                    : 'Waiting for first reading'}
                            </span>
                        </div>
                    ) : null}
                </div>

                {live ? (
                    <div className="grid gap-3 sm:grid-cols-3">
                        {ENVIRONMENT_METRICS.map((metric) => {
                            const Icon = metric.icon;

                            return (
                                <div
                                    key={metric.key}
                                    className="rounded-[var(--radius-sm)] border border-border bg-surface px-4 py-3 shadow-[var(--shadow-card)]"
                                >
                                    <div className="flex items-center gap-2 text-xs text-text-faint">
                                        <Icon className="size-3.5 text-[color:var(--accent)]" />
                                        {metric.label}
                                    </div>
                                    <p className="mt-1 font-display text-2xl font-semibold tabular-nums">
                                        {formatEnvironmentValue(
                                            live[metric.key],
                                            metric.unit,
                                        )}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <Card className="border-border bg-surface py-8 shadow-[var(--shadow-card)]">
                        <CardContent className="text-center text-sm text-text-faint">
                            No environmental readings yet. Configure a sensor or
                            weather API in{' '}
                            <Link
                                href={settings.general.edit.url()}
                                className="text-[color:var(--accent)] underline-offset-2 hover:underline"
                            >
                                settings
                            </Link>
                            .
                        </CardContent>
                    </Card>
                )}

                <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)]">
                    <CardHeader className="px-4 md:px-5">
                        <CardHeading
                            title="Trend"
                            info={environmentInfo.trend}
                            description={
                                <>
                                    Temperature · humidity · wind ·{' '}
                                    {trends.source === 'raw-hourly'
                                        ? 'hourly aggregates'
                                        : 'raw readings'}{' '}
                                    · {chartData.length} points
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
                                            onClick={() =>
                                                applyFilters({
                                                    range: 'custom',
                                                    from,
                                                    to,
                                                })
                                            }
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
            </div>
        </>
    );
}

EnvironmentTrends.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Environment', href: environment.index() },
    ],
};
