import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type SectionInfoContent = {
    summary: string;
    items?: readonly string[];
    source?: string;
};

type Props = {
    title?: string;
    info: SectionInfoContent;
    className?: string;
};

/** Clickable (i) control that explains what data a panel/stat uses. */
export function SectionInfo({ title = 'About this data', info, className }: Props) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'inline-flex size-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface-2 text-text-faint transition-colors hover:border-[color:var(--accent)] hover:text-[color:var(--accent)] focus-visible:ring-2 focus-visible:ring-[color:var(--accent)] focus-visible:outline-none',
                        className,
                    )}
                    aria-label={title}
                    onClick={(event) => {
                        event.preventDefault();
                        event.stopPropagation();
                    }}
                    onPointerDown={(event) => event.stopPropagation()}
                >
                    <Info className="size-3.5" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[min(22rem,calc(100vw-2rem))] border-border bg-surface p-0 text-text shadow-[var(--shadow-pop)]"
                onClick={(event) => event.stopPropagation()}
            >
                <div className="border-b border-border px-3 py-2">
                    <p className="text-[11px] font-semibold tracking-wide text-text-faint uppercase">
                        {title}
                    </p>
                </div>
                <div className="flex flex-col gap-2 px-3 py-3">
                    <p className="text-sm leading-relaxed text-text-dim">
                        {info.summary}
                    </p>
                    {info.items && info.items.length > 0 ? (
                        <ul className="flex flex-col gap-1.5 text-xs leading-snug text-text-dim">
                            {info.items.map((item) => (
                                <li
                                    key={item}
                                    className="flex gap-2 before:mt-1.5 before:size-1 before:shrink-0 before:rounded-full before:bg-[color:var(--accent)] before:content-['']"
                                >
                                    <span>{item}</span>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                    {info.source ? (
                        <p className="border-t border-border pt-2 font-mono text-[10px] text-text-faint">
                            Source: {info.source}
                        </p>
                    ) : null}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function withInfoAction(
    info: SectionInfoContent | undefined,
    action: ReactNode,
): ReactNode {
    if (!info && !action) {
        return null;
    }

    return (
        <div className="flex shrink-0 items-center gap-2">
            {info ? <SectionInfo info={info} /> : null}
            {action}
        </div>
    );
}
