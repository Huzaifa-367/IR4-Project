import { LiveNumber } from '@/components/ir4/live-number';
import { cn } from '@/lib/utils';

export type MetricItem = {
    label: string;
    value: string | number;
    delta?: string;
    deltaTone?: 'ok' | 'crit' | 'neutral';
};

type Props = {
    items: MetricItem[];
    className?: string;
    compact?: boolean;
};

export function MetricRow({ items, className, compact = false }: Props) {
    return (
        <div
            className={cn(
                'grid sm:grid-cols-3',
                compact ? 'gap-2' : 'gap-4',
                className,
            )}
        >
            {items.map((item) => (
                <div key={item.label} className="min-w-0">
                    <p className={cn('eyebrow', compact ? 'mb-0.5' : 'mb-1')}>
                        {item.label}
                    </p>
                    <LiveNumber
                        value={item.value}
                        className={cn(
                            'text-text',
                            compact ? 'text-sm' : 'text-xl',
                        )}
                    />
                    {item.delta ? (
                        <p
                            className={cn(
                                'mt-0.5 text-[11px]',
                                item.deltaTone === 'ok' &&
                                    'text-[color:var(--ok)]',
                                item.deltaTone === 'crit' &&
                                    'text-[color:var(--crit)]',
                                (!item.deltaTone ||
                                    item.deltaTone === 'neutral') &&
                                    'text-text-faint',
                            )}
                        >
                            {item.delta}
                        </p>
                    ) : null}
                </div>
            ))}
        </div>
    );
}
