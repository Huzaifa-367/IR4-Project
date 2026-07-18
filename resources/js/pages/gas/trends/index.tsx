import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { AnalyticalChart } from '@/components/ir4/analytical-chart';
import { MetricRow } from '@/components/ir4/metric-row';
import { Panel } from '@/components/ir4/panel';
import { RangeToggle } from '@/components/ir4/range-toggle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { GasTrendSeries } from '@/types/gas';

type Props = {
    series: GasTrendSeries;
    filters: {
        gas_type: string;
        device_id: string;
        range: string;
        from: string;
        to: string;
    };
    devices: Array<{ id: number; name: string; reference: string }>;
    gasTypes: Array<{ value: string; label: string }>;
};

export default function GasTrends({
    series,
    filters,
    devices,
    gasTypes,
}: Props) {
    const [gasType, setGasType] = useState(filters.gas_type);
    const [deviceId, setDeviceId] = useState(filters.device_id);
    const [range, setRange] = useState(filters.range || 'day');
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function applyFilters(patch: Partial<Props['filters']>): void {
        router.get(
            '/gas/trends',
            {
                gas_type: patch.gas_type ?? gasType,
                device_id: patch.device_id ?? deviceId,
                range: patch.range ?? range,
                from: patch.from ?? from,
                to: patch.to ?? to,
            },
            { preserveState: true, replace: true },
        );
    }

    const chartData = useMemo(
        () =>
            series.points.map((point) => ({
                label: new Date(point.at).toLocaleString([], {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }),
                avg: point.avg ?? point.value,
                min: point.min,
                max: point.max,
            })),
        [series.points],
    );

    const values = series.points
        .map((p) => p.avg ?? p.value)
        .filter((v): v is number => v !== null);
    const latest = values.at(-1);
    const min = values.length > 0 ? Math.min(...values) : null;
    const max = values.length > 0 ? Math.max(...values) : null;

    return (
        <>
            <Head title="Gas trends" />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Gas Trends"
                        description={`Source: ${series.source} · ${series.points.length} points`}
                    />
                    <Button asChild size="sm" variant="secondary">
                        <Link href="/gas">Dashboard</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <select
                        value={gasType}
                        onChange={(event) => {
                            setGasType(event.target.value);
                            applyFilters({ gas_type: event.target.value });
                        }}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        {gasTypes.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                    <select
                        value={deviceId}
                        onChange={(event) => {
                            setDeviceId(event.target.value);
                            applyFilters({ device_id: event.target.value });
                        }}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All devices</option>
                        {devices.map((d) => (
                            <option key={d.id} value={String(d.id)}>
                                {d.name}
                            </option>
                        ))}
                    </select>
                    <RangeToggle
                        value={range}
                        onChange={(value) => {
                            setRange(value);
                            applyFilters({ range: value });
                        }}
                        options={[
                            { value: 'shift', label: 'Shift' },
                            { value: 'day', label: 'Day' },
                            { value: 'week', label: 'Week' },
                            { value: 'custom', label: 'Custom' },
                        ]}
                        aria-label="Trend range"
                    />
                    {range === 'custom' ? (
                        <>
                            <Input
                                type="date"
                                value={from}
                                onChange={(event) => {
                                    setFrom(event.target.value);
                                    applyFilters({ from: event.target.value });
                                }}
                            />
                            <Input
                                type="date"
                                value={to}
                                onChange={(event) => {
                                    setTo(event.target.value);
                                    applyFilters({ to: event.target.value });
                                }}
                            />
                        </>
                    ) : null}
                </div>

                <MetricRow
                    items={[
                        { label: 'Latest', value: latest?.toFixed(1) ?? '—' },
                        { label: 'Min', value: min?.toFixed(1) ?? '—' },
                        { label: 'Max', value: max?.toFixed(1) ?? '—' },
                    ]}
                />

                <Panel
                    title={
                        gasTypes.find((t) => t.value === gasType)?.label ??
                        'Reading'
                    }
                    subtitle="hover for exact values"
                >
                    <AnalyticalChart
                        data={chartData}
                        series={[
                            {
                                key: 'avg',
                                label: 'Average',
                                color: 'var(--viz-1)',
                                type: 'area',
                            },
                        ]}
                        height={280}
                        emptyLabel="No readings in range"
                    />
                </Panel>
            </div>
        </>
    );
}
