import { Head, Link } from '@inertiajs/react';
import { useCallback } from 'react';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import { useReverbChannel } from '@/hooks/use-reverb-channel';
import {
    PERMIT_BOARD_COLUMNS,
    replacePermitBoardColumns,
    upsertPermitBoardCard,
} from '@/lib/permit-board';
import { permitTypeBarClass, permitTypeDotClass } from '@/lib/permit-colours';
import { cn } from '@/lib/utils';
import permitRoutes from '@/routes/permits';
import tracking from '@/routes/tracking';
import type { PermitBoardCard, PermitBoardColumns } from '@/types/permit';

type Props = {
    columns: PermitBoardColumns;
    expiringWithinHours: number;
    canRequest: boolean;
};

const STATUS_TONE: Record<string, StatusPillTone> = {
    pending_inspection: 'warn',
    pending_gas_test: 'warn',
    pending_approval: 'warn',
    pending_issue: 'accent',
    active: 'ok',
    suspended: 'crit',
    expired: 'neutral',
};

function formatCountdown(validTo: string | null): string {
    if (!validTo) {
        return 'No window';
    }

    const diffMs = new Date(validTo).getTime() - Date.now();

    if (diffMs <= 0) {
        return 'Expired';
    }

    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    if (hours >= 24) {
        return `${Math.floor(hours / 24)}d ${hours % 24}h`;
    }

    return `${hours}h ${minutes}m`;
}

function PermitCard({ permit }: { permit: PermitBoardCard }) {
    return (
        <Link
            href={permitRoutes.show(permit.uuid)}
            className={cn(
                'block rounded-[var(--radius-sm)] border border-border bg-surface-2 p-3 shadow-[var(--shadow-card)] transition-colors hover:border-[color:var(--accent)]/50',
                permit.expiring && 'border-[color:var(--warn)]/50',
            )}
        >
            <div className="flex items-start gap-2">
                <span
                    className={cn(
                        'mt-0.5 h-8 w-1 shrink-0 rounded-full',
                        permitTypeBarClass(permit.type?.colour_token),
                    )}
                    aria-hidden
                />
                <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-2">
                        <span className="font-mono text-xs text-text">
                            {permit.permit_number}
                        </span>
                        <StatusPill
                            label={permit.status_label}
                            tone={STATUS_TONE[permit.status] ?? 'neutral'}
                        />
                    </div>
                    <p className="mt-1 line-clamp-2 text-sm text-text">
                        {permit.task_description}
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-dim">
                        <span className="inline-flex items-center gap-1.5">
                            <span
                                className={cn(
                                    'size-1.5 rounded-full',
                                    permitTypeDotClass(
                                        permit.type?.colour_token,
                                    ),
                                )}
                                aria-hidden
                            />
                            {permit.type?.name ?? 'Type'}
                        </span>
                        <span>{permit.zone?.name ?? 'No zone'}</span>
                        <span className="tabular-nums">
                            {formatCountdown(permit.valid_to)}
                        </span>
                    </div>
                </div>
            </div>
        </Link>
    );
}

export default function PermitBoard({
    columns,
    expiringWithinHours,
    canRequest,
}: Props) {
    const [board, setBoard] = usePropSyncedState(columns);
    const liveUrl = permitRoutes.live.url();

    const onSnapshot = useCallback(
        (data: unknown) => {
            const next = replacePermitBoardColumns(data);

            if (next) {
                setBoard(next);
            }
        },
        [setBoard],
    );

    useReverbChannel<{ permit: PermitBoardCard }>({
        channel: 'permits',
        events: ['.PermitUpdated'],
        onEvent: (payload) => {
            if (payload?.permit) {
                setBoard((current) =>
                    upsertPermitBoardCard(current, payload.permit),
                );
            }
        },
        snapshotUrl: liveUrl,
        onSnapshot,
    });

    const total = PERMIT_BOARD_COLUMNS.reduce(
        (sum, column) => sum + board[column.key].length,
        0,
    );

    return (
        <>
            <Head title="Permit board" />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Control room</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            Permit board
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            {total} live permit{total === 1 ? '' : 's'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild size="sm" variant="secondary">
                            <Link href={permitRoutes.index()}>Register</Link>
                        </Button>
                        {canRequest ? (
                            <Button asChild size="sm">
                                <Link href={permitRoutes.create()}>
                                    Request permit
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    {PERMIT_BOARD_COLUMNS.map((column) => {
                        const rows = board[column.key];
                        const countLabel = `${rows.length} permit${rows.length === 1 ? '' : 's'}`;
                        const subtitle =
                            column.key === 'expiring'
                                ? `${countLabel} · active, ≤${expiringWithinHours}h left`
                                : countLabel;

                        return (
                            <Panel
                                key={column.key}
                                title={column.title}
                                subtitle={subtitle}
                                className="min-h-48"
                            >
                                {rows.length === 0 ? (
                                    <p className="text-sm text-text-faint">
                                        None
                                    </p>
                                ) : (
                                    <div className="flex flex-col gap-2">
                                        {rows.map((permit) => (
                                            <PermitCard
                                                key={permit.uuid}
                                                permit={permit}
                                            />
                                        ))}
                                    </div>
                                )}
                            </Panel>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

PermitBoard.layout = {
    breadcrumbs: [
        { title: 'Workforce', href: tracking.workers.index() },
        { title: 'Permits', href: permitRoutes.board() },
    ],
};
