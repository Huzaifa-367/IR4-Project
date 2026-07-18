import { cn } from '@/lib/utils';

type Gauge = {
    label: string;
    source: string;
    value: number;
    unit: string;
    warn: number | null;
    alarm: number | null;
    status: 'ok' | 'warn' | 'crit';
};

type Props = {
    gauges: Gauge[];
    className?: string;
};

export function GasChannelGauges({ gauges, className }: Props) {
    if (gauges.length === 0) {
        return (
            <div className="py-8 text-center text-sm text-text-faint">
                No live gas channels
            </div>
        );
    }

    return (
        <div className={cn('space-y-3', className)}>
            {gauges.map((g) => {
                const alarm = g.alarm ?? Math.max(g.value * 1.5, 1);
                const warn = g.warn ?? alarm * 0.5;
                const pct = Math.max(3, Math.min(100, (g.value / alarm) * 100));
                const warnPct = Math.max(0, Math.min(100, (warn / alarm) * 100));
                const color =
                    g.status === 'crit'
                        ? 'var(--crit)'
                        : g.status === 'warn'
                          ? 'var(--warn)'
                          : 'var(--ok)';

                return (
                    <div
                        key={`${g.label}-${g.source}`}
                        className="grid grid-cols-[64px_1fr_56px] items-center gap-2"
                    >
                        <div className="min-w-0">
                            <div className="text-xs font-semibold text-text">
                                {g.label}
                            </div>
                            <div className="truncate text-[10px] text-text-faint">
                                {g.source}
                            </div>
                        </div>
                        <div className="relative h-2 rounded-pill bg-surface-3">
                            <div
                                className="absolute inset-y-0 left-0 rounded-pill"
                                style={{
                                    width: `${pct}%`,
                                    background: color,
                                }}
                            />
                            <div
                                className="absolute top-[-3px] bottom-[-3px] w-px bg-[color:var(--warn)]"
                                style={{ left: `${warnPct}%` }}
                            />
                            <div className="absolute top-[-3px] right-0 bottom-[-3px] w-px bg-[color:var(--crit)]" />
                        </div>
                        <div className="text-right">
                            <span
                                className="font-mono text-sm font-semibold tabular-nums"
                                style={{ color }}
                            >
                                {Number.isInteger(g.value)
                                    ? g.value
                                    : g.value.toFixed(1)}
                            </span>
                            <span className="ml-0.5 text-[10px] text-text-faint">
                                {g.unit}
                            </span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
