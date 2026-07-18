import { usePermissions } from '@/hooks/use-permissions';
import { useSystemHealth } from '@/hooks/use-system-health';
import { cn } from '@/lib/utils';

const DOT_TONE: Record<string, string> = {
    ok: 'bg-[color:var(--ok)] shadow-[0_0_8px_var(--ok)]',
    warn: 'bg-[color:var(--warn)] shadow-[0_0_8px_var(--warn)]',
    crit: 'bg-[color:var(--crit)] shadow-[0_0_8px_var(--crit)]',
    muted: 'bg-text-faint',
};

/** Sidebar footer status block — mirrors the mockup's `.sysfoot` panel. */
export function SystemStatusPanel() {
    const { can } = usePermissions();
    const health = useSystemHealth(can('view-dashboard'));

    if (!health) {
        return null;
    }

    return (
        <div className="rounded-[var(--radius)] border border-border bg-surface-2 px-3.5 py-3">
            <p className="eyebrow flex items-center gap-1.5">
                <span
                    className={cn(
                        'size-[7px] rounded-full',
                        DOT_TONE[health.tone],
                    )}
                />
                System Status
            </p>
            <p className="mt-1 text-lg font-bold tracking-tight text-text">
                {health.label}
                {health.total > 0 ? (
                    <small className="ml-1.5 text-[13px] font-semibold text-text-dim">
                        {health.online}/{health.total}
                    </small>
                ) : null}
            </p>
            <p className="mt-0.5 text-[11px] text-text-faint">
                {health.meta}
            </p>
        </div>
    );
}
