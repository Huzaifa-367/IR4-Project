import { Cell, Pie, PieChart, ResponsiveContainer } from 'recharts';
import { LiveNumber } from '@/components/ir4/live-number';
import { cn } from '@/lib/utils';

export type DonutSlice = {
    label: string;
    value: number;
    color?: string;
};

type Props = {
    data: DonutSlice[];
    centerLabel?: string;
    className?: string;
    height?: number;
};

const VIZ = [
    'var(--viz-1)',
    'var(--viz-2)',
    'var(--viz-3)',
    'var(--viz-4)',
    'var(--viz-5)',
    'var(--viz-6)',
];

export function DonutChart({
    data,
    centerLabel = 'Total',
    className,
    height = 200,
}: Props) {
    const total = data.reduce((sum, row) => sum + row.value, 0);

    if (total === 0) {
        return (
            <div
                className={cn(
                    'flex items-center justify-center text-sm text-text-faint',
                    className,
                )}
                style={{ height }}
            >
                No data
            </div>
        );
    }

    return (
        <div className={cn('grid gap-3 sm:grid-cols-[1fr_1fr]', className)}>
            <div className="relative" style={{ height }}>
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={data}
                            dataKey="value"
                            nameKey="label"
                            innerRadius="62%"
                            outerRadius="88%"
                            paddingAngle={2}
                            stroke="none"
                            isAnimationActive={false}
                        >
                            {data.map((row, i) => (
                                <Cell
                                    key={row.label}
                                    fill={row.color ?? VIZ[i % VIZ.length]}
                                />
                            ))}
                        </Pie>
                    </PieChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <p className="eyebrow">{centerLabel}</p>
                    <LiveNumber value={total} className="text-2xl text-text" />
                </div>
            </div>
            <ul className="flex flex-col justify-center gap-2 text-sm">
                {data.map((row, i) => {
                    const pct = Math.round((row.value / total) * 100);

                    return (
                        <li
                            key={row.label}
                            className="flex items-center justify-between gap-2"
                        >
                            <span className="flex min-w-0 items-center gap-2 text-text-dim">
                                <span
                                    className="size-2 shrink-0 rounded-full"
                                    style={{
                                        background:
                                            row.color ?? VIZ[i % VIZ.length],
                                    }}
                                />
                                <span className="truncate">{row.label}</span>
                            </span>
                            <span className="font-mono text-text tabular-nums">
                                {row.value} · {pct}%
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

type GaugeProps = {
    value: number;
    max?: number;
    label?: string;
    sublabel?: string;
    className?: string;
};

export function RadialGauge({
    value,
    max = 100,
    label = 'Score',
    sublabel,
    className,
}: GaugeProps) {
    const pct = max <= 0 ? 0 : Math.max(0, Math.min(100, (value / max) * 100));
    const tone =
        pct >= 80 ? 'var(--ok)' : pct >= 50 ? 'var(--warn)' : 'var(--crit)';

    return (
        <div className={cn('flex flex-col items-center gap-2', className)}>
            <div className="relative size-36">
                <svg viewBox="0 0 120 120" className="size-full -rotate-90">
                    <circle
                        cx="60"
                        cy="60"
                        r="48"
                        fill="none"
                        stroke="var(--surface-3)"
                        strokeWidth="12"
                    />
                    <circle
                        cx="60"
                        cy="60"
                        r="48"
                        fill="none"
                        stroke={tone}
                        strokeWidth="12"
                        strokeLinecap="round"
                        strokeDasharray={`${(pct / 100) * 301.6} 301.6`}
                    />
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center">
                    <LiveNumber
                        value={Math.round(value)}
                        className="text-3xl text-text"
                    />
                    <span className="text-[11px] text-text-faint">/{max}</span>
                </div>
            </div>
            <p className="eyebrow">{label}</p>
            {sublabel ? (
                <p className="text-xs text-text-dim">{sublabel}</p>
            ) : null}
        </div>
    );
}
