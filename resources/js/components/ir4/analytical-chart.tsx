import {
    Area,
    AreaChart,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { cn } from '@/lib/utils';

export type ChartSeries = {
    key: string;
    label: string;
    color?: string;
    type?: 'area' | 'line';
};

type Props = {
    data: Array<Record<string, string | number | null>>;
    series: ChartSeries[];
    xKey?: string;
    className?: string;
    height?: number;
    emptyLabel?: string;
    thresholdWarn?: number;
    thresholdCrit?: number;
};

const VIZ = [
    'var(--viz-1)',
    'var(--viz-2)',
    'var(--viz-3)',
    'var(--viz-4)',
    'var(--viz-5)',
    'var(--viz-6)',
];

function ChartTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: Array<{ name?: string; value?: number; color?: string }>;
    label?: string | number;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="rounded-[var(--radius-sm)] border border-border bg-surface-2 px-3 py-2 shadow-[var(--shadow-pop)]">
            <p className="mb-1 font-mono text-[11px] text-text-faint">{label}</p>
            <ul className="space-y-0.5">
                {payload.map((entry) => (
                    <li
                        key={String(entry.name)}
                        className="flex items-center gap-2 text-xs"
                    >
                        <span
                            className="size-1.5 rounded-full"
                            style={{ background: entry.color }}
                        />
                        <span className="text-text-dim">{entry.name}</span>
                        <span className="ml-auto font-mono tabular-nums text-text">
                            {entry.value ?? '—'}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function AnalyticalChart({
    data,
    series,
    xKey = 'label',
    className,
    height = 250,
    emptyLabel = 'No data for this range',
    thresholdWarn,
    thresholdCrit,
}: Props) {
    if (data.length === 0 || series.length === 0) {
        return (
            <div
                className={cn(
                    'flex items-center justify-center text-sm text-text-faint',
                    className,
                )}
                style={{ height }}
            >
                {emptyLabel}
            </div>
        );
    }

    const useArea = series.some((s) => (s.type ?? 'area') === 'area');
    const Chart = useArea ? AreaChart : LineChart;

    return (
        <div className={cn('w-full', className)} style={{ height }}>
            <ResponsiveContainer width="100%" height="100%">
                <Chart
                    data={data}
                    margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                >
                    <CartesianGrid
                        stroke="var(--border)"
                        strokeDasharray="3 3"
                        vertical={false}
                    />
                    <XAxis
                        dataKey={xKey}
                        tick={{ fill: 'var(--text-faint)', fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                    />
                    <YAxis
                        tick={{ fill: 'var(--text-faint)', fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                        width={36}
                    />
                    <Tooltip
                        content={<ChartTooltip />}
                        cursor={{
                            stroke: 'var(--text-faint)',
                            strokeDasharray: '4 4',
                        }}
                    />
                    {series.length > 1 ? (
                        <Legend
                            wrapperStyle={{
                                fontSize: 12,
                                color: 'var(--text-dim)',
                            }}
                        />
                    ) : null}
                    {thresholdWarn !== undefined ? (
                        <ReferenceLine
                            y={thresholdWarn}
                            stroke="var(--warn)"
                            strokeDasharray="5 4"
                            strokeWidth={1.5}
                        />
                    ) : null}
                    {thresholdCrit !== undefined ? (
                        <ReferenceLine
                            y={thresholdCrit}
                            stroke="var(--crit)"
                            strokeDasharray="5 4"
                            strokeWidth={1.5}
                        />
                    ) : null}
                    {series.map((s, index) => {
                        const color = s.color ?? VIZ[index % VIZ.length];

                        if ((s.type ?? 'area') === 'area') {
                            return (
                                <Area
                                    key={s.key}
                                    type="monotone"
                                    dataKey={s.key}
                                    name={s.label}
                                    stroke={color}
                                    fill={color}
                                    fillOpacity={0.15}
                                    strokeWidth={2}
                                    isAnimationActive={false}
                                />
                            );
                        }

                        return (
                            <Line
                                key={s.key}
                                type="monotone"
                                dataKey={s.key}
                                name={s.label}
                                stroke={color}
                                strokeWidth={2}
                                dot={false}
                                isAnimationActive={false}
                            />
                        );
                    })}
                </Chart>
            </ResponsiveContainer>
        </div>
    );
}
