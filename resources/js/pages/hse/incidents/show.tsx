import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { DetailField, FactTile } from '@/components/ir4/fact-tile';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { cn } from '@/lib/utils';
import type { HseIncident, HseOption } from '@/types/hse';

type WorkerOption = { id: number; name: string };

type Props = {
    incident: HseIncident;
    workers: WorkerOption[];
    typeOptions: HseOption[];
    severityOptions: HseOption[];
    canLog: boolean;
    canClassify: boolean;
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

function severityTone(severity: string | null): StatusPillTone {
    if (severity === 'critical' || severity === 'high') {
        return 'crit';
    }

    if (severity === 'medium') {
        return 'warn';
    }

    if (severity === 'low') {
        return 'ok';
    }

    return 'neutral';
}

function statusTone(status: string): StatusPillTone {
    if (status === 'closed') {
        return 'ok';
    }

    if (status === 'classified') {
        return 'accent';
    }

    if (status === 'open' || status === 'under_review') {
        return 'warn';
    }

    return 'neutral';
}

export default function IncidentShow({
    incident,
    workers,
    typeOptions,
    severityOptions,
    canLog,
    canClassify,
}: Props) {
    const canClassifyNow =
        canClassify &&
        (incident.status === 'open' || incident.status === 'under_review');
    const canCloseClassified =
        canClassify && incident.status === 'classified';
    const canFalseAlarmClose =
        canClassify &&
        (incident.status === 'open' || incident.status === 'under_review');
    const canReopen = canClassify && incident.status === 'classified';

    const [incidentType, setIncidentType] = useState(
        typeOptions[0]?.value ?? '',
    );
    const [severity, setSeverity] = useState(
        severityOptions[0]?.value ?? '',
    );
    const [involvedWorkerId, setInvolvedWorkerId] = useState('');

    const heroToneClass =
        incident.severity === 'critical' || incident.severity === 'high'
            ? 'bg-[color:var(--crit)]'
            : incident.severity === 'medium' ||
                incident.status === 'open' ||
                incident.status === 'under_review'
              ? 'bg-[color:var(--warn)]'
              : incident.status === 'classified'
                ? 'bg-[color:var(--accent)]'
                : incident.status === 'closed'
                  ? 'bg-[color:var(--ok)]'
                  : 'bg-[color:var(--accent)]';

    return (
        <>
            <Head title={incident.incident_number} />
            <div className="mx-auto flex max-w-6xl flex-col gap-4 p-4 md:p-5">
                <header className="overflow-hidden rounded-[var(--radius)] border border-border bg-surface shadow-[var(--shadow-card)]">
                    <div
                        className={cn('h-1.5 w-full', heroToneClass)}
                        aria-hidden
                    />
                    <div className="flex flex-wrap items-start justify-between gap-4 p-4 md:p-5">
                        <div className="min-w-0 space-y-2">
                            <span className="inline-flex items-center rounded-pill bg-surface-3 px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase text-text-dim">
                                {incident.source_label}
                            </span>
                            <h1 className="font-display text-2xl font-semibold tracking-tight text-text md:text-3xl">
                                {incident.incident_number}
                            </h1>
                            <div className="flex flex-wrap gap-1.5">
                                <StatusPill
                                    label={incident.status_label}
                                    tone={statusTone(incident.status)}
                                />
                                {incident.severity_label ? (
                                    <StatusPill
                                        label={incident.severity_label}
                                        tone={severityTone(incident.severity)}
                                    />
                                ) : null}
                                {incident.incident_type_label ? (
                                    <StatusPill
                                        label={incident.incident_type_label}
                                        tone="accent"
                                        showDot={false}
                                    />
                                ) : null}
                            </div>
                        </div>
                        <Button asChild variant="outline">
                            <Link href="/incidents">All incidents</Link>
                        </Button>
                    </div>
                </header>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FactTile
                        label="Occurred"
                        value={formatDate(incident.occurred_at)}
                        tone="accent"
                    />
                    <FactTile
                        label="Zone"
                        value={incident.zone_name ?? '—'}
                        tone="neutral"
                    />
                    <FactTile
                        label="Severity"
                        value={incident.severity_label ?? 'Unset'}
                        tone={severityTone(incident.severity)}
                    />
                    <FactTile
                        label="Status"
                        value={incident.status_label}
                        tone={statusTone(incident.status)}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Panel
                        title="Details"
                        subtitle="Nature and response"
                        className="xl:col-span-7"
                    >
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <DetailField
                                label="Type"
                                value={incident.incident_type_label}
                            />
                            <DetailField
                                label="Camera"
                                value={incident.camera_name}
                            />
                            {incident.alert_id ? (
                                <DetailField
                                    label="Source alert"
                                    value={
                                        <Link
                                            href="/alerts"
                                            className="text-[color:var(--accent)] hover:underline"
                                        >
                                            Alert #{incident.alert_id}
                                        </Link>
                                    }
                                />
                            ) : null}
                            <div className="sm:col-span-2">
                                <DetailField
                                    label="Nature of incident"
                                    value={incident.nature_of_incident}
                                />
                            </div>
                            {incident.immediate_action ? (
                                <div className="sm:col-span-2">
                                    <DetailField
                                        label="Immediate action"
                                        value={incident.immediate_action}
                                    />
                                </div>
                            ) : null}
                            {incident.corrective_action ? (
                                <div className="sm:col-span-2">
                                    <DetailField
                                        label="Corrective action"
                                        value={incident.corrective_action}
                                    />
                                </div>
                            ) : null}
                            {incident.close_note ? (
                                <div className="sm:col-span-2">
                                    <DetailField
                                        label="Close note"
                                        value={incident.close_note}
                                    />
                                </div>
                            ) : null}
                        </dl>
                    </Panel>

                    <Panel
                        title="Provenance"
                        subtitle="Who touched this record"
                        className="xl:col-span-5"
                    >
                        <dl className="grid gap-2 text-sm">
                            <DetailField
                                label="Created"
                                value={`${formatDate(incident.created_at)}${incident.created_by_name ? ` · ${incident.created_by_name}` : ''}`}
                            />
                            {incident.classified_at ? (
                                <DetailField
                                    label="Classified"
                                    value={`${formatDate(incident.classified_at)}${incident.classified_by_name ? ` · ${incident.classified_by_name}` : ''}`}
                                />
                            ) : null}
                            {incident.closed_at ? (
                                <DetailField
                                    label="Closed"
                                    value={`${formatDate(incident.closed_at)}${incident.closed_by_name ? ` · ${incident.closed_by_name}` : ''}`}
                                />
                            ) : null}
                        </dl>
                    </Panel>
                </div>

                {(canReopen || canCloseClassified || canFalseAlarmClose) && (
                    <Panel title="Actions">
                        <div className="flex flex-wrap gap-2">
                            {canReopen && (
                                <Form
                                    action={`/incidents/${incident.id}/reopen`}
                                    method="post"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            disabled={processing}
                                        >
                                            Reopen to under review
                                        </Button>
                                    )}
                                </Form>
                            )}
                            {canCloseClassified && (
                                <Form
                                    action={`/incidents/${incident.id}/close`}
                                    method="post"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Close classified incident
                                        </Button>
                                    )}
                                </Form>
                            )}
                            {canFalseAlarmClose && (
                                <Form
                                    action={`/incidents/${incident.id}/close`}
                                    method="post"
                                    className="flex flex-wrap items-end gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-1">
                                                <Label htmlFor="close_note">
                                                    False-alarm close note
                                                </Label>
                                                <Input
                                                    id="close_note"
                                                    name="close_note"
                                                    required
                                                    minLength={10}
                                                    placeholder="Why this is not a real incident…"
                                                    className="w-72"
                                                />
                                                {errors.close_note && (
                                                    <p className="text-sm text-destructive">
                                                        {errors.close_note}
                                                    </p>
                                                )}
                                            </div>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Close without classification
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </div>
                    </Panel>
                )}

                <div className="grid gap-4 xl:grid-cols-12">
                    <Panel title="Personnel" className="xl:col-span-6">
                        <ul className="flex flex-col gap-2 text-sm">
                            {incident.personnel.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex items-center justify-between gap-2 rounded-md border border-border bg-surface-2/30 px-3 py-2"
                                >
                                    <Link
                                        href={`/workforce/workers/${row.worker_id}`}
                                        className="text-[color:var(--accent)] hover:underline"
                                    >
                                        {row.worker_label}
                                    </Link>
                                    <span className="text-text-dim">
                                        {row.involvement_label}
                                    </span>
                                </li>
                            ))}
                            {incident.personnel.length === 0 && (
                                <li className="text-text-faint">
                                    No personnel attached.
                                </li>
                            )}
                        </ul>
                    </Panel>

                    <Panel title="Evidence" className="xl:col-span-6">
                        <ul className="flex flex-col gap-2 text-sm">
                            {incident.evidence.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-surface-2/30 px-3 py-2"
                                >
                                    <div>
                                        <div className="text-text">
                                            {row.evidence_type_label}
                                            {row.auto_captured
                                                ? ' (auto-captured)'
                                                : ` · ${row.added_by_name ?? 'user'}`}
                                        </div>
                                        {row.payload && (
                                            <pre className="mt-1 max-h-24 overflow-auto text-xs text-text-faint">
                                                {JSON.stringify(row.payload)}
                                            </pre>
                                        )}
                                    </div>
                                    {row.download_url && (
                                        <Button asChild size="sm" variant="outline">
                                            <a href={row.download_url}>
                                                Download
                                            </a>
                                        </Button>
                                    )}
                                </li>
                            ))}
                            {incident.evidence.length === 0 && (
                                <li className="text-text-faint">
                                    No evidence yet.
                                </li>
                            )}
                        </ul>
                        {canLog && incident.status !== 'closed' && (
                            <Form
                                action={`/incidents/${incident.id}/evidence`}
                                method="post"
                                className="mt-3 flex flex-wrap items-end gap-2 border-t border-border pt-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="evidence_type"
                                            value="note"
                                        />
                                        <div className="grid gap-1">
                                            <Label htmlFor="note">
                                                Add note
                                            </Label>
                                            <Input
                                                id="note"
                                                name="note"
                                                required
                                                minLength={3}
                                            />
                                            {errors.note && (
                                                <p className="text-sm text-destructive">
                                                    {errors.note}
                                                </p>
                                            )}
                                        </div>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Attach note
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </Panel>
                </div>

                {canClassifyNow && (
                    <Panel
                        title="Classify"
                        subtitle="Set type, severity, and actions"
                        className="border-[color:var(--accent)]/30"
                    >
                        <Form
                            action={`/incidents/${incident.id}/classify`}
                            method="put"
                            className="grid max-w-xl gap-3"
                            transform={(data) => ({
                                ...data,
                                incident_type: incidentType,
                                severity,
                                personnel: involvedWorkerId
                                    ? [
                                          {
                                              worker_id: involvedWorkerId,
                                              involvement: 'involved',
                                          },
                                      ]
                                    : [],
                            })}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="incident_type">
                                            Type
                                        </Label>
                                        <SearchableSelect
                                            id="incident_type"
                                            required
                                            value={incidentType}
                                            onValueChange={setIncidentType}
                                            options={typeOptions}
                                        />
                                        {errors.incident_type && (
                                            <p className="text-sm text-destructive">
                                                {errors.incident_type}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="severity">
                                            Severity
                                        </Label>
                                        <SearchableSelect
                                            id="severity"
                                            required
                                            value={severity}
                                            onValueChange={setSeverity}
                                            options={severityOptions}
                                        />
                                        {errors.severity && (
                                            <p className="text-sm text-destructive">
                                                {errors.severity}
                                            </p>
                                        )}
                                    </div>
                                    {(
                                        [
                                            [
                                                'nature_of_incident',
                                                'Nature of incident',
                                            ],
                                            [
                                                'immediate_action',
                                                'Immediate action',
                                            ],
                                            [
                                                'corrective_action',
                                                'Corrective action',
                                            ],
                                        ] as const
                                    ).map(([name, label]) => (
                                        <div key={name} className="grid gap-2">
                                            <Label htmlFor={name}>{label}</Label>
                                            <textarea
                                                id={name}
                                                name={name}
                                                required
                                                minLength={10}
                                                rows={3}
                                                className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                                defaultValue={
                                                    (incident[
                                                        name
                                                    ] as string | null) ?? ''
                                                }
                                            />
                                            {errors[name] && (
                                                <p className="text-sm text-destructive">
                                                    {errors[name]}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                    <div className="grid gap-2">
                                        <Label>Involved worker (optional)</Label>
                                        <SearchableSelect
                                            value={involvedWorkerId}
                                            onValueChange={setInvolvedWorkerId}
                                            allowClear
                                            clearLabel="—"
                                            placeholder="—"
                                            options={workers.map((worker) => ({
                                                value: String(worker.id),
                                                label: worker.name,
                                            }))}
                                        />
                                        {(errors.personnel ||
                                            errors[
                                                'personnel.0.worker_id'
                                            ]) && (
                                            <p className="text-sm text-destructive">
                                                {errors.personnel ??
                                                    errors[
                                                        'personnel.0.worker_id'
                                                    ]}
                                            </p>
                                        )}
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Save classification
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Panel>
                )}
            </div>
        </>
    );
}

IncidentShow.layout = {
    breadcrumbs: [{ title: 'Incidents', href: '/incidents' }],
};
