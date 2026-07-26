import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type FactTileTone = 'ok' | 'warn' | 'crit' | 'accent' | 'neutral' | 'info';

type Props = {
    label: string;
    value: ReactNode;
    tone?: FactTileTone;
    className?: string;
};

export function FactTile({
    label,
    value,
    tone = 'neutral',
    className,
}: Props) {
    return (
        <div
            className={cn(
                'rounded-[var(--radius)] border border-border bg-surface px-3 py-2.5 shadow-[var(--shadow-card)]',
                tone === 'ok' && 'border-[color:var(--ok)]/35',
                tone === 'warn' && 'border-[color:var(--warn)]/35',
                tone === 'crit' && 'border-[color:var(--crit)]/35',
                tone === 'accent' && 'border-[color:var(--accent)]/35',
                (tone === 'neutral' || tone === 'info') && '',
                className,
            )}
        >
            <p className="eyebrow">{label}</p>
            <div className="mt-1 truncate text-sm font-semibold text-text">
                {value}
            </div>
        </div>
    );
}

type DetailFieldProps = {
    label: string;
    value: ReactNode;
    className?: string;
};

export function DetailField({ label, value, className }: DetailFieldProps) {
    return (
        <div
            className={cn(
                'rounded-md border border-border/80 bg-surface-2/20 px-3 py-2',
                className,
            )}
        >
            <dt className="eyebrow">{label}</dt>
            <dd className="mt-1 text-sm font-medium text-text">
                {value ?? '—'}
            </dd>
        </div>
    );
}
