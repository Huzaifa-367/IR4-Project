import { Form, Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useAlertStore } from '@/components/ir4/alert-provider';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { Button } from '@/components/ui/button';
import type { Alert } from '@/types/alert';

type Props = {
    alerts: {
        data: Alert[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: {
        alert_type: string;
        severity: string;
        status: string;
        search: string;
    };
    alertTypes: Array<{ value: string; label: string }>;
    severities: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    canAcknowledge: boolean;
    canResolve: boolean;
    audibleEnabled: boolean;
};

export default function AlertsIndex({
    alerts,
    filters,
    alertTypes,
    severities,
    statuses,
    canAcknowledge,
    canResolve,
}: Props) {
    const { bellCount, status } = useAlertStore();

    return (
        <>
            <Head title="Alerts" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Alert centre"
                        description={`${bellCount} open`}
                    />
                    <LiveStatusPill status={status} />
                </div>

                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get(
                            '/alerts',
                            {
                                alert_type: String(
                                    form.get('alert_type') ?? '',
                                ),
                                severity: String(form.get('severity') ?? ''),
                                status: String(form.get('status') ?? ''),
                                search: String(form.get('search') ?? ''),
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <select
                        name="status"
                        defaultValue={filters.status}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Open + ack</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                    <select
                        name="severity"
                        defaultValue={filters.severity}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All severities</option>
                        {severities.map((severity) => (
                            <option key={severity.value} value={severity.value}>
                                {severity.label}
                            </option>
                        ))}
                    </select>
                    <select
                        name="alert_type"
                        defaultValue={filters.alert_type}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All types</option>
                        {alertTypes.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    <input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search title"
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    />
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Raised
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Severity
                                </th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Title</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {alerts.data.map((alert) => (
                                <tr
                                    key={alert.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        {alert.raised_at}
                                        {alert.occurrences > 1
                                            ? ` ×${alert.occurrences}`
                                            : ''}
                                    </td>
                                    <td className="px-3 py-2">
                                        {alert.severity}
                                    </td>
                                    <td className="px-3 py-2">
                                        {alert.alert_type_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {alert.title}
                                        {alert.payload.suggested_action ? (
                                            <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <span>
                                                    Suggested:{' '}
                                                    {String(
                                                        alert.payload
                                                            .suggested_action,
                                                    )}
                                                </span>
                                                {alert.payload
                                                    .suggested_action ===
                                                    'create_incident' && (
                                                    <Link
                                                        href={`/incidents/create?alert_id=${alert.id}`}
                                                        className="text-primary underline"
                                                    >
                                                        Create incident
                                                    </Link>
                                                )}
                                                {alert.payload
                                                    .suggested_action ===
                                                    'log_lsr' && (
                                                    <Link
                                                        href={`/lsr-violations/create?alert_id=${alert.id}`}
                                                        className="text-primary underline"
                                                    >
                                                        Log LSR
                                                    </Link>
                                                )}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td className="px-3 py-2">
                                        {alert.status}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <div className="flex justify-end gap-1">
                                            {canAcknowledge &&
                                                alert.status === 'open' && (
                                                    <Form
                                                        action={`/alerts/${alert.id}/acknowledge`}
                                                        method="post"
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="secondary"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Ack
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                            {canResolve &&
                                                alert.status !== 'resolved' && (
                                                    <Form
                                                        action={`/alerts/${alert.id}/resolve`}
                                                        method="post"
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Resolve
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {alerts.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No alerts match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-sm text-muted-foreground">
                    Page {alerts.meta.current_page} of {alerts.meta.last_page} ·{' '}
                    {alerts.meta.total} total ·{' '}
                    <Link href="/dashboard" className="underline">
                        Dashboard
                    </Link>
                </p>
            </div>
        </>
    );
}
