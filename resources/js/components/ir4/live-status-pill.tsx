import type { ReverbLiveStatus } from '@/hooks/use-reverb-channel';

const labels: Record<ReverbLiveStatus, string> = {
    live: 'LIVE',
    reconnecting: 'RECONNECTING',
    offline: 'OFFLINE',
};

export function LiveStatusPill({ status }: { status: ReverbLiveStatus }) {
    const tone =
        status === 'live'
            ? 'border-emerald-600/40 bg-emerald-50 text-emerald-900'
            : status === 'reconnecting'
              ? 'border-amber-500/40 bg-amber-50 text-amber-950'
              : 'border-border bg-muted text-muted-foreground';

    return (
        <span
            className={`inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium tracking-wide ${tone}`}
            data-live-status={status}
        >
            {labels[status]}
        </span>
    );
}
