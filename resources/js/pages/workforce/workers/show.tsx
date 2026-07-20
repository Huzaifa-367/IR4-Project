import { Form, Head, Link } from '@inertiajs/react';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import {
    WorkerDocumentsPanel,
    type DocumentChecklistItem,
    type PermitReadinessRow,
    type ReadinessSummary,
} from '@/components/ir4/worker-documents-panel';
import { Button } from '@/components/ui/button';
import type { Worker } from '@/types/worker';

type TagHistoryRow = {
    id: number;
    tag_uid: string;
    status: string;
    status_label: string;
    assigned_at: string | null;
};

type EntryExitRow = {
    id: number;
    direction: string;
    occurred_at: string | null;
    source: string;
    gate_zone_name: string | null;
    correction_note: string | null;
};

type PortableDeviceRow = {
    id: number;
    device_type: string;
    make_model: string | null;
    serial_number: string | null;
    status: string;
    approved_at: string | null;
    revoked_at: string | null;
    revoke_reason: string | null;
};

type IncidentRow = {
    id: number;
    incident_number: string;
    status_label: string;
    involvement_label: string;
};

type LsrRow = {
    id: number;
    category_label: string;
    status_label: string;
    occurred_at: string | null;
};

type DocumentRow = {
    id: number;
    type_name: string;
    document_number: string | null;
    issuing_body: string | null;
    issued_at: string | null;
    expires_at: string | null;
    verification_status: string;
    verification_status_label: string;
    has_file: boolean;
    download_url: string | null;
};

type DocumentTypeOption = {
    id: number;
    name: string;
    code: string;
    requires_file: boolean;
    requires_expiry?: boolean;
    category?: string | null;
};

type Props = {
    worker: Worker;
    onboarding?: boolean;
    canManage: boolean;
    canSeeEntryExit: boolean;
    canSeePortableDevices: boolean;
    canSeeIncidents: boolean;
    canSeeLsr: boolean;
    canManageDocuments: boolean;
    tagHistory: TagHistoryRow[];
    entryExitLogs: EntryExitRow[];
    portableDevices: PortableDeviceRow[];
    incidents: IncidentRow[];
    lsrViolations: LsrRow[];
    documents: DocumentRow[];
    documentTypes: DocumentTypeOption[];
    documentChecklist: DocumentChecklistItem[];
    permitReadiness: PermitReadinessRow[];
    readinessSummary: ReadinessSummary | null;
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function WorkersShow({
    worker,
    onboarding = false,
    canManage,
    canSeeEntryExit,
    canSeePortableDevices,
    canSeeIncidents,
    canSeeLsr,
    canManageDocuments,
    tagHistory,
    entryExitLogs,
    portableDevices,
    incidents,
    lsrViolations,
    documents,
    documentTypes,
    documentChecklist = [],
    permitReadiness = [],
    readinessSummary = null,
}: Props) {
    const activeTag = tagHistory.find((tag) => tag.status === 'assigned');

    return (
        <>
            <Head title={worker.name} />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                {onboarding ? (
                    <div className="rounded-lg border border-border bg-surface-2 px-4 py-3">
                        <p className="text-xs font-medium uppercase tracking-wide text-text-faint">
                            Worker onboarding
                        </p>
                        <ol className="mt-2 flex flex-wrap gap-3 text-sm">
                            <li className="text-text">
                                <span className="font-medium">1. Profile</span>{' '}
                                · done
                            </li>
                            <li className="font-medium text-primary">
                                2. Documents
                            </li>
                            <li className="text-text-faint">
                                3. Permit readiness
                            </li>
                        </ol>
                    </div>
                ) : null}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">
                            {worker.contractor} · {worker.worker_type_label}
                        </p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            {worker.name}
                        </h1>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            <StatusPill
                                label={worker.present ? 'On site' : 'Off site'}
                                tone={worker.present ? 'ok' : 'neutral'}
                            />
                            <StatusPill
                                label={worker.is_active ? 'Active' : 'Inactive'}
                                tone={worker.is_active ? 'ok' : 'crit'}
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/workforce/workers">Back</Link>
                        </Button>
                        {canManage && (
                            <Button asChild>
                                <Link
                                    href={`/workforce/workers/${worker.id}/edit`}
                                >
                                    Edit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Panel
                        title="Profile"
                        className="xl:col-span-5"
                        action={
                            worker.photo_url ? (
                                <img
                                    src={worker.photo_url}
                                    alt=""
                                    className="size-14 rounded-[var(--radius-sm)] object-cover"
                                />
                            ) : undefined
                        }
                    >
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <Field label="Badge" value={worker.badge_number} />
                            <Field
                                label="Employee code"
                                value={worker.employee_code}
                            />
                            <Field label="Phone" value={worker.phone} />
                            <Field label="Role" value={worker.role_title} />
                            <div className="sm:col-span-2">
                                <Field label="Notes" value={worker.notes} />
                            </div>
                        </dl>
                    </Panel>

                    <Panel title="Active tag" className="xl:col-span-7">
                        {activeTag ? (
                            <div className="mb-3 flex items-center justify-between rounded-[var(--radius-sm)] border border-border bg-surface-2 px-3 py-2">
                                <span className="font-mono text-sm text-text">
                                    {activeTag.tag_uid}
                                </span>
                                <StatusPill label="Assigned" tone="ok" />
                            </div>
                        ) : (
                            <p className="mb-3 text-sm text-text-faint">
                                No tag currently assigned.
                            </p>
                        )}
                        <p className="eyebrow mb-2">Tag history</p>
                        <ul className="flex flex-col gap-2 text-sm">
                            {tagHistory.map((tag) => (
                                <li
                                    key={tag.id}
                                    className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                                >
                                    <span className="font-mono text-xs text-text-dim">
                                        {tag.tag_uid}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <span className="text-xs text-text-faint">
                                            {formatDate(tag.assigned_at)}
                                        </span>
                                        <StatusPill
                                            label={tag.status_label}
                                            tone={tagStatusTone(tag.status)}
                                        />
                                    </span>
                                </li>
                            ))}
                            {tagHistory.length === 0 && (
                                <li className="text-text-faint">
                                    No tags ever assigned.
                                </li>
                            )}
                        </ul>
                    </Panel>
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    {canSeeEntryExit ? (
                        <Panel
                            title="Entry / exit history"
                            subtitle="most recent 10"
                            className="xl:col-span-6"
                            action={
                                <Link
                                    href="/tracking/entry-exit"
                                    className="text-xs text-[color:var(--accent)] hover:underline"
                                >
                                    Full log ›
                                </Link>
                            }
                        >
                            <ul className="flex flex-col gap-2 text-sm">
                                {entryExitLogs.map((log) => (
                                    <li
                                        key={log.id}
                                        className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                                    >
                                        <span className="flex items-center gap-2">
                                            <StatusPill
                                                label={
                                                    log.direction === 'in'
                                                        ? 'In'
                                                        : 'Out'
                                                }
                                                tone={
                                                    log.direction === 'in'
                                                        ? 'ok'
                                                        : 'neutral'
                                                }
                                            />
                                            <span className="text-text-dim">
                                                {log.gate_zone_name ?? '—'}
                                            </span>
                                        </span>
                                        <span className="text-xs text-text-faint">
                                            {formatDate(log.occurred_at)}
                                        </span>
                                    </li>
                                ))}
                                {entryExitLogs.length === 0 && (
                                    <li className="text-text-faint">
                                        No entry/exit history.
                                    </li>
                                )}
                            </ul>
                        </Panel>
                    ) : null}

                    {canSeePortableDevices ? (
                        <Panel
                            title="Portable devices"
                            className="xl:col-span-6"
                            action={
                                <Link
                                    href="/workforce/portable-devices"
                                    className="text-xs text-[color:var(--accent)] hover:underline"
                                >
                                    All devices ›
                                </Link>
                            }
                        >
                            <ul className="flex flex-col gap-2 text-sm">
                                {portableDevices.map((device) => (
                                    <li
                                        key={device.id}
                                        className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                                    >
                                        <span className="text-text">
                                            {device.device_type}
                                            {device.make_model
                                                ? ` · ${device.make_model}`
                                                : ''}
                                        </span>
                                        <StatusPill
                                            label={
                                                device.status === 'approved'
                                                    ? 'Approved'
                                                    : 'Revoked'
                                            }
                                            tone={
                                                device.status === 'approved'
                                                    ? 'ok'
                                                    : 'crit'
                                            }
                                        />
                                    </li>
                                ))}
                                {portableDevices.length === 0 && (
                                    <li className="text-text-faint">
                                        No portable devices registered.
                                    </li>
                                )}
                            </ul>
                        </Panel>
                    ) : null}
                </div>

                {canSeeIncidents || canSeeLsr ? (
                    <div className="grid gap-4 xl:grid-cols-12">
                        {canSeeIncidents ? (
                            <Panel
                                title="Incident involvements"
                                className="xl:col-span-6"
                            >
                                <ul className="flex flex-col gap-2 text-sm">
                                    {incidents.map((incident) => (
                                        <li
                                            key={incident.id}
                                            className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                                        >
                                            <Link
                                                href={`/incidents/${incident.id}`}
                                                className="font-mono text-xs text-[color:var(--accent)] hover:underline"
                                            >
                                                {incident.incident_number}
                                            </Link>
                                            <span className="flex items-center gap-2 text-text-dim">
                                                {incident.involvement_label}
                                                <StatusPill
                                                    label={
                                                        incident.status_label
                                                    }
                                                    tone="info"
                                                    showDot={false}
                                                />
                                            </span>
                                        </li>
                                    ))}
                                    {incidents.length === 0 && (
                                        <li className="text-text-faint">
                                            No incident involvements.
                                        </li>
                                    )}
                                </ul>
                            </Panel>
                        ) : null}

                        {canSeeLsr ? (
                            <Panel
                                title="LSR violations"
                                className="xl:col-span-6"
                            >
                                <ul className="flex flex-col gap-2 text-sm">
                                    {lsrViolations.map((lsr) => (
                                        <li
                                            key={lsr.id}
                                            className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                                        >
                                            <Link
                                                href={`/lsr-violations/${lsr.id}`}
                                                className="text-[color:var(--accent)] hover:underline"
                                            >
                                                LSR #{lsr.id} ·{' '}
                                                {lsr.category_label}
                                            </Link>
                                            <span className="flex items-center gap-2 text-xs text-text-faint">
                                                {formatDate(lsr.occurred_at)}
                                                <StatusPill
                                                    label={lsr.status_label}
                                                    tone={
                                                        lsr.status_label ===
                                                        'Closed'
                                                            ? 'ok'
                                                            : 'warn'
                                                    }
                                                />
                                            </span>
                                        </li>
                                    ))}
                                    {lsrViolations.length === 0 && (
                                        <li className="text-text-faint">
                                            No LSR violations logged.
                                        </li>
                                    )}
                                </ul>
                            </Panel>
                        ) : null}
                    </div>
                ) : null}

                {canManageDocuments ? (
                    <Panel
                        title="Certificates & documents"
                        subtitle="Upload once on the worker — permit assignment only checks readiness"
                    >
                        <WorkerDocumentsPanel
                            workerId={worker.id}
                            documents={documents}
                            documentTypes={documentTypes}
                            checklist={documentChecklist}
                            permitReadiness={permitReadiness}
                            summary={readinessSummary}
                            onboarding={onboarding}
                        />
                    </Panel>
                ) : null}

                {canManage && (
                    <div className="flex flex-wrap gap-2 border-t border-border pt-4">
                        {worker.is_active ? (
                            <>
                                <Form
                                    action={`/workforce/workers/${worker.id}/deactivate`}
                                    method="post"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            disabled={
                                                processing || worker.present
                                            }
                                        >
                                            Deactivate
                                        </Button>
                                    )}
                                </Form>
                                <Form
                                    action={`/workforce/workers/${worker.id}/offboard`}
                                    method="post"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={
                                                processing || worker.present
                                            }
                                        >
                                            Offboard
                                        </Button>
                                    )}
                                </Form>
                            </>
                        ) : (
                            <Form
                                action={`/workforce/workers/${worker.id}/reactivate`}
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Reactivate
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

function tagStatusTone(status: string): StatusPillTone {
    if (status === 'assigned') {
        return 'ok';
    }

    if (status === 'lost' || status === 'damaged') {
        return 'crit';
    }

    return 'neutral';
}

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-xs text-text-faint">{label}</dt>
            <dd className="mt-0.5 text-text">{value ?? '—'}</dd>
        </div>
    );
}

WorkersShow.layout = {
    breadcrumbs: [
        { title: 'Workforce', href: '/workforce/workers' },
        { title: 'Workers', href: '/workforce/workers' },
    ],
};
