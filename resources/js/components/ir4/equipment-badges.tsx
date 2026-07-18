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
            ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100'
            : status === 'out_of_service'
              ? 'bg-amber-100 text-amber-950 dark:bg-amber-950 dark:text-amber-100'
              : status === 'under_maintenance'
                ? 'bg-sky-100 text-sky-950 dark:bg-sky-950 dark:text-sky-100'
                : status === 'retired'
                  ? 'bg-zinc-200 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100'
                  : 'bg-muted text-muted-foreground';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium capitalize',
                tone,
                className,
            )}
        >
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
            ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100'
            : state === 'overdue_return'
              ? 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-100'
              : 'bg-violet-100 text-violet-950 dark:bg-violet-950 dark:text-violet-100';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium',
                tone,
                className,
            )}
        >
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
                    'inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-900 dark:bg-red-950 dark:text-red-100',
                    className,
                )}
            >
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
                    'inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-900 dark:bg-red-950 dark:text-red-100',
                    className,
                )}
            >
                Overdue {parts.join(' + ')}
            </span>
        );
    }

    if (isDueSoon) {
        return (
            <span
                className={cn(
                    'inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-950 dark:bg-amber-950 dark:text-amber-100',
                    className,
                )}
            >
                Due within 7 days
            </span>
        );
    }

    return null;
}
