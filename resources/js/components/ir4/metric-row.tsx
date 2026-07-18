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
};

export function MetricRow({ items, className }: Props) {
    return (
        <div
            className={cn(
                'grid gap-4 sm:grid-cols-3',
                className,
            )}
        >
            {items.map((item) => (
                <div key={item.label} className="min-w-0">
                    <p className="eyebrow mb-1">{item.label}</p>
                    <LiveNumber
                        value={item.value}
                        className="text-xl text-text"
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
