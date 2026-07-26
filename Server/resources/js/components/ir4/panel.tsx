import type { ReactNode } from 'react';
import {
    SectionInfo,
    
    withInfoAction
} from '@/components/ir4/section-info';
import type {SectionInfoContent} from '@/components/ir4/section-info';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    subtitle?: string;
    info?: SectionInfoContent;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
};

/** Standard analytics card shell — eyebrow-free title + subtitle + optional action, used across every dashboard-style page (DOC-16 §5). */
export function Panel({
    title,
    subtitle,
    info,
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
                    <div className="flex items-center gap-2">
                        <h2 className="text-sm font-semibold tracking-tight text-text">
                            {title}
                        </h2>
                        {info && !action ? <SectionInfo info={info} /> : null}
                    </div>
                    {subtitle ? (
                        <p className="mt-0.5 text-xs text-text-faint">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
                {withInfoAction(info && action ? info : undefined, action)}
            </div>
            {children}
        </section>
    );
}
