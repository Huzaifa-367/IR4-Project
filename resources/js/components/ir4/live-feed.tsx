import { Link } from '@inertiajs/react';
import {
    StatusPill
    
} from '@/components/ir4/status-pill';
import type {StatusPillTone} from '@/components/ir4/status-pill';
import { cn } from '@/lib/utils';

export type LiveFeedItem = {
    id: string | number;
    title: string;
    severity: string;
    meta?: string;
    raisedAt?: string;
    href?: string;
};

type Props = {
    items: LiveFeedItem[];
    className?: string;
    emptyLabel?: string;
};

function toneForSeverity(severity: string): StatusPillTone {
    const s = severity.toLowerCase();

    if (s.includes('crit') || s === 'red') {
        return 'crit';
    }

    if (s.includes('warn') || s === 'amber' || s === 'high') {
        return 'warn';
    }

    if (s.includes('ok') || s.includes('resolv') || s === 'green') {
        return 'ok';
    }

    return 'info';
}

function relativeTime(iso?: string): string {
    if (!iso) {
        return '';
    }

    const ms = Date.now() - new Date(iso).getTime();

    if (!Number.isFinite(ms) || ms < 0) {
        return '';
    }

    const sec = Math.floor(ms / 1000);

    if (sec < 60) {
        return `${sec}s`;
    }

    const min = Math.floor(sec / 60);

    if (min < 60) {
        return `${min}m`;
    }

    const hr = Math.floor(min / 60);

    return `${hr}h`;
}

export function LiveFeed({
    items,
    className,
    emptyLabel = 'No open alerts',
}: Props) {
    return (
        <ul className={cn('divide-y divide-border', className)}>
            {items.length === 0 ? (
                <li className="px-1 py-8 text-center text-sm text-text-faint">
                    {emptyLabel}
                </li>
            ) : (
                items.map((item) => {
                    const tone = toneForSeverity(item.severity);
                    const border =
                        tone === 'crit'
                            ? 'border-l-[color:var(--crit)]'
                            : tone === 'warn'
                              ? 'border-l-[color:var(--warn)]'
                              : 'border-l-[color:var(--accent)]';

                    const row = (
                        <div
                            className={cn(
                                'flex gap-3 border-l-2 py-3 pr-1 pl-3',
                                border,
                            )}
                        >
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusPill
                                        label={item.severity}
                                        tone={tone}
                                    />
                                    <span className="font-mono text-[11px] text-text-faint">
                                        #{item.id}
                                    </span>
                                </div>
                                <p className="truncate text-sm font-medium text-text">
                                    {item.title}
                                </p>
                                {item.meta ? (
                                    <p className="truncate text-xs text-text-dim">
                                        {item.meta}
                                    </p>
                                ) : null}
                            </div>
                            <span className="shrink-0 font-mono text-[11px] text-text-faint tabular-nums">
                                {relativeTime(item.raisedAt)}
                            </span>
                        </div>
                    );

                    return (
                        <li key={item.id}>
                            {item.href ? (
                                <Link
                                    href={item.href}
                                    className="block hover:bg-surface-2"
                                >
                                    {row}
                                </Link>
                            ) : (
                                row
                            )}
                        </li>
                    );
                })
            )}
        </ul>
    );
}
