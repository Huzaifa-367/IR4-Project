import { Head } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useState } from 'react';
import { AuditDiff } from '@/components/ir4/audit-diff';
import { AuditEventBadge } from '@/components/ir4/audit-event-badge';
import { Pagination } from '@/components/ir4/pagination';
import { RequirePermission } from '@/components/ir4/require-permission';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
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

        visitFilters('/settings/audit-log', {
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

    const exportQuery = new URLSearchParams(
        Object.entries(queryParams).filter((entry): entry is [string, string] =>
            Boolean(entry[1]),
        ),
    ).toString();

    return (
        <RequirePermission permission="view-audit-log">
            <Head title="Audit log" />
            <SettingsPageShell
                title="Audit Log"
                description={`${auditLogs.meta.total} append-only events. Sensitive values are masked before persistence.`}
                actions={
                    <Button asChild variant="outline">
                        <a href={`/settings/audit-log/export?${exportQuery}`}>
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
                <div className="overflow-hidden rounded-[var(--radius-sm)] border border-border bg-surface shadow-[var(--shadow-card)]">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-sm">
                            <thead className="bg-surface-2 text-left">
                                <tr>
                                    <th className="px-4 py-3 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                        Time
                                    </th>
                                    <th className="px-4 py-3 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                        Event
                                    </th>
                                    <th className="px-4 py-3 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                        Actor
                                    </th>
                                    <th className="px-4 py-3 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                        Subject
                                    </th>
                                    <th className="px-4 py-3 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                        Description
                                    </th>
                                    <th className="px-4 py-3 text-[11px] font-semibold tracking-wide text-text-dim uppercase">
                                        Details
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {auditLogs.data.map((log: AuditLog) => (
                                    <tr
                                        key={log.id}
                                        className="border-t border-border align-top"
                                    >
                                        <td className="px-4 py-3 text-xs whitespace-nowrap text-text-faint">
                                            {new Date(
                                                log.occurred_at,
                                            ).toLocaleString()}
                                        </td>
                                        <td className="px-4 py-3">
                                            <AuditEventBadge
                                                event={log.event}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-text-dim">
                                            {log.user?.name ?? 'System'}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-text-dim">
                                            {log.auditable_label
                                                ? `${log.auditable_label} #${log.auditable_id}`
                                                : '—'}
                                        </td>
                                        <td className="max-w-sm px-4 py-3 text-text-dim">
                                            {log.description ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <details className="min-w-80">
                                                <summary className="cursor-pointer text-xs text-[color:var(--accent)]">
                                                    View diff and request
                                                </summary>
                                                <div className="mt-3 space-y-3">
                                                    <AuditDiff
                                                        oldValues={
                                                            log.old_values
                                                        }
                                                        newValues={
                                                            log.new_values
                                                        }
                                                    />
                                                    <p className="text-xs text-text-faint">
                                                        IP:{' '}
                                                        {log.ip_address ?? '—'}{' '}
                                                        · Route:{' '}
                                                        {log.route ?? '—'}
                                                    </p>
                                                    <p className="max-w-xl text-xs break-all text-text-faint">
                                                        {log.user_agent ?? '—'}
                                                    </p>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {auditLogs.data.length === 0 && (
                        <p className="p-8 text-center text-sm text-text-faint">
                            No audit events match these filters.
                        </p>
                    )}
                    <Pagination
                        meta={auditLogs.meta}
                        pageUrl="/settings/audit-log"
                        params={queryParams}
                    />
                </div>
            </SettingsPageShell>
        </RequirePermission>
    );
}
