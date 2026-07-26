import type { ReverbLiveStatus } from '@/hooks/use-reverb-channel';
import { cn } from '@/lib/utils';

const labels: Record<ReverbLiveStatus, string> = {
    live: 'LIVE',
    reconnecting: 'RECONNECTING',
    offline: 'OFFLINE',
};

export function LiveStatusPill({ status }: { status: ReverbLiveStatus }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-pill border px-2.5 py-1 text-[11px] font-semibold tracking-[0.08em]',
                status === 'live' &&
                    'border-[color:var(--ok)]/40 bg-[color:var(--ok-bg)] text-[color:var(--ok)]',
                status === 'reconnecting' &&
                    'border-[color:var(--warn)]/40 bg-[color:var(--warn-bg)] text-[color:var(--warn)]',
                status === 'offline' &&
                    'border-border bg-surface-3 text-text-dim',
            )}
            data-live-status={status}
        >
            <span
                className={cn(
                    'size-1.5 rounded-full',
                    status === 'live' &&
                        'animate-live-dot bg-[color:var(--ok)]',
                    status === 'reconnecting' && 'bg-[color:var(--warn)]',
                    status === 'offline' && 'bg-text-faint',
                )}
                aria-hidden
            />
            {labels[status]}
        </span>
    );
}
