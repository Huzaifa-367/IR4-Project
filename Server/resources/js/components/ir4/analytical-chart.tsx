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
    yAxisId?: 'left' | 'right';
};

function seriesMax(
    data: Array<Record<string, string | number | null>>,
    key: string,
): number {
    return data.reduce((max, row) => {
        const value = row[key];

        return typeof value === 'number' && Number.isFinite(value)
            ? Math.max(max, value)
            : max;
    }, 0);
}

/** Evenly spaced ticks from 0 so mixed-scale charts show intermediate labels. */
function axisTicks(max: number, targetCount = 21): number[] {
    const padded = Math.max(max, 1) * 1.05;
    const rough = padded / Math.max(targetCount - 1, 1);
    const magnitude = 10 ** Math.floor(Math.log10(Math.max(rough, 1e-9)));
    const residual = rough / magnitude;
    const step =
        residual <= 1.1
            ? magnitude
            : residual <= 2.2
              ? 2 * magnitude
              : residual <= 5.5
                ? 5 * magnitude
                : 10 * magnitude;
    const top = Math.ceil(padded / step) * step;
    const ticks: number[] = [];

    for (let value = 0; value <= top + step / 2; value += step) {
        ticks.push(Number(value.toFixed(6)));
    }

    return ticks;
}

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
            <p className="mb-1 font-mono text-[11px] text-text-faint">
                {label}
            </p>
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
                        <span className="ml-auto font-mono text-text tabular-nums">
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
    const maxima = series.map((item) => seriesMax(data, item.key));
    const high = Math.max(0, ...maxima);
    const splitAxes =
        series.length > 1 &&
        maxima.some((value) => value > 0 && value <= high / 5);
    const leftMax = splitAxes
        ? Math.max(
              0,
              ...maxima.filter((value, index) => maxima[index] <= high / 5),
          )
        : high;
    const rightMax = splitAxes ? high : 0;
    const leftTicks = axisTicks(leftMax, 21);
    const rightTicks = splitAxes ? axisTicks(rightMax, 21) : [];
    const axisFor = (item: ChartSeries, index: number): 'left' | 'right' =>
        item.yAxisId ??
        (splitAxes && maxima[index] > high / 5 ? 'right' : 'left');

    return (
        <div className={cn('w-full', className)} style={{ height }}>
            <ResponsiveContainer width="100%" height="100%">
                <Chart
                    data={data}
                    margin={{
                        top: 8,
                        right: splitAxes ? 4 : 8,
                        left: 0,
                        bottom: 4,
                    }}
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
                        yAxisId="left"
                        ticks={leftTicks}
                        interval={0}
                        minTickGap={0}
                        domain={[0, leftTicks[leftTicks.length - 1] ?? 'auto']}
                        tick={{ fill: 'var(--text-faint)', fontSize: 10 }}
                        tickFormatter={(value: number) => String(Number(value))}
                        axisLine={false}
                        tickLine={false}
                        width={36}
                    />
                    {splitAxes ? (
                        <YAxis
                            yAxisId="right"
                            orientation="right"
                            ticks={rightTicks}
                            interval={0}
                            minTickGap={0}
                            domain={[
                                0,
                                rightTicks[rightTicks.length - 1] ?? 'auto',
                            ]}
                            tick={{ fill: 'var(--text-faint)', fontSize: 10 }}
                            tickFormatter={(value: number) =>
                                String(Number(value))
                            }
                            axisLine={false}
                            tickLine={false}
                            width={40}
                        />
                    ) : null}
                    <Tooltip
                        content={<ChartTooltip />}
                        cursor={{
                            stroke: 'var(--text-faint)',
                            strokeDasharray: '4 4',
                        }}
                    />
                    {series.length > 1 ? (
                        <Legend
                            verticalAlign="bottom"
                            height={28}
                            iconType="circle"
                            iconSize={8}
                            wrapperStyle={{
                                fontSize: 12,
                                color: 'var(--text-dim)',
                                paddingTop: 4,
                                marginTop: 0,
                            }}
                        />
                    ) : null}
                    {thresholdWarn !== undefined ? (
                        <ReferenceLine
                            yAxisId="left"
                            y={thresholdWarn}
                            stroke="var(--warn)"
                            strokeDasharray="5 4"
                            strokeWidth={1.5}
                        />
                    ) : null}
                    {thresholdCrit !== undefined ? (
                        <ReferenceLine
                            yAxisId="left"
                            y={thresholdCrit}
                            stroke="var(--crit)"
                            strokeDasharray="5 4"
                            strokeWidth={1.5}
                        />
                    ) : null}
                    {series.map((s, index) => {
                        const color = s.color ?? VIZ[index % VIZ.length];
                        const yAxisId = splitAxes ? axisFor(s, index) : 'left';

                        if ((s.type ?? 'area') === 'area') {
                            return (
                                <Area
                                    key={s.key}
                                    yAxisId={yAxisId}
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
                                yAxisId={yAxisId}
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
