import { Head } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useState } from 'react';
import { AuditDiff } from '@/components/ir4/audit-diff';
import { AuditEventBadge } from '@/components/ir4/audit-event-badge';
import { RequirePermission } from '@/components/ir4/require-permission';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { FILTER_SEARCH_DEBOUNCE_MS, visitFilters } from '@/lib/visit-filters';
import settings from '@/routes/settings';
import type { AuditEvent, AuditLog } from '@/types/audit';
import type { PaginatedMeta } from '@/types/hardware';

type FilterValues = {
    event?: string;
    user_id?: string;
    auditable_type?: string;
    from?: string;
    to?: string;
    search?: string;
};

type Props = {
    auditLogs: { data: AuditLog[]; meta: PaginatedMeta };
    filters: FilterValues;
    events: { value: AuditEvent; label: string }[];
    users: { id: number; name: string }[];
    models: { value: string; label: string }[];
};

const ALL = 'all';

export default function AuditLogIndex({
    auditLogs,
    filters,
    events,
    users,
    models,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [event, setEvent] = useState(filters.event || ALL);
    const [userId, setUserId] = useState(filters.user_id || ALL);
    const [auditableType, setAuditableType] = useState(
        filters.auditable_type || ALL,
    );
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const queryParams = {
        search: search || undefined,
        event: event === ALL ? undefined : event,
        user_id: userId === ALL ? undefined : userId,
        auditable_type: auditableType === ALL ? undefined : auditableType,
        from: from || undefined,
        to: to || undefined,
    };

    function applyFilters(patch: Partial<FilterValues> = {}): void {
        const nextSearch = patch.search ?? search;
        const nextEvent = patch.event ?? event;
        const nextUserId = patch.user_id ?? userId;
        const nextAuditableType = patch.auditable_type ?? auditableType;
        const nextFrom = patch.from ?? from;
        const nextTo = patch.to ?? to;

        visitFilters(settings.auditLog.index.url(), {
            search: nextSearch || undefined,
            event: nextEvent === ALL ? undefined : nextEvent,
            user_id: nextUserId === ALL ? undefined : nextUserId,
            auditable_type:
                nextAuditableType === ALL ? undefined : nextAuditableType,
            from: nextFrom || undefined,
            to: nextTo || undefined,
        });
    }

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const exportQuery = Object.fromEntries(
        Object.entries(queryParams).filter((entry): entry is [string, string] =>
            Boolean(entry[1]),
        ),
    );

    const columns: SettingsColumn<AuditLog>[] = [
        {
            key: 'number',
            header: 'Number',
            className: 'w-24',
            cell: (log) => (
                <span className="font-mono text-xs">Audit #{log.id}</span>
            ),
        },
        {
            key: 'time',
            header: 'Time',
            cell: (log) => (
                <span className="text-xs whitespace-nowrap text-text-faint">
                    {new Date(log.occurred_at).toLocaleString()}
                </span>
            ),
        },
        {
            key: 'event',
            header: 'Event',
            cell: (log) => <AuditEventBadge event={log.event} />,
        },
        {
            key: 'actor',
            header: 'Actor',
            cell: (log) => log.user?.name ?? 'System',
        },
        {
            key: 'subject',
            header: 'Subject',
            cell: (log) => (
                <span className="font-mono text-xs text-text-dim">
                    {log.auditable_label
                        ? `${log.auditable_label} #${log.auditable_id}`
                        : '—'}
                </span>
            ),
        },
        {
            key: 'description',
            header: 'Description',
            className: 'max-w-sm',
            cell: (log) => log.description ?? '—',
        },
        {
            key: 'details',
            header: 'Details',
            className: 'min-w-80',
            cell: (log) => (
                <details>
                    <summary className="cursor-pointer text-xs text-[color:var(--accent)]">
                        View diff and request
                    </summary>
                    <div className="mt-3 space-y-3">
                        <AuditDiff
                            oldValues={log.old_values}
                            newValues={log.new_values}
                        />
                        <p className="text-xs text-text-faint">
                            IP: {log.ip_address ?? '—'} · Route:{' '}
                            {log.route ?? '—'}
                        </p>
                        <p className="max-w-xl text-xs break-all text-text-faint">
                            {log.user_agent ?? '—'}
                        </p>
                    </div>
                </details>
            ),
        },
    ];

    return (
        <RequirePermission permission="view-audit-log">
            <Head title="Audit log" />
            <SettingsPageShell
                title="Audit Log"
                description={`${auditLogs.meta.total} append-only events. Sensitive values are masked before persistence.`}
                actions={
                    <Button asChild variant="outline">
                        <a
                            href={settings.auditLog.export.url({
                                query: exportQuery,
                            })}
                        >
                            <Download className="size-4" />
                            Export CSV
                        </a>
                    </Button>
                }
                filters={
                    <>
                        <Input
                            value={search}
                            onChange={(event_) => {
                                const value = event_.target.value;
                                setSearch(value);
                                debouncedApplySearch(value);
                            }}
                            placeholder="Description"
                            className="w-full sm:w-52"
                            aria-label="Search description"
                        />
                        <SearchableSelect
                            value={event}
                            onValueChange={(value) => {
                                setEvent(value);
                                cancelDebounce();
                                applyFilters({ event: value });
                            }}
                            placeholder="Event"
                            triggerClassName="w-40"
                            options={[
                                { value: ALL, label: 'All events' },
                                ...events.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={userId}
                            onValueChange={(value) => {
                                setUserId(value);
                                cancelDebounce();
                                applyFilters({ user_id: value });
                            }}
                            placeholder="User"
                            triggerClassName="w-40"
                            options={[
                                { value: ALL, label: 'All users' },
                                ...users.map((user) => ({
                                    value: String(user.id),
                                    label: user.name,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={auditableType}
                            onValueChange={(value) => {
                                setAuditableType(value);
                                cancelDebounce();
                                applyFilters({ auditable_type: value });
                            }}
                            placeholder="Model"
                            triggerClassName="w-40"
                            options={[
                                { value: ALL, label: 'All models' },
                                ...models.map((model) => ({
                                    value: model.value,
                                    label: model.label,
                                })),
                            ]}
                        />
                        <Input
                            type="date"
                            value={from}
                            onChange={(event_) => setFrom(event_.target.value)}
                            className="w-36"
                            aria-label="From date"
                        />
                        <Input
                            type="date"
                            value={to}
                            onChange={(event_) => setTo(event_.target.value)}
                            className="w-36"
                            aria-label="To date"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => applyFilters()}
                        >
                            <Search className="size-4" />
                            Apply
                        </Button>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={auditLogs.data}
                    rowKey={(log) => log.id}
                    meta={auditLogs.meta}
                    pageUrl={settings.auditLog.index.url()}
                    queryParams={queryParams}
                    emptyTitle="No audit events"
                    emptyDescription="No audit events match these filters."
                />
            </SettingsPageShell>
        </RequirePermission>
    );
}
