import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    subtitle?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
};

/** Standard analytics card shell — eyebrow-free title + subtitle + optional action, used across every dashboard-style page (DOC-16 §5). */
export function Panel({
    title,
    subtitle,
    action,
    children,
    className = '',
}: Props) {
    return (
        <section
            className={cn(
                'rounded-[var(--radius)] border border-border bg-surface p-4 shadow-[var(--shadow-card)] md:p-5',
                className,
            )}
        >
            <div className="mb-3 flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold tracking-tight text-text">
                        {title}
                    </h2>
                    {subtitle ? (
                        <p className="mt-0.5 text-xs text-text-faint">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
