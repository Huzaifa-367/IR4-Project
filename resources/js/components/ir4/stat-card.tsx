import { Link } from '@inertiajs/react';
import {
    Area,
    AreaChart,
    ResponsiveContainer,
} from 'recharts';
import { LiveNumber } from '@/components/ir4/live-number';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: string | number;
    href?: string;
    delta?: string | null;
    deltaTone?: 'ok' | 'crit' | 'neutral';
    sparkline?: number[];
    className?: string;
    pulseCrit?: boolean;
    children?: React.ReactNode;
};

export function StatCard({
    label,
    value,
    href,
    delta,
    deltaTone = 'neutral',
    sparkline,
    className,
    pulseCrit = false,
    children,
}: Props) {
    const chartData =
        sparkline?.map((v, i) => ({ i, v })) ?? [];

    const body = (
        <div
            className={cn(
                'flex h-full flex-col gap-2 rounded-[var(--radius)] border border-border bg-surface p-5 shadow-[var(--shadow-card)]',
                pulseCrit && 'animate-crit-pulse border-[color:var(--crit)]',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <p className="eyebrow min-w-0 flex-1">{label}</p>
                {href ? (
                    <span className="shrink-0 text-text-faint" aria-hidden>
                        ›
                    </span>
                ) : null}
            </div>
            <div className="flex flex-wrap items-end gap-2">
                <LiveNumber value={value} className="text-4xl text-text md:text-5xl" />
                {delta ? (
                    <span
                        className={cn(
                            'mb-1 inline-flex rounded-pill px-2 py-0.5 text-[11px] font-semibold',
                            deltaTone === 'ok' &&
                                'bg-[color:var(--ok-bg)] text-[color:var(--ok)]',
                            deltaTone === 'crit' &&
                                'bg-[color:var(--crit-bg)] text-[color:var(--crit)]',
                            deltaTone === 'neutral' &&
                                'bg-surface-3 text-text-dim',
                        )}
                    >
                        {delta}
                    </span>
                ) : null}
            </div>
            {chartData.length > 1 ? (
                <div className="mt-auto h-10 w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={chartData}>
                            <defs>
                                <linearGradient
                                    id={`spark-${label.replace(/\s+/g, '-')}`}
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        stopColor="var(--accent)"
                                        stopOpacity={0.35}
                                    />
                                    <stop
                                        offset="100%"
                                        stopColor="var(--accent)"
                                        stopOpacity={0}
                                    />
                                </linearGradient>
                            </defs>
                            <Area
                                type="monotone"
                                dataKey="v"
                                stroke="var(--accent)"
                                strokeWidth={1.5}
                                fill={`url(#spark-${label.replace(/\s+/g, '-')})`}
                                isAnimationActive={false}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            ) : null}
            {children}
        </div>
    );

    if (href) {
        return (
            <Link href={href} className="block h-full">
                {body}
            </Link>
        );
    }

    return body;
}
