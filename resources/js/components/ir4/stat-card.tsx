import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: string | number;
    href?: string;
    delta?: string | null;
    deltaTone?: 'ok' | 'crit' | 'neutral';
    children?: React.ReactNode;
    className?: string;
    pulseCrit?: boolean;
};

export function StatCard({
    label,
    value,
    href,
    delta,
    deltaTone = 'neutral',
    children,
    className,
    pulseCrit = false,
}: Props) {
    const body = (
        <div
            className={cn(
                'flex h-full flex-col gap-2 rounded-[14px] border border-border bg-card p-4 shadow-sm',
                pulseCrit && 'animate-pulse border-[color:var(--crit,#F0506E)]',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <p className="min-w-0 flex-1 text-[12px] leading-snug font-medium tracking-[0.06em] break-words text-muted-foreground uppercase">
                    {label}
                </p>
                {href ? (
                    <span className="shrink-0 text-xs text-muted-foreground">
                        →
                    </span>
                ) : null}
            </div>
            <p className="font-mono text-4xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            {delta ? (
                <p
                    className={cn(
                        'text-xs font-medium',
                        deltaTone === 'ok' && 'text-[color:var(--ok,#34D399)]',
                        deltaTone === 'crit' &&
                            'text-[color:var(--crit,#F0506E)]',
                        deltaTone === 'neutral' && 'text-muted-foreground',
                    )}
                >
                    {delta}
                </p>
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
