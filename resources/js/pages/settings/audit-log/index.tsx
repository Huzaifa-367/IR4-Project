import { Head, Link } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import Heading from '@/components/heading';
import { AuditDiff } from '@/components/ir4/audit-diff';
import { AuditEventBadge } from '@/components/ir4/audit-event-badge';
import { RequirePermission } from '@/components/ir4/require-permission';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AuditEvent, AuditLog } from '@/types/audit';

type FilterValues = {
    event?: string;
    user_id?: string;
    auditable_type?: string;
    from?: string;
    to?: string;
    search?: string;
};

type Props = {
    auditLogs: {
        data: AuditLog[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: FilterValues;
    events: { value: AuditEvent; label: string }[];
    users: { id: number; name: string }[];
    models: { value: string; label: string }[];
};

const selectClassName =
    'h-9 rounded-md border border-input bg-background px-3 text-sm';

function buildPageUrl(filters: FilterValues, page: number): string {
    const parameters: URLSearchParams = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]: [string, string?]) => {
        if (value) {
            parameters.set(key, value);
        }
    });
    parameters.set('page', String(page));

    return `/settings/audit-log?${parameters.toString()}`;
}

export default function AuditLogIndex({
    auditLogs,
    filters,
    events,
    users,
    models,
}: Props) {
    const exportQuery: string = new URLSearchParams(
        Object.entries(filters).filter(
            (entry: [string, string?]): entry is [string, string] =>
                Boolean(entry[1]),
        ),
    ).toString();

    return (
        <RequirePermission permission="view-audit-log">
            <Head title="Audit log" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Audit log"
                        description={`${auditLogs.meta.total} append-only events. Sensitive values are masked before persistence.`}
                    />
                    <Button asChild variant="outline">
                        <a href={`/settings/audit-log/export?${exportQuery}`}>
                            <Download className="size-4" />
                            Export CSV
                        </a>
                    </Button>
                </div>
                <form
                    method="get"
                    className="grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-3 xl:grid-cols-7"
                >
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Description"
                    />
                    <select
                        name="event"
                        defaultValue={filters.event ?? ''}
                        className={selectClassName}
                    >
                        <option value="">All events</option>
                        {events.map((event) => (
                            <option key={event.value} value={event.value}>
                                {event.label}
                            </option>
                        ))}
                    </select>
                    <select
                        name="user_id"
                        defaultValue={filters.user_id ?? ''}
                        className={selectClassName}
                    >
                        <option value="">All users</option>
                        {users.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="auditable_type"
                        defaultValue={filters.auditable_type ?? ''}
                        className={selectClassName}
                    >
                        <option value="">All models</option>
                        {models.map((model) => (
                            <option key={model.value} value={model.value}>
                                {model.label}
                            </option>
                        ))}
                    </select>
                    <Input type="date" name="from" defaultValue={filters.from} />
                    <Input type="date" name="to" defaultValue={filters.to} />
                    <Button type="submit">
                        <Search className="size-4" />
                        Filter
                    </Button>
                </form>
                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[900px] text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Time</th>
                                <th className="px-4 py-3 font-medium">Event</th>
                                <th className="px-4 py-3 font-medium">Actor</th>
                                <th className="px-4 py-3 font-medium">Subject</th>
                                <th className="px-4 py-3 font-medium">
                                    Description
                                </th>
                                <th className="px-4 py-3 font-medium">
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
                                    <td className="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">
                                        {new Date(
                                            log.occurred_at,
                                        ).toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        <AuditEventBadge event={log.event} />
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.user?.name ?? 'System'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {log.auditable_label
                                            ? `${log.auditable_label} #${log.auditable_id}`
                                            : '—'}
                                    </td>
                                    <td className="max-w-sm px-4 py-3">
                                        {log.description ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <details className="min-w-80">
                                            <summary className="cursor-pointer text-xs text-cyan-300">
                                                View diff and request
                                            </summary>
                                            <div className="mt-3 space-y-3">
                                                <AuditDiff
                                                    oldValues={log.old_values}
                                                    newValues={log.new_values}
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    IP: {log.ip_address ?? '—'} ·
                                                    Route: {log.route ?? '—'}
                                                </p>
                                                <p className="max-w-xl break-all text-xs text-muted-foreground">
                                                    {log.user_agent ?? '—'}
                                                </p>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {auditLogs.data.length === 0 && (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No audit events match these filters.
                        </p>
                    )}
                </div>
                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        Page {auditLogs.meta.current_page} of{' '}
                        {auditLogs.meta.last_page}
                    </p>
                    <div className="flex gap-2">
                        {auditLogs.meta.current_page > 1 && (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={buildPageUrl(
                                        filters,
                                        auditLogs.meta.current_page - 1,
                                    )}
                                >
                                    Previous
                                </Link>
                            </Button>
                        )}
                        {auditLogs.meta.current_page <
                            auditLogs.meta.last_page && (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={buildPageUrl(
                                        filters,
                                        auditLogs.meta.current_page + 1,
                                    )}
                                >
                                    Next
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </RequirePermission>
    );
}
