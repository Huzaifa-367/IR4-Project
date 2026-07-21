import { cn } from '@/lib/utils';
import { CheckoutStateLabels, EquipmentStatusLabels } from '@/types/enums';
import type { CheckoutState, EquipmentStatus } from '@/types/enums';

type StatusBadgeProps = {
    status: EquipmentStatus | string;
    className?: string;
};

export function EquipmentStatusBadge({ status, className }: StatusBadgeProps) {
    const label =
        EquipmentStatusLabels[status as EquipmentStatus] ??
        status.replaceAll('_', ' ');

    const tone =
        status === 'in_service'
            ? 'bg-[color:var(--ok-bg)] text-[color:var(--ok)]'
            : status === 'out_of_service'
              ? 'bg-[color:var(--warn-bg)] text-[color:var(--warn)]'
              : status === 'under_maintenance'
                ? 'bg-[color:var(--accent-dim)] text-[color:var(--accent)]'
                : status === 'retired'
                  ? 'bg-surface-3 text-text-dim'
                  : 'bg-surface-3 text-text-dim';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-pill px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase',
                tone,
                className,
            )}
        >
            <span
                className={cn(
                    'size-1.5 rounded-full',
                    status === 'in_service' && 'bg-[color:var(--ok)]',
                    status === 'out_of_service' && 'bg-[color:var(--warn)]',
                    status === 'under_maintenance' &&
                        'bg-[color:var(--accent)]',
                    status === 'retired' && 'bg-text-faint',
                )}
                aria-hidden
            />
            {label}
        </span>
    );
}

type CustodyBadgeProps = {
    state: CheckoutState | string;
    workerName?: string | null;
    className?: string;
};

export function CustodyBadge({
    state,
    workerName,
    className,
}: CustodyBadgeProps) {
    const label =
        CheckoutStateLabels[state as CheckoutState] ??
        state.replaceAll('_', ' ');
    const detail =
        (state === 'checked_out' || state === 'overdue_return') && workerName
            ? `${label} · ${workerName}`
            : label;

    const tone =
        state === 'available'
            ? 'bg-[color:var(--ok-bg)] text-[color:var(--ok)]'
            : state === 'overdue_return'
              ? 'bg-[color:var(--crit-bg)] text-[color:var(--crit)]'
              : 'bg-[color:var(--accent-dim)] text-[color:var(--accent)]';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-pill px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase',
                tone,
                className,
            )}
        >
            <span
                className={cn(
                    'size-1.5 rounded-full',
                    state === 'available' && 'bg-[color:var(--ok)]',
                    state === 'overdue_return' && 'bg-[color:var(--crit)]',
                    state === 'checked_out' && 'bg-[color:var(--accent)]',
                )}
                aria-hidden
            />
            {detail}
        </span>
    );
}

type OverdueBadgeProps = {
    isInspectionOverdue?: boolean;
    isServiceOverdue?: boolean;
    isDueSoon?: boolean;
    isReturnOverdue?: boolean;
    className?: string;
};

export function OverdueBadge({
    isInspectionOverdue = false,
    isServiceOverdue = false,
    isDueSoon = false,
    isReturnOverdue = false,
    className,
}: OverdueBadgeProps) {
    if (isReturnOverdue) {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1.5 rounded-pill bg-[color:var(--crit-bg)] px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase text-[color:var(--crit)]',
                    className,
                )}
            >
                <span
                    className="size-1.5 rounded-full bg-[color:var(--crit)]"
                    aria-hidden
                />
                Overdue return
            </span>
        );
    }

    if (isInspectionOverdue || isServiceOverdue) {
        const parts: string[] = [];

        if (isInspectionOverdue) {
            parts.push('inspection');
        }

        if (isServiceOverdue) {
            parts.push('service');
        }

        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1.5 rounded-pill bg-[color:var(--crit-bg)] px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase text-[color:var(--crit)]',
                    className,
                )}
            >
                <span
                    className="size-1.5 rounded-full bg-[color:var(--crit)]"
                    aria-hidden
                />
                Overdue {parts.join(' + ')}
            </span>
        );
    }

    if (isDueSoon) {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1.5 rounded-pill bg-[color:var(--warn-bg)] px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase text-[color:var(--warn)]',
                    className,
                )}
            >
                <span
                    className="size-1.5 rounded-full bg-[color:var(--warn)]"
                    aria-hidden
                />
                Due within 7 days
            </span>
        );
    }

    return null;
}
