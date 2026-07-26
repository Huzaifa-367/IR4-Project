import { cn } from '@/lib/utils';

type Props = {
    value: number;
    max?: number;
    tone?: 'ok' | 'warn' | 'crit' | 'accent';
    className?: string;
    showLabel?: boolean;
};

const fillTone: Record<NonNullable<Props['tone']>, string> = {
    ok: 'bg-[color:var(--ok)]',
    warn: 'bg-[color:var(--warn)]',
    crit: 'bg-[color:var(--crit)]',
    accent: 'bg-[color:var(--accent)]',
};

export function MiniProgress({
    value,
    max = 100,
    tone = 'accent',
    className,
    showLabel = false,
}: Props) {
    const pct = max <= 0 ? 0 : Math.max(0, Math.min(100, (value / max) * 100));

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <div className="h-1.5 flex-1 overflow-hidden rounded-pill bg-surface-3">
                <div
                    className={cn('h-full rounded-pill transition-[width]', fillTone[tone])}
                    style={{ width: `${pct}%` }}
                />
            </div>
            {showLabel ? (
                <span className="font-mono text-[11px] text-text-dim tabular-nums">
                    {Math.round(pct)}%
                </span>
            ) : null}
        </div>
    );
}
