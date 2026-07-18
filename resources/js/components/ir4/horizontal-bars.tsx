import { cn } from '@/lib/utils';

export type HorizontalBarItem = {
    label: string;
    value: number;
    color?: string;
};

type Props = {
    items: HorizontalBarItem[];
    className?: string;
};

const VIZ = [
    'var(--viz-1)',
    'var(--viz-6)',
    'var(--crit)',
    'var(--warn)',
    'var(--viz-2)',
    'var(--viz-4)',
    'var(--viz-5)',
];

export function HorizontalBars({ items, className }: Props) {
    const max = Math.max(1, ...items.map((i) => i.value));

    if (items.length === 0) {
        return (
            <div className="py-8 text-center text-sm text-text-faint">
                No open LSR this shift
            </div>
        );
    }

    return (
        <div className={cn('space-y-2.5', className)}>
            {items.map((item, index) => (
                <div
                    key={item.label}
                    className="grid grid-cols-[120px_1fr_36px] items-center gap-2"
                >
                    <span className="truncate text-xs text-text-dim">
                        {item.label}
                    </span>
                    <div className="h-2 overflow-hidden rounded-pill bg-surface-3">
                        <div
                            className="h-full rounded-pill"
                            style={{
                                width: `${(item.value / max) * 100}%`,
                                background: item.color ?? VIZ[index % VIZ.length],
                            }}
                        />
                    </div>
                    <span className="text-right font-mono text-xs tabular-nums text-text">
                        {item.value}
                    </span>
                </div>
            ))}
        </div>
    );
}
