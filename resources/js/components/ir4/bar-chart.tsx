import {
    Bar,
    BarChart as RechartsBarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { cn } from '@/lib/utils';

type Props = {
    data: Array<{ label: string; value: number; fill?: string }>;
    className?: string;
    height?: number;
    emptyLabel?: string;
};

export function BarChart({
    data,
    className,
    height = 220,
    emptyLabel = 'No data for this range',
}: Props) {
    if (data.length === 0) {
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

    const peak = Math.max(...data.map((d) => d.value));

    return (
        <div className={cn('w-full', className)} style={{ height }}>
            <ResponsiveContainer width="100%" height="100%">
                <RechartsBarChart
                    data={data.map((d) => ({
                        ...d,
                        fill:
                            d.fill ??
                            (d.value === peak
                                ? 'var(--accent-strong)'
                                : 'var(--accent)'),
                    }))}
                    margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                >
                    <CartesianGrid
                        stroke="var(--border)"
                        strokeDasharray="3 3"
                        vertical={false}
                    />
                    <XAxis
                        dataKey="label"
                        tick={{ fill: 'var(--text-faint)', fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                    />
                    <YAxis
                        tick={{ fill: 'var(--text-faint)', fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                        width={32}
                    />
                    <Tooltip
                        cursor={{ fill: 'var(--surface-2)' }}
                        contentStyle={{
                            background: 'var(--surface-2)',
                            border: '1px solid var(--border)',
                            borderRadius: 10,
                            fontSize: 12,
                        }}
                    />
                    <Bar
                        dataKey="value"
                        radius={[6, 6, 0, 0]}
                        isAnimationActive={false}
                    />
                </RechartsBarChart>
            </ResponsiveContainer>
        </div>
    );
}
