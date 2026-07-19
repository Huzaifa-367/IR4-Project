import { Form, Head, Link } from '@inertiajs/react';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

function severityTone(severity: string | null): 'ok' | 'warn' | 'crit' {
    if (severity === 'critical' || severity === 'high') {
        return 'crit';
    }

    if (severity === 'medium') {
        return 'warn';
    }

    return 'ok';
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

    return (
        <>
            <Head title={incident.incident_number} />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">{incident.source_label}</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            {incident.incident_number}
                        </h1>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            <StatusPill
                                label={incident.status_label}
                                tone="info"
                            />
                            {incident.severity_label ? (
                                <StatusPill
                                    label={incident.severity_label}
                                    tone={severityTone(incident.severity)}
                                />
                            ) : null}
                        </div>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/incidents">Back</Link>
                    </Button>
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Panel title="Details" className="xl:col-span-7">
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <Field
                                label="Occurred"
                                value={formatDate(incident.occurred_at)}
                            />
                            <Field
                                label="Type"
                                value={incident.incident_type_label}
                            />
                            <Field
                                label="Zone"
                                value={incident.zone_name}
                            />
                            <Field
                                label="Camera"
                                value={incident.camera_name}
                            />
                            {incident.alert_id ? (
                                <div>
                                    <dt className="text-xs text-text-faint">
                                        Source alert
                                    </dt>
                                    <dd className="mt-0.5">
                                        <Link
                                            href="/alerts"
                                            className="text-[color:var(--accent)] hover:underline"
                                        >
                                            Alert #{incident.alert_id}
                                        </Link>
                                    </dd>
                                </div>
                            ) : null}
                            <div className="sm:col-span-2">
                                <Field
                                    label="Nature of incident"
                                    value={incident.nature_of_incident}
                                />
                            </div>
                            {incident.immediate_action ? (
                                <div className="sm:col-span-2">
                                    <Field
                                        label="Immediate action"
                                        value={incident.immediate_action}
                                    />
                                </div>
                            ) : null}
                            {incident.corrective_action ? (
                                <div className="sm:col-span-2">
                                    <Field
                                        label="Corrective action"
                                        value={incident.corrective_action}
                                    />
                                </div>
                            ) : null}
                            {incident.close_note ? (
                                <div className="sm:col-span-2">
                                    <Field
                                        label="Close note"
                                        value={incident.close_note}
                                    />
                                </div>
                            ) : null}
                        </dl>
                    </Panel>

                    <Panel title="Provenance" className="xl:col-span-5">
                        <dl className="grid gap-3 text-sm">
                            <Field
                                label="Created"
                                value={`${formatDate(incident.created_at)}${incident.created_by_name ? ` · ${incident.created_by_name}` : ''}`}
                            />
                            {incident.classified_at ? (
                                <Field
                                    label="Classified"
                                    value={`${formatDate(incident.classified_at)}${incident.classified_by_name ? ` · ${incident.classified_by_name}` : ''}`}
                                />
                            ) : null}
                            {incident.closed_at ? (
                                <Field
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
                                    className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                                >
                                    <Link
                                        href={`/tracking/workers/${row.worker_id}`}
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
                                    className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
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
                    <Panel title="Classify">
                        <Form
                            action={`/incidents/${incident.id}/classify`}
                            method="put"
                            className="grid max-w-xl gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="incident_type">
                                            Type
                                        </Label>
                                        <select
                                            id="incident_type"
                                            name="incident_type"
                                            required
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {typeOptions.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="severity">
                                            Severity
                                        </Label>
                                        <select
                                            id="severity"
                                            name="severity"
                                            required
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {severityOptions.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
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
                                        <select
                                            name="personnel[0][worker_id]"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                            defaultValue=""
                                        >
                                            <option value="">—</option>
                                            {workers.map((worker) => (
                                                <option
                                                    key={worker.id}
                                                    value={worker.id}
                                                >
                                                    {worker.name}
                                                </option>
                                            ))}
                                        </select>
                                        <input
                                            type="hidden"
                                            name="personnel[0][involvement]"
                                            value="involved"
                                        />
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

function Field({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div>
            <dt className="text-xs text-text-faint">{label}</dt>
            <dd className="mt-0.5 text-text">{value ?? '—'}</dd>
        </div>
    );
}

IncidentShow.layout = {
    breadcrumbs: [{ title: 'Incidents', href: '/incidents' }],
};
