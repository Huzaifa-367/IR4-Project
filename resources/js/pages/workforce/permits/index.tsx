import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
import type {
    PaginatedPermits,
    PermitListItem,
    PermitOption,
} from '@/types/permit';

type Props = {
    permits: PaginatedPermits;
    filters: {
        search: string;
        status: string;
        sort: string;
        direction: string;
    };
    statusOptions: PermitOption[];
    canRequest: boolean;
};

const ALL = 'all';

const TYPE_COLOUR_CLASS: Record<string, string> = {
    red: 'bg-red-500',
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    yellow: 'bg-yellow-500',
    orange: 'bg-orange-500',
    purple: 'bg-purple-500',
    cyan: 'bg-cyan-500',
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

function formatCountdown(validTo: string | null): string {
    if (!validTo) {
        return '—';
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

export default function PermitsIndex({
    permits,
    filters,
    statusOptions,
    canRequest,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || ALL);

    function applyFilters(
        patch: Partial<{ search: string; status: string }> = {},
    ): void {
        const nextSearch = patch.search ?? search;
        const nextStatus = patch.status ?? status;

        visitFilters('/workforce/permits', {
            search: nextSearch || undefined,
            status: nextStatus === ALL ? undefined : nextStatus,
        });
    }

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const queryParams = {
        search: search || undefined,
        status: status === ALL ? undefined : status,
    };

    const columns: SettingsColumn<PermitListItem>[] = [
        {
            key: 'number',
            header: 'Number',
            cell: (row) => (
                <span className="font-mono text-xs">{row.permit_number}</span>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (row) => (
                <span className="inline-flex items-center gap-2">
                    {row.type?.colour_token ? (
                        <span
                            className={`h-2 w-2 shrink-0 rounded-full ${TYPE_COLOUR_CLASS[row.type.colour_token] ?? 'bg-muted-foreground'}`}
                            aria-hidden
                        />
                    ) : null}
                    {row.type?.name ?? '—'}
                </span>
            ),
        },
        {
            key: 'zone',
            header: 'Zone',
            cell: (row) => row.zone?.name ?? '—',
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
        {
            key: 'valid_to',
            header: 'Valid to',
            cell: (row) => formatCountdown(row.valid_to),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-20 text-right',
            cell: (row) => (
                <Button asChild size="sm" variant="ghost">
                    <Link href={`/workforce/permits/${row.id}`}>Open</Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Permits to Work" />
            <SettingsPageShell
                eyebrow="Workforce"
                title="Permits to Work"
                description="GI 2.100-aligned permit board. Draft → inspect → gas → issue."
                actions={
                    canRequest ? (
                        <Button asChild>
                            <Link href="/workforce/permits/create">Request permit</Link>
                        </Button>
                    ) : undefined
                }
                filters={
                    <div className="flex flex-wrap items-center gap-2">
                        <Input
                            value={search}
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);
                                debouncedApplySearch(value);
                            }}
                            placeholder="Search number, task…"
                            className="w-full sm:w-56"
                            aria-label="Search permits"
                        />
                        <select
                            value={status}
                            onChange={(event) => {
                                const value = event.target.value;
                                setStatus(value);
                                cancelDebounce();
                                applyFilters({ status: value });
                            }}
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            aria-label="Filter by status"
                        >
                            <option value={ALL}>All statuses</option>
                            {statusOptions.map((option) => (
                                <option
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={permits.data}
                    rowKey={(row) => row.id}
                    meta={permits.meta}
                    pageUrl="/workforce/permits"
                    queryParams={queryParams}
                    emptyTitle="No permits"
                    emptyDescription="No permits match these filters."
                />
            </SettingsPageShell>
        </>
    );
}

PermitsIndex.layout = {
    breadcrumbs: [
        { title: 'Workforce', href: '/workforce/workers' },
        { title: 'Permits', href: '/workforce/permits' },
    ],
};
