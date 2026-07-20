import { Head, Link } from '@inertiajs/react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';

type PermitRow = {
    id: number;
    permit_number: string;
    status: string;
    status_label: string;
    type: {
        id: number;
        code: string;
        name: string;
        colour_token: string | null;
    } | null;
    zone: { id: number; name: string } | null;
};

type Props = {
    workOrder: {
        id: number;
        reference: string;
        description: string | null;
        status: string;
        zone: { id: number; name: string } | null;
        created_at: string | null;
    };
    clearance: {
        is_clear: boolean;
        total_permits: number;
        active_permits: number;
        pending_permits: number;
        permits: PermitRow[];
    };
    canCreatePermit: boolean;
};

const STATUS_TONE: Record<string, StatusPillTone> = {
    draft: 'neutral',
    pending_inspection: 'warn',
    pending_gas_test: 'warn',
    pending_approval: 'warn',
    pending_issue: 'accent',
    active: 'ok',
    suspended: 'crit',
    expired: 'neutral',
    closed: 'neutral',
    cancelled: 'neutral',
    rejected: 'crit',
};

export default function WorkOrderShow({
    workOrder,
    clearance,
    canCreatePermit,
}: Props) {
    const columns: SettingsColumn<PermitRow>[] = [
        {
            key: 'number',
            header: 'Permit',
            cell: (row) => (
                <Link
                    href={`/workforce/permits/${row.id}`}
                    className="font-mono text-xs hover:underline"
                >
                    {row.permit_number}
                </Link>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (row) => row.type?.name ?? '—',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <StatusPill
                    label={row.status_label}
                    tone={STATUS_TONE[row.status] ?? 'neutral'}
                />
            ),
        },
    ];

    return (
        <>
            <Head title={workOrder.reference} />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Work order</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            {workOrder.reference}
                        </h1>
                        {workOrder.description ? (
                            <p className="mt-1 max-w-2xl text-sm text-text-dim">
                                {workOrder.description}
                            </p>
                        ) : null}
                        {workOrder.zone ? (
                            <p className="mt-1 text-xs text-text-faint">
                                Zone: {workOrder.zone.name}
                            </p>
                        ) : null}
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href="/workforce/work-orders">Back</Link>
                        </Button>
                        {canCreatePermit ? (
                            <Button asChild>
                                <Link href="/workforce/permits/create">
                                    Request permit
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                <Panel title="Clearance status">
                    <div className="flex flex-wrap items-center gap-3">
                        <StatusPill
                            label={
                                clearance.is_clear
                                    ? 'All permits active'
                                    : 'Pending permits'
                            }
                            tone={clearance.is_clear ? 'ok' : 'warn'}
                        />
                        <span className="text-sm text-text-dim">
                            {clearance.active_permits} active ·{' '}
                            {clearance.pending_permits} pending ·{' '}
                            {clearance.total_permits} total
                        </span>
                    </div>
                    <p className="mt-2 text-xs text-text-faint">
                        Work order is clear when every linked permit is active,
                        or when no permits are linked yet.
                    </p>
                </Panel>

                <Panel title="Linked permits">
                    <SettingsDataTable
                        columns={columns}
                        rows={clearance.permits}
                        rowKey={(row) => row.id}
                        emptyTitle="No permits linked"
                        emptyDescription="Link permits by selecting this work order when requesting a permit."
                    />
                </Panel>
            </div>
        </>
    );
}
