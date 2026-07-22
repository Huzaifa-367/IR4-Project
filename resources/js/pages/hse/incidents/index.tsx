import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
import type { PaginatedMeta } from '@/types/hardware';
import type { HseIncident, HseOption, IncidentPrefill } from '@/types/hse';

type ZoneOption = { id: number; name: string };

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
    prefill: IncidentPrefill | null;
    zones: ZoneOption[];
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

function buildLogForm(prefill: IncidentPrefill | null) {
    return {
        alert_id: prefill?.alert_id ? String(prefill.alert_id) : '',
        ppe_violation_id: prefill?.ppe_violation_id
            ? String(prefill.ppe_violation_id)
            : '',
        camera_id: prefill?.camera_id ? String(prefill.camera_id) : '',
        occurred_at:
            prefill?.occurred_at?.slice(0, 16) ??
            new Date().toISOString().slice(0, 16),
        zone_id: prefill?.zone_id ? String(prefill.zone_id) : '',
        nature_of_incident: prefill?.nature_of_incident ?? '',
    };
}

export default function IncidentsIndex({
    incidents,
    filters,
    statusOptions,
    sourceOptions,
    typeOptions,
    severityOptions,
    canLog,
    prefill = null,
    zones = [],
}: Props) {
    const [logOpen, setLogOpen] = useState(() => prefill !== null);
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || ALL);
    const [source, setSource] = useState(filters.source || ALL);
    const [incidentType, setIncidentType] = useState(
        filters.incident_type || ALL,
    );
    const [severity, setSeverity] = useState(filters.severity || ALL);
    const logForm = useForm(buildLogForm(prefill));

    useEffect(() => {
        if (!logOpen) {
            return;
        }

        logForm.setData(buildLogForm(prefill));
        logForm.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset when dialog opens
    }, [logOpen, prefill]);

    function applyFilters(
        patch: Partial<{
            search: string;
            status: string;
            source: string;
            incident_type: string;
            severity: string;
        }> = {},
    ): void {
        const nextSearch = patch.search ?? search;
        const nextStatus = patch.status ?? status;
        const nextSource = patch.source ?? source;
        const nextIncidentType = patch.incident_type ?? incidentType;
        const nextSeverity = patch.severity ?? severity;

        visitFilters('/incidents', {
            search: nextSearch || undefined,
            status: nextStatus === ALL ? undefined : nextStatus,
            source: nextSource === ALL ? undefined : nextSource,
            incident_type:
                nextIncidentType === ALL ? undefined : nextIncidentType,
            severity: nextSeverity === ALL ? undefined : nextSeverity,
        });
    }

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const queryParams = {
        search: search || undefined,
        status: status === ALL ? undefined : status,
        source: source === ALL ? undefined : source,
        incident_type: incidentType === ALL ? undefined : incidentType,
        severity: severity === ALL ? undefined : severity,
    };

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
                        <Button type="button" onClick={() => setLogOpen(true)}>
                            Log incident
                        </Button>
                    ) : undefined
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
                            placeholder="Search number, nature…"
                            className="w-full sm:w-56"
                            aria-label="Search incidents"
                        />
                        <SearchableSelect
                            value={status}
                            onValueChange={(value) => {
                                setStatus(value);
                                cancelDebounce();
                                applyFilters({ status: value });
                            }}
                            placeholder="Status"
                            triggerClassName="w-40"
                            options={[
                                { value: ALL, label: 'All statuses' },
                                ...statusOptions.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={source}
                            onValueChange={(value) => {
                                setSource(value);
                                cancelDebounce();
                                applyFilters({ source: value });
                            }}
                            placeholder="Source"
                            triggerClassName="w-36"
                            options={[
                                { value: ALL, label: 'All sources' },
                                ...sourceOptions.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={incidentType}
                            onValueChange={(value) => {
                                setIncidentType(value);
                                cancelDebounce();
                                applyFilters({ incident_type: value });
                            }}
                            placeholder="Type"
                            triggerClassName="w-40"
                            options={[
                                { value: ALL, label: 'All types' },
                                ...typeOptions.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={severity}
                            onValueChange={(value) => {
                                setSeverity(value);
                                cancelDebounce();
                                applyFilters({ severity: value });
                            }}
                            placeholder="Severity"
                            triggerClassName="w-36"
                            options={[
                                { value: ALL, label: 'All severities' },
                                ...severityOptions.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                })),
                            ]}
                        />
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

            <Dialog open={logOpen} onOpenChange={setLogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Log incident</DialogTitle>
                        <DialogDescription>
                            {prefill
                                ? `Prefill from alert #${prefill.alert_id} — review and submit.`
                                : 'Manual HSE incident. Nothing is auto-created from alerts.'}
                        </DialogDescription>
                    </DialogHeader>

                    {prefill ? (
                        <div className="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm">
                            <p className="font-medium">
                                From alert: {prefill.alert.title}
                            </p>
                            <p className="text-muted-foreground">
                                Type {prefill.alert.alert_type}. Evidence
                                attaches on submit.
                            </p>
                        </div>
                    ) : null}

                    <form
                        className="grid gap-4"
                        onSubmit={(event: FormEvent<HTMLFormElement>) => {
                            event.preventDefault();

                            if (logForm.data.occurred_at === '') {
                                logForm.setError(
                                    'occurred_at',
                                    'Occurred at is required.',
                                );

                                return;
                            }

                            logForm.post('/incidents', {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setLogOpen(false);
                                    logForm.reset();
                                    logForm.clearErrors();
                                },
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="incident-occurred_at">
                                Occurred at
                            </Label>
                            <Input
                                id="incident-occurred_at"
                                type="datetime-local"
                                required
                                value={logForm.data.occurred_at}
                                onChange={(event) =>
                                    logForm.setData(
                                        'occurred_at',
                                        event.target.value,
                                    )
                                }
                            />
                            {logForm.errors.occurred_at ? (
                                <p className="text-sm text-destructive">
                                    {logForm.errors.occurred_at}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="incident-zone_id">Zone</Label>
                            <SearchableSelect
                                id="incident-zone_id"
                                value={logForm.data.zone_id}
                                onValueChange={(value) =>
                                    logForm.setData('zone_id', value)
                                }
                                allowClear
                                clearLabel="—"
                                placeholder="—"
                                options={zones.map((zone) => ({
                                    value: String(zone.id),
                                    label: zone.name,
                                }))}
                            />
                            {logForm.errors.zone_id ? (
                                <p className="text-sm text-destructive">
                                    {logForm.errors.zone_id}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="nature_of_incident">
                                Initial notes
                            </Label>
                            <textarea
                                id="nature_of_incident"
                                rows={4}
                                maxLength={5000}
                                value={logForm.data.nature_of_incident}
                                onChange={(event) =>
                                    logForm.setData(
                                        'nature_of_incident',
                                        event.target.value,
                                    )
                                }
                                className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                            {logForm.errors.nature_of_incident ? (
                                <p className="text-sm text-destructive">
                                    {logForm.errors.nature_of_incident}
                                </p>
                            ) : null}
                        </div>

                        {logForm.errors.alert_id ? (
                            <p className="text-sm text-destructive">
                                {logForm.errors.alert_id}
                            </p>
                        ) : null}
                        {logForm.errors.ppe_violation_id ? (
                            <p className="text-sm text-destructive">
                                {logForm.errors.ppe_violation_id}
                            </p>
                        ) : null}
                        {logForm.errors.camera_id ? (
                            <p className="text-sm text-destructive">
                                {logForm.errors.camera_id}
                            </p>
                        ) : null}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setLogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={logForm.processing}
                            >
                                Submit incident
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

IncidentsIndex.layout = {
    breadcrumbs: [{ title: 'Incidents', href: '/incidents' }],
};
