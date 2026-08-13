import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { useLivePartialReload } from '@/hooks/use-live-partial-reload';
import { permitTypeDotClass } from '@/lib/permit-colours';
import { FILTER_SEARCH_DEBOUNCE_MS, visitFilters } from '@/lib/visit-filters';
import permitRoutes from '@/routes/permits';
import tracking from '@/routes/tracking';
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
    useLivePartialReload({
        channel: 'permits',
        events: ['.PermitUpdated'],
        only: ['permits'],
    });
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || ALL);

    function applyFilters(
        patch: Partial<{ search: string; status: string }> = {},
    ): void {
        const nextSearch = patch.search ?? search;
        const nextStatus = patch.status ?? status;

        visitFilters(permitRoutes.index.url(), {
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
                            className={`h-2 w-2 shrink-0 rounded-full ${permitTypeDotClass(row.type.colour_token)}`}
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
                    <Link href={permitRoutes.show(row.uuid)}>Open</Link>
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
                description="History register — live status lives on the permit board."
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={permitRoutes.board()}>Live board</Link>
                        </Button>
                        {canRequest ? (
                            <Button asChild>
                                <Link href={permitRoutes.create()}>
                                    Request permit
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                }
                filters={
                    <>
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
                        <SearchableSelect
                            value={status}
                            onValueChange={(value) => {
                                setStatus(value);
                                cancelDebounce();
                                applyFilters({ status: value });
                            }}
                            triggerClassName="w-44"
                            placeholder="All statuses"
                            options={[
                                { value: ALL, label: 'All statuses' },
                                ...statusOptions,
                            ]}
                        />
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={permits.data}
                    rowKey={(row) => row.id}
                    meta={permits.meta}
                    pageUrl={permitRoutes.index.url()}
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
        { title: 'Workforce', href: tracking.workers.index() },
        { title: 'Permits', href: permitRoutes.board() },
    ],
};
