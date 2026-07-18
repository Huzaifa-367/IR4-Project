import { cn } from '@/lib/utils';

export type StatusPillTone =
    | 'ok'
    | 'warn'
    | 'crit'
    | 'accent'
    | 'neutral'
    | 'info';

type Props = {
    label: string;
    tone?: StatusPillTone;
    className?: string;
    showDot?: boolean;
};

const toneClass: Record<StatusPillTone, string> = {
    ok: 'bg-[color:var(--ok-bg)] text-[color:var(--ok)]',
    warn: 'bg-[color:var(--warn-bg)] text-[color:var(--warn)]',
    crit: 'bg-[color:var(--crit-bg)] text-[color:var(--crit)]',
    accent: 'bg-[color:var(--accent-dim)] text-[color:var(--accent)]',
    neutral: 'bg-surface-3 text-text-dim',
    info: 'bg-surface-3 text-text-dim',
};

const dotClass: Record<StatusPillTone, string> = {
    ok: 'bg-[color:var(--ok)]',
    warn: 'bg-[color:var(--warn)]',
    crit: 'bg-[color:var(--crit)]',
    accent: 'bg-[color:var(--accent)]',
    neutral: 'bg-text-faint',
    info: 'bg-text-faint',
};

export function StatusPill({
    label,
    tone = 'neutral',
    className,
    showDot = true,
}: Props) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-pill px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase',
                toneClass[tone],
                className,
            )}
        >
            {showDot ? (
                <span
                    className={cn('size-1.5 rounded-full', dotClass[tone])}
                    aria-hidden
                />
            ) : null}
            {label}
        </span>
    );
}
