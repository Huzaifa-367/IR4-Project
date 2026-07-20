import { Head, router } from '@inertiajs/react';
import { CloudSun, Droplets, Radio, Wind } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AnalyticalChart } from '@/components/ir4/analytical-chart';
import { CardHeading } from '@/components/ir4/card-heading';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { MetricRow } from '@/components/ir4/metric-row';
import { RangeToggle } from '@/components/ir4/range-toggle';
import { StatCard } from '@/components/ir4/stat-card';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import { environmentInfo } from '@/lib/analytics-info';
import { dashboard } from '@/routes';
import type {
    EnvironmentDashboardSnapshot,
    EnvironmentSensor,
} from '@/types/environment';

type RangeValue = 'day' | 'week' | 'custom';

type Props = {
    snapshot: EnvironmentDashboardSnapshot;
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

export default function EnvironmentTrends({
    snapshot: initialSnapshot,
    filters,
}: Props) {
    const [liveSensors, setLiveSensors] = useState<EnvironmentSensor[] | null>(
        null,
    );
    const [range, setRange] = usePropSyncedState<RangeValue>(
        (filters.range as RangeValue) || 'day',
    );
    const [from, setFrom] = usePropSyncedState(filters.from);
    const [to, setTo] = usePropSyncedState(filters.to);

    const snapshot = {
        ...initialSnapshot,
        sensors: liveSensors ?? initialSnapshot.sensors,
    };

    const updateSensor = (sensor: EnvironmentSensor): void => {
        setLiveSensors((current) =>
            [
                ...(current ?? initialSnapshot.sensors).filter(
                    (item) => item.device_id !== sensor.device_id,
                ),
                sensor,
            ].sort((a, b) => a.device_name.localeCompare(b.device_name)),
        );
    };

    const { status } = useReverbChannel({
        channel: 'environment',
        events: ['.EnvironmentUpdated'],
        onEvent: (payload: unknown) => {
            const event = payload as { sensor: EnvironmentSensor };
            updateSensor(event.sensor);
            router.reload({
                only: ['snapshot'],
            });
        },
        snapshotUrl: '/api/environment/live',
        onSnapshot: (payload: unknown) => {
            const response = payload as {
                data: { sensors: EnvironmentSensor[] };
            };
            setLiveSensors(response.data.sensors);
        },
        pollIntervalMs: 30_000,
    });

    const chartData = useMemo(() => {
        const byTime = new Map<
            string,
            Record<string, string | number | null>
        >();
        snapshot.trend.series.forEach((metricSeries) => {
            metricSeries.points.forEach((point) => {
                const date = new Date(point.at);
                const existing = byTime.get(point.at) ?? {
                    at: point.at,
                    label:
                        range === 'week' || range === 'custom'
                            ? date.toLocaleDateString(undefined, {
                                  weekday: 'short',
                                  month: 'short',
                                  day: 'numeric',
                                  hour:
                                      range === 'custom'
                                          ? '2-digit'
                                          : undefined,
                                  minute:
                                      range === 'custom'
                                          ? '2-digit'
                                          : undefined,
                              })
                            : date.toLocaleTimeString(undefined, {
                                  hour: '2-digit',
                                  minute: '2-digit',
                              }),
                };
                existing[metricSeries.key] = point.avg ?? point.value;
                byTime.set(point.at, existing);
            });
        });

        return Array.from(byTime.values()).sort((a, b) =>
            String(a.at).localeCompare(String(b.at)),
        );
    }, [snapshot.trend.series, range]);

    const chartSeries = snapshot.trend.series.map((metric, index) => ({
        key: metric.key,
        label: metric.unit
            ? `${metric.label} (${metric.unit})`
            : metric.label,
        type: (index === 0 ? 'area' : 'line') as 'area' | 'line',
    }));

    const metricByKey = new Map(
        snapshot.metrics.map((metric) => [metric.key, metric]),
    );
    const temperature = metricByKey.get('temperature_c');
    const humidity = metricByKey.get('humidity_pct');
    const wind = metricByKey.get('wind_speed_ms');
    const freshSensors = snapshot.sensors.filter(
        (sensor) => !sensor.is_stale,
    ).length;

    const applyRange = (nextRange: RangeValue): void => {
        setRange(nextRange);

        if (nextRange === 'custom') {
            return;
        }

        router.get(
            '/environment',
            { range: nextRange },
            { only: ['snapshot', 'filters'], preserveState: true, replace: true },
        );
    };

    const applyCustomRange = (): void => {
        router.get(
            '/environment',
            { range: 'custom', from, to },
            { only: ['snapshot', 'filters'], preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Environmental Conditions" />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Control room</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            Environmental Conditions
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            Ambient telemetry · display only · no environmental
                            alarms in v1
                        </p>
                    </div>
                    <LiveStatusPill status={status} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Temperature"
                        value={
                            temperature?.current === null ||
                            temperature?.current === undefined
                                ? '—'
                                : `${temperature.current}°C`
                        }
                        delta={
                            temperature !== undefined &&
                            temperature.min !== null &&
                            temperature.max !== null
                                ? `${temperature.min}–${temperature.max}°C`
                                : 'No readings'
                        }
                        sparkline={temperature?.sparkline}
                        info={environmentInfo.temperature}
                    />
                    <StatCard
                        label="Humidity"
                        value={
                            humidity?.current === null ||
                            humidity?.current === undefined
                                ? '—'
                                : `${humidity.current}%`
                        }
                        delta={
                            humidity !== undefined && humidity.avg !== null
                                ? `${humidity.avg}% range avg`
                                : 'No readings'
                        }
                        sparkline={humidity?.sparkline}
                        info={environmentInfo.humidity}
                    />
                    <StatCard
                        label="Wind Speed"
                        value={
                            wind?.current === null ||
                            wind?.current === undefined
                                ? '—'
                                : `${wind.current} m/s`
                        }
                        delta={
                            wind !== undefined && wind.max !== null
                                ? `${wind.max} m/s range max`
                                : 'No readings'
                        }
                        sparkline={wind?.sparkline}
                        info={environmentInfo.wind}
                    />
                    <StatCard
                        label="Sensor Health"
                        value={`${freshSensors}/${snapshot.sensors.length}`}
                        delta={`${snapshot.sensors.filter((sensor) => sensor.is_stale).length} stale`}
                        deltaTone={
                            snapshot.sensors.some((sensor) => sensor.is_stale)
                                ? 'crit'
                                : 'ok'
                        }
                        pulseCrit={snapshot.sensors.some(
                            (sensor) => sensor.is_stale,
                        )}
                        info={environmentInfo.sensorHealth}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)] xl:col-span-8">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="Environmental Trend"
                                info={environmentInfo.trend}
                                description={
                                    <>
                                        All metrics · {snapshot.trend.source}{' '}
                                        data · {chartData.length} points
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
                                emptyLabel="No readings in this range"
                            />
                        </CardContent>
                    </Card>

                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)] xl:col-span-4">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="Range Statistics"
                                info={environmentInfo.rangeStats}
                                description="Minimum, average, and maximum"
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

                {snapshot.extra_metrics.length > 0 ? (
                    <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)]">
                        <CardHeader className="px-4 md:px-5">
                            <CardHeading
                                title="Air Quality & Additional Parameters"
                                info={environmentInfo.extra}
                                description="Dynamically reported sensor metrics"
                            />
                        </CardHeader>
                        <CardContent className="grid gap-3 px-4 sm:grid-cols-2 md:px-5 xl:grid-cols-4">
                            {snapshot.extra_metrics.map((metric) => (
                                <div
                                    key={metric.key}
                                    className="rounded-[var(--radius-sm)] border border-border bg-surface-2 p-4"
                                >
                                    <p className="eyebrow">{metric.label}</p>
                                    <p className="mt-1 font-display text-3xl font-semibold tabular-nums">
                                        {metric.current}
                                    </p>
                                    <p className="mt-1 text-xs text-text-faint">
                                        {metric.sensor_count} reporting sensor
                                        {metric.sensor_count === 1 ? '' : 's'}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ) : null}

                <Card className="gap-4 border-border bg-surface py-4 shadow-[var(--shadow-card)]">
                    <CardHeader className="px-4 md:px-5">
                        <CardHeading
                            title="Live Sensor Fleet"
                            info={environmentInfo.fleet}
                            description="Latest readings and data freshness per registered device"
                        />
                    </CardHeader>
                    <CardContent className="grid gap-3 px-4 md:grid-cols-2 md:px-5 xl:grid-cols-3">
                        {snapshot.sensors.map((sensor) => (
                            <div
                                key={sensor.device_id}
                                className="flex flex-col gap-4 rounded-[var(--radius-sm)] border border-border bg-surface-2 p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="truncate text-sm font-semibold">
                                            {sensor.device_name}
                                        </h3>
                                        <p className="truncate font-mono text-[11px] text-text-faint">
                                            {sensor.asset_label ??
                                                sensor.device_ref}
                                        </p>
                                    </div>
                                    <StatusPill
                                        label={
                                            sensor.is_stale
                                                ? 'Stale'
                                                : 'Current'
                                        }
                                        tone={
                                            sensor.is_stale ? 'warn' : 'ok'
                                        }
                                    />
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    <SensorMetric
                                        icon={CloudSun}
                                        label="Temp"
                                        value={sensor.temperature_c}
                                        unit="°C"
                                    />
                                    <SensorMetric
                                        icon={Droplets}
                                        label="Humidity"
                                        value={sensor.humidity_pct}
                                        unit="%"
                                    />
                                    <SensorMetric
                                        icon={Wind}
                                        label="Wind"
                                        value={sensor.wind_speed_ms}
                                        unit="m/s"
                                    />
                                </div>
                                <div className="flex items-center gap-2 text-xs text-text-faint">
                                    <Radio className="size-3.5" />
                                    <span>
                                        {sensor.recorded_at
                                            ? new Date(
                                                  sensor.recorded_at,
                                              ).toLocaleString()
                                            : 'Waiting for first reading'}
                                    </span>
                                </div>
                            </div>
                        ))}
                        {snapshot.sensors.length === 0 ? (
                            <div className="col-span-full py-10 text-center text-sm text-text-faint">
                                No environmental sensors registered
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function SensorMetric({
    icon: Icon,
    label,
    value,
    unit,
}: {
    icon: typeof CloudSun;
    label: string;
    value: number | null;
    unit: string;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1 rounded-md bg-surface-3 p-2">
            <Icon className="size-3.5 text-[color:var(--accent)]" />
            <span className="text-[10px] text-text-faint">{label}</span>
            <span className="truncate font-mono text-xs tabular-nums">
                {value ?? '—'}
                {value !== null ? ` ${unit}` : ''}
            </span>
        </div>
    );
}

EnvironmentTrends.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Environment', href: '/environment' },
    ],
};
