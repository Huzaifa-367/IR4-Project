import { Fragment } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    types: Array<{ key: string; label: string }>;
    hours: number[];
    cells: number[][];
    className?: string;
};

const TYPE_COLOR = [
    'var(--viz-1)',
    'var(--viz-6)',
    'var(--crit)',
    'var(--viz-2)',
];

export function PpeHeatmap({ types, hours, cells, className }: Props) {
    if (types.length === 0 || hours.length === 0) {
        return (
            <div className="py-8 text-center text-sm text-text-faint">
                No PPE data this shift
            </div>
        );
    }

    return (
        <div
            className={cn('overflow-x-auto', className)}
            style={{
                display: 'grid',
                gridTemplateColumns: `72px repeat(${hours.length}, minmax(18px, 1fr))`,
                gap: 4,
            }}
        >
            <div />
            {hours.map((hour) => (
                <div
                    key={hour}
                    className="text-center font-mono text-[10px] text-text-faint"
                >
                    {hour}
                </div>
            ))}
            {types.map((type, ti) => (
                <Fragment key={type.key}>
                    <div className="flex items-center text-xs text-text-dim">
                        {type.label}
                    </div>
                    {(cells[ti] ?? []).map((value, hi) => {
                        const opacity =
                            value === 0
                                ? 0.06
                                : Math.min(1, 0.18 + value * 0.26);
                        const color = TYPE_COLOR[ti % TYPE_COLOR.length];

                        return (
                            <div
                                key={`${type.key}-${hours[hi]}`}
                                title={`${type.label} @ ${hours[hi]}:00 — ${value}`}
                                className="aspect-square rounded-[3px]"
                                style={{
                                    background: `color-mix(in srgb, ${color} ${opacity * 100}%, var(--surface-3))`,
                                }}
                            />
                        );
                    })}
                </Fragment>
            ))}
        </div>
    );
}
