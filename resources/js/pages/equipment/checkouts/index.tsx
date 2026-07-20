import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { CustodyBadge, OverdueBadge } from '@/components/ir4/equipment-badges';
import { ReturnDialog } from '@/components/ir4/return-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
import type { EquipmentCheckout } from '@/types/equipment';
import type { PaginatedMeta } from '@/types/hardware';

type Props = {
    checkouts: { data: EquipmentCheckout[]; meta: PaginatedMeta };
    filters: { open: boolean; search: string };
    canManage: boolean;
};

export default function EquipmentCheckoutsIndex({
    checkouts,
    filters,
    canManage,
}: Props) {
    const [returnTarget, setReturnTarget] = useState<EquipmentCheckout | null>(
        null,
    );
    const [search, setSearch] = useState(filters.search);

    function applyFilters(
        patch: Partial<{ open: boolean; search: string }> = {},
    ): void {
        const nextOpen = patch.open ?? filters.open;
        const nextSearch = patch.search ?? search;

        visitFilters('/equipment/checkouts', {
            open: nextOpen ? '1' : '0',
            search: nextSearch || undefined,
        });
    }

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const columns: SettingsColumn<EquipmentCheckout>[] = [
        {
            key: 'equipment',
            header: 'Equipment',
            cell: (row) =>
                row.equipment ? (
                    <Link
                        href={`/equipment/${row.equipment.id}`}
                        className="text-text hover:underline"
                    >
                        {row.equipment.equipment_code} · {row.equipment.name}
                    </Link>
                ) : (
                    `#${row.equipment_id}`
                ),
        },
        {
            key: 'worker',
            header: 'Worker',
            cell: (row) => row.worker?.name ?? `Worker #${row.worker_id}`,
        },
        {
            key: 'since',
            header: 'Out since',
            cell: (row) => new Date(row.checked_out_at).toLocaleString(),
        },
        {
            key: 'reason',
            header: 'Reason / zone',
            cell: (row) =>
                `${row.reason ?? '—'}${row.zone ? ` · ${row.zone.name}` : ''}`,
        },
        {
            key: 'expected',
            header: 'Expected back',
            cell: (row) =>
                row.expected_return_at
                    ? new Date(row.expected_return_at).toLocaleString()
                    : '—',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => {
                const isOpen = row.returned_at === null;
                const isOverdue = row.is_overdue_return === true;

                return (
                    <div className="flex flex-wrap gap-1">
                        {isOpen ? (
                            <CustodyBadge
                                state={
                                    isOverdue ? 'overdue_return' : 'checked_out'
                                }
                            />
                        ) : (
                            <StatusPill
                                label={
                                    row.returned_at
                                        ? `Returned ${new Date(row.returned_at).toLocaleDateString()}`
                                        : 'Returned'
                                }
                                tone="neutral"
                                showDot={false}
                            />
                        )}
                        <OverdueBadge isReturnOverdue={isOverdue} />
                    </div>
                );
            },
        },
        {
            key: 'actions',
            header: '',
            className: 'w-24 text-right',
            cell: (row) =>
                canManage && row.returned_at === null ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={() => setReturnTarget(row)}
                    >
                        Return
                    </Button>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Equipment checkouts" />
            <SettingsPageShell
                title="Checkouts"
                description="Open custody and return history across all equipment."
                actions={
                    <Button asChild variant="outline" size="sm">
                        <Link href="/equipment">Equipment list</Link>
                    </Button>
                }
                filters={
                    <>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={filters.open ? 'default' : 'outline'}
                                onClick={() => {
                                    cancelDebounce();
                                    applyFilters({ open: true });
                                }}
                            >
                                Open
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={!filters.open ? 'default' : 'outline'}
                                onClick={() => {
                                    cancelDebounce();
                                    applyFilters({ open: false });
                                }}
                            >
                                History
                            </Button>
                        </div>
                        <Input
                            value={search}
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);
                                debouncedApplySearch(value);
                            }}
                            placeholder="Worker, code, reason…"
                            className="w-56"
                            aria-label="Search checkouts"
                        />
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={checkouts.data}
                    rowKey={(row) => row.id}
                    meta={checkouts.meta}
                    pageUrl="/equipment/checkouts"
                    queryParams={{
                        open: filters.open ? '1' : '0',
                        search: search || undefined,
                    }}
                    emptyTitle="No checkouts"
                    emptyDescription={
                        filters.open
                            ? 'No open checkouts.'
                            : 'No checkout history.'
                    }
                />
            </SettingsPageShell>

            <ReturnDialog
                open={returnTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReturnTarget(null);
                    }
                }}
                checkout={returnTarget}
                equipmentLabel={
                    returnTarget?.equipment
                        ? `${returnTarget.equipment.equipment_code} · ${returnTarget.equipment.name}`
                        : undefined
                }
            />
        </>
    );
}

EquipmentCheckoutsIndex.layout = {
    breadcrumbs: [
        { title: 'Equipment', href: '/equipment' },
        { title: 'Checkouts', href: '/equipment/checkouts' },
    ],
};
