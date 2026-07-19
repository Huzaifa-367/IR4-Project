import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
import type { PaginatedMeta } from '@/types/hardware';
import type { HseIncident, HseOption } from '@/types/hse';

type Props = {
    incidents: {
        data: HseIncident[];
        meta: PaginatedMeta;
    };
    filters: {
        search: string;
        status: string;
        source: string;
        incident_type: string;
        severity: string;
        sort: string;
        direction: string;
    };
    statusOptions: HseOption[];
    sourceOptions: HseOption[];
    typeOptions: HseOption[];
    severityOptions: HseOption[];
    canLog: boolean;
    canClassify: boolean;
};

const ALL = 'all';

const STATUS_TONE: Record<string, StatusPillTone> = {
    open: 'crit',
    under_review: 'warn',
    classified: 'accent',
    closed: 'ok',
};

function severityTone(severity: string | null): StatusPillTone {
    if (severity === 'critical' || severity === 'high') {
        return 'crit';
    }

    if (severity === 'medium') {
        return 'warn';
    }

    return 'neutral';
}

export default function IncidentsIndex({
    incidents,
    filters,
    statusOptions,
    sourceOptions,
    typeOptions,
    severityOptions,
    canLog,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || ALL);
    const [source, setSource] = useState(filters.source || ALL);
    const [incidentType, setIncidentType] = useState(
        filters.incident_type || ALL,
    );
    const [severity, setSeverity] = useState(filters.severity || ALL);

    const queryParams = {
        search: search || undefined,
        status: status === ALL ? undefined : status,
        source: source === ALL ? undefined : source,
        incident_type: incidentType === ALL ? undefined : incidentType,
        severity: severity === ALL ? undefined : severity,
    };

    function applyFilters(): void {
        router.get('/incidents', queryParams, {
            preserveState: true,
            replace: true,
        });
    }

    const columns: SettingsColumn<HseIncident>[] = [
        {
            key: 'number',
            header: 'Number',
            cell: (row) => (
                <span className="font-mono text-xs">{row.incident_number}</span>
            ),
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
            key: 'severity',
            header: 'Severity',
            cell: (row) =>
                row.severity_label ? (
                    <StatusPill
                        label={row.severity_label}
                        tone={severityTone(row.severity)}
                    />
                ) : (
                    '—'
                ),
        },
        { key: 'source', header: 'Source', cell: (row) => row.source_label },
        {
            key: 'occurred',
            header: 'Occurred',
            cell: (row) => row.occurred_at ?? '—',
        },
        { key: 'zone', header: 'Zone', cell: (row) => row.zone_name ?? '—' },
        {
            key: 'actions',
            header: '',
            className: 'w-20 text-right',
            cell: (row) => (
                <Button asChild size="sm" variant="ghost">
                    <Link href={`/incidents/${row.id}`}>Open</Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="HSE Incidents" />
            <SettingsPageShell
                title="HSE Incidents"
                description="User-authored safety records. Alerts may prefill — nothing is saved until submit."
                actions={
                    canLog ? (
                        <Button asChild>
                            <Link href="/incidents/create">Log incident</Link>
                        </Button>
                    ) : undefined
                }
                filters={
                    <>
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search number, nature…"
                            className="w-full sm:w-56"
                            aria-label="Search incidents"
                        />
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All statuses
                                    </SelectItem>
                                    {statusOptions.map((option) => (
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
                        <Select value={source} onValueChange={setSource}>
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Source" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All sources
                                    </SelectItem>
                                    {sourceOptions.map((option) => (
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
                            value={incidentType}
                            onValueChange={setIncidentType}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All types
                                    </SelectItem>
                                    {typeOptions.map((option) => (
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
                        <Select value={severity} onValueChange={setSeverity}>
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Severity" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All severities
                                    </SelectItem>
                                    {severityOptions.map((option) => (
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
                        <Button
                            type="button"
                            variant="outline"
                            onClick={applyFilters}
                        >
                            Apply
                        </Button>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={incidents.data}
                    rowKey={(row) => row.id}
                    meta={incidents.meta}
                    pageUrl="/incidents"
                    queryParams={queryParams}
                    emptyTitle="No incidents"
                    emptyDescription="No incidents match these filters."
                />
            </SettingsPageShell>
        </>
    );
}
