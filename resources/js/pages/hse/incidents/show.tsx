import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
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
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={incident.incident_number}
                        description={`${incident.status_label} · ${incident.source_label}`}
                    />
                    <Button asChild variant="outline">
                        <Link href="/incidents">Back</Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <section className="space-y-2 rounded-lg border border-border p-4 text-sm">
                        <div>
                            <span className="text-muted-foreground">
                                Occurred:{' '}
                            </span>
                            {incident.occurred_at}
                        </div>
                        <div>
                            <span className="text-muted-foreground">Zone: </span>
                            {incident.zone_name ?? '—'}
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Nature:{' '}
                            </span>
                            {incident.nature_of_incident ?? '—'}
                        </div>
                        {incident.immediate_action && (
                            <div>
                                <span className="text-muted-foreground">
                                    Immediate action:{' '}
                                </span>
                                {incident.immediate_action}
                            </div>
                        )}
                        {incident.corrective_action && (
                            <div>
                                <span className="text-muted-foreground">
                                    Corrective action:{' '}
                                </span>
                                {incident.corrective_action}
                            </div>
                        )}
                        {incident.close_note && (
                            <div>
                                <span className="text-muted-foreground">
                                    Close note:{' '}
                                </span>
                                {incident.close_note}
                            </div>
                        )}
                    </section>

                    <section className="space-y-3 rounded-lg border border-border p-4">
                        <h2 className="font-medium">Actions</h2>
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
                                className="space-y-2"
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Close classified incident
                                    </Button>
                                )}
                            </Form>
                        )}
                        {canFalseAlarmClose && (
                            <Form
                                action={`/incidents/${incident.id}/close`}
                                method="post"
                                className="space-y-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Label htmlFor="close_note">
                                            False-alarm close note
                                        </Label>
                                        <Input
                                            id="close_note"
                                            name="close_note"
                                            required
                                            minLength={10}
                                            placeholder="Why this is not a real incident…"
                                        />
                                        {errors.close_note && (
                                            <p className="text-sm text-destructive">
                                                {errors.close_note}
                                            </p>
                                        )}
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
                    </section>
                </div>

                <section className="space-y-3 rounded-lg border border-border p-4">
                    <h2 className="font-medium">Personnel</h2>
                    <ul className="space-y-1 text-sm">
                        {incident.personnel.map((row) => (
                            <li key={row.id}>
                                {row.worker_label} — {row.involvement_label}
                            </li>
                        ))}
                        {incident.personnel.length === 0 && (
                            <li className="text-muted-foreground">
                                No personnel attached.
                            </li>
                        )}
                    </ul>
                </section>

                <section className="space-y-3 rounded-lg border border-border p-4">
                    <h2 className="font-medium">Evidence</h2>
                    <ul className="space-y-2 text-sm">
                        {incident.evidence.map((row) => (
                            <li
                                key={row.id}
                                className="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2"
                            >
                                <div>
                                    <div>
                                        {row.evidence_type_label}
                                        {row.auto_captured
                                            ? ' (auto-captured)'
                                            : ` · ${row.added_by_name ?? 'user'}`}
                                    </div>
                                    {row.payload && (
                                        <pre className="mt-1 max-h-24 overflow-auto text-xs text-muted-foreground">
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
                            <li className="text-muted-foreground">
                                No evidence yet.
                            </li>
                        )}
                    </ul>
                    {canLog && incident.status !== 'closed' && (
                        <Form
                            action={`/incidents/${incident.id}/evidence`}
                            method="post"
                            className="grid max-w-md gap-2 border-t border-border pt-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="evidence_type"
                                        value="note"
                                    />
                                    <Label htmlFor="note">Add note</Label>
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
                </section>

                {canClassifyNow && (
                    <section className="space-y-3 rounded-lg border border-border p-4">
                        <h2 className="font-medium">Classify</h2>
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
                    </section>
                )}
            </div>
        </>
    );
}
