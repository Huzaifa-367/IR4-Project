import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
import type { PaginatedMeta } from '@/types/hardware';

type WorkOrderRow = {
    id: number;
    reference: string;
    description: string | null;
    status: string;
    zone: { id: number; name: string } | null;
    permits_count: number;
    created_at: string | null;
};

type Props = {
    workOrders: {
        data: WorkOrderRow[];
        meta: PaginatedMeta;
    };
    filters: {
        search: string;
        sort: string;
        direction: string;
    };
    canCreate: boolean;
};

export default function WorkOrdersIndex({
    workOrders,
    filters,
    canCreate,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    function applySearch(value: string): void {
        visitFilters('/workforce/work-orders', {
            search: value || undefined,
        });
    }

    const [debouncedApplySearch] = useDebouncedCallback(
        applySearch,
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const columns: SettingsColumn<WorkOrderRow>[] = [
        {
            key: 'reference',
            header: 'Reference',
            cell: (row) => (
                <Link
                    href={`/workforce/work-orders/${row.id}`}
                    className="font-mono text-xs font-medium hover:underline"
                >
                    {row.reference}
                </Link>
            ),
        },
        {
            key: 'zone',
            header: 'Zone',
            cell: (row) => row.zone?.name ?? '—',
        },
        {
            key: 'permits',
            header: 'Permits',
            className: 'text-right font-mono tabular-nums',
            cell: (row) => row.permits_count,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => row.status,
        },
        {
            key: 'actions',
            header: '',
            className: 'w-20 text-right',
            cell: (row) => (
                <Button asChild size="sm" variant="ghost">
                    <Link href={`/workforce/work-orders/${row.id}`}>Open</Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Work orders" />
            <SettingsPageShell
                eyebrow="Workforce"
                title="Work orders"
                description="Group related permits under a shared work order reference."
                actions={
                    canCreate ? (
                        <Button asChild>
                            <Link href="/workforce/work-orders/create">
                                New work order
                            </Link>
                        </Button>
                    ) : undefined
                }
                filters={
                    <Input
                        value={search}
                        onChange={(event) => {
                            const value = event.target.value;
                            setSearch(value);
                            debouncedApplySearch(value);
                        }}
                        placeholder="Search reference…"
                        className="w-full sm:w-56"
                        aria-label="Search work orders"
                    />
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={workOrders.data}
                    rowKey={(row) => row.id}
                    meta={workOrders.meta}
                    pageUrl="/workforce/work-orders"
                    emptyTitle="No work orders"
                    emptyDescription="Create a work order to link permits for a job package."
                />
            </SettingsPageShell>
        </>
    );
}
