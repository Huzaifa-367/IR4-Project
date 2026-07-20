import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { useAlertStore } from '@/components/ir4/alert-provider';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
import type { Alert } from '@/types/alert';
import type { PaginatedMeta } from '@/types/hardware';

type Props = {
    alerts: { data: Alert[]; meta: PaginatedMeta };
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
};

const ALL = 'all';

const SEVERITY_TONE: Record<string, StatusPillTone> = {
    critical: 'crit',
    warning: 'warn',
    info: 'info',
};

const STATUS_TONE: Record<string, StatusPillTone> = {
    open: 'crit',
    acknowledged: 'warn',
    resolved: 'ok',
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
    const { bellCount, status: liveStatus } = useAlertStore();
    const [search, setSearch] = useState(filters.search);
    const [severity, setSeverity] = useState(filters.severity || ALL);
    const [alertType, setAlertType] = useState(filters.alert_type || ALL);
    const [statusFilter, setStatusFilter] = useState(filters.status || ALL);

    function applyFilters(
        patch: Partial<{
            search: string;
            severity: string;
            alert_type: string;
            status: string;
        }> = {},
    ): void {
        const nextSearch = patch.search ?? search;
        const nextSeverity = patch.severity ?? severity;
        const nextAlertType = patch.alert_type ?? alertType;
        const nextStatus = patch.status ?? statusFilter;

        visitFilters('/alerts', {
            search: nextSearch || undefined,
            severity: nextSeverity === ALL ? undefined : nextSeverity,
            alert_type: nextAlertType === ALL ? undefined : nextAlertType,
            status: nextStatus === ALL ? undefined : nextStatus,
        });
    }

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const queryParams = {
        search: search || undefined,
        severity: severity === ALL ? undefined : severity,
        alert_type: alertType === ALL ? undefined : alertType,
        status: statusFilter === ALL ? undefined : statusFilter,
    };

    const columns: SettingsColumn<Alert>[] = [
        {
            key: 'raised',
            header: 'Raised',
            cell: (alert) => (
                <span className="whitespace-nowrap">
                    {new Date(alert.raised_at).toLocaleString()}
                    {alert.occurrences > 1 ? (
                        <span className="ml-1.5 font-mono text-xs text-text-faint">
                            ×{alert.occurrences}
                        </span>
                    ) : null}
                </span>
            ),
        },
        {
            key: 'severity',
            header: 'Severity',
            cell: (alert) => (
                <StatusPill
                    label={alert.severity}
                    tone={SEVERITY_TONE[alert.severity] ?? 'neutral'}
                />
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (alert) => alert.alert_type_label,
        },
        {
            key: 'title',
            header: 'Title',
            cell: (alert) => (
                <div>
                    <span className="text-text">{alert.title}</span>
                    {alert.payload.suggested_action ? (
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-text-faint">
                            <span>
                                Suggested:{' '}
                                {String(alert.payload.suggested_action)}
                            </span>
                            {alert.payload.suggested_action ===
                            'create_incident' ? (
                                <Link
                                    href={`/incidents/create?alert_id=${alert.id}`}
                                    className="text-[color:var(--accent)] hover:underline"
                                >
                                    Create incident
                                </Link>
                            ) : null}
                            {alert.payload.suggested_action === 'log_lsr' ? (
                                <Link
                                    href={`/lsr-violations/create?alert_id=${alert.id}`}
                                    className="text-[color:var(--accent)] hover:underline"
                                >
                                    Log LSR
                                </Link>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (alert) => (
                <StatusPill
                    label={alert.status}
                    tone={STATUS_TONE[alert.status] ?? 'neutral'}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-32 text-right',
            cell: (alert) => (
                <div className="flex justify-end gap-1">
                    {canAcknowledge && alert.status === 'open' && (
                        <Form
                            action={`/alerts/${alert.id}/acknowledge`}
                            method="post"
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="secondary"
                                    disabled={processing}
                                >
                                    Ack
                                </Button>
                            )}
                        </Form>
                    )}
                    {canResolve && alert.status !== 'resolved' && (
                        <Form
                            action={`/alerts/${alert.id}/resolve`}
                            method="post"
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Resolve
                                </Button>
                            )}
                        </Form>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Alerts" />
            <SettingsPageShell
                title="Alert Centre"
                description={`${bellCount} open`}
                actions={<LiveStatusPill status={liveStatus} />}
                filters={
                    <>
                        <Input
                            value={search}
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);
                                debouncedApplySearch(value);
                            }}
                            placeholder="Search title…"
                            className="w-full sm:w-56"
                            aria-label="Search alerts"
                        />
                        <Select
                            value={statusFilter}
                            onValueChange={(value) => {
                                setStatusFilter(value);
                                cancelDebounce();
                                applyFilters({ status: value });
                            }}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        Open + ack
                                    </SelectItem>
                                    {statuses.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select
                            value={severity}
                            onValueChange={(value) => {
                                setSeverity(value);
                                cancelDebounce();
                                applyFilters({ severity: value });
                            }}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Severity" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All severities
                                    </SelectItem>
                                    {severities.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select
                            value={alertType}
                            onValueChange={(value) => {
                                setAlertType(value);
                                cancelDebounce();
                                applyFilters({ alert_type: value });
                            }}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All types
                                    </SelectItem>
                                    {alertTypes.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={alerts.data}
                    rowKey={(alert) => alert.id}
                    meta={alerts.meta}
                    pageUrl="/alerts"
                    queryParams={queryParams}
                    emptyTitle="No alerts"
                    emptyDescription="No alerts match these filters."
                />
            </SettingsPageShell>

            <div className="px-4 pb-4 md:px-6">
                <Link
                    href="/dashboard"
                    className="text-sm text-[color:var(--accent)] hover:underline"
                >
                    ← Dashboard
                </Link>
            </div>
        </>
    );
}
