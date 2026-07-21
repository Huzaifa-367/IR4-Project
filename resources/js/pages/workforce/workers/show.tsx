import { Form, Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { FactTile, DetailField } from '@/components/ir4/fact-tile';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { WorkerForm } from '@/components/ir4/worker-form';
import {
    WorkerDocumentsPanel,
} from '@/components/ir4/worker-documents-panel';
import type {
    DocumentChecklistItem,
    DocumentRow,
    PermitReadinessRow,
    ReadinessSummary,
} from '@/components/ir4/worker-documents-panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
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
    openEdit?: boolean;
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
    workerTypes: Array<{ value: string; label: string }>;
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
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

function workerTypeSoft(type: string): string {
    if (type === 'employee') {
        return 'bg-[color:var(--accent-dim)] text-[color:var(--accent)]';
    }

    if (type === 'contractor') {
        return 'bg-[color:var(--warn-bg)] text-[color:var(--warn)]';
    }

    return 'bg-surface-3 text-text-dim';
}

export default function WorkersShow({
    worker,
    onboarding = false,
    openEdit = false,
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
    workerTypes,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);

    useEffect(() => {
        if (openEdit && canManage) {
            setEditOpen(true);
        }
    }, [openEdit, canManage]);

    const activeTag = tagHistory.find((tag) => tag.status === 'assigned');
    const heroTone = !worker.is_active
        ? 'bg-[color:var(--crit)]'
        : worker.present
          ? 'bg-[color:var(--ok)]'
          : 'bg-[color:var(--accent)]';

    const readinessTone =
        readinessSummary === null
            ? ('neutral' as const)
            : readinessSummary.blocked_roles > 0 ||
                readinessSummary.missing_recommended > 0
              ? ('warn' as const)
              : readinessSummary.ready_roles > 0
                ? ('ok' as const)
                : ('neutral' as const);

    return (
        <>
            <Head title={worker.name} />
            <div className="mx-auto flex max-w-6xl flex-col gap-4 p-4 md:p-5">
                {onboarding ? (
                    <section className="rounded-[var(--radius)] border-l-4 border-[color:var(--accent)] bg-[color:var(--accent-dim)] p-4 shadow-[var(--shadow-card)]">
                        <p className="eyebrow text-[color:var(--accent)]">
                            Onboarding
                        </p>
                        <h2 className="mt-1 font-display text-lg font-semibold text-text">
                            Worker created — add certificates
                        </h2>
                        <p className="mt-1 text-sm text-text-dim">
                            Upload the recommended packs below so this worker
                            can be assigned to permits.
                        </p>
                    </section>
                ) : null}

                <header className="overflow-hidden rounded-[var(--radius)] border border-border bg-surface shadow-[var(--shadow-card)]">
                    <div className={cn('h-1.5 w-full', heroTone)} aria-hidden />
                    <div className="flex flex-wrap items-start justify-between gap-4 p-4 md:p-5">
                        <div className="flex min-w-0 flex-1 items-start gap-4">
                            {worker.photo_url ? (
                                <img
                                    src={worker.photo_url}
                                    alt=""
                                    className="size-16 shrink-0 rounded-[var(--radius)] border border-border object-cover shadow-[var(--shadow-card)] md:size-20"
                                />
                            ) : (
                                <div
                                    className="flex size-16 shrink-0 items-center justify-center rounded-[var(--radius)] border border-border bg-surface-2 font-display text-xl font-semibold text-text-dim md:size-20"
                                    aria-hidden
                                >
                                    {worker.name
                                        .split(/\s+/)
                                        .slice(0, 2)
                                        .map((part) => part[0]?.toUpperCase())
                                        .join('')}
                                </div>
                            )}
                            <div className="min-w-0 space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span
                                        className={cn(
                                            'inline-flex items-center rounded-pill px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase',
                                            workerTypeSoft(worker.worker_type),
                                        )}
                                    >
                                        {worker.worker_type_label}
                                    </span>
                                    <span className="text-sm text-text-dim">
                                        {worker.contractor}
                                    </span>
                                </div>
                                <h1 className="font-display text-2xl font-semibold tracking-tight text-text md:text-3xl">
                                    {worker.name}
                                </h1>
                                <div className="flex flex-wrap gap-1.5">
                                    <StatusPill
                                        label={
                                            worker.present
                                                ? 'On site'
                                                : 'Off site'
                                        }
                                        tone={
                                            worker.present ? 'ok' : 'neutral'
                                        }
                                    />
                                    <StatusPill
                                        label={
                                            worker.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }
                                        tone={
                                            worker.is_active ? 'ok' : 'crit'
                                        }
                                    />
                                    {worker.role_title ? (
                                        <StatusPill
                                            label={worker.role_title}
                                            tone="accent"
                                            showDot={false}
                                        />
                                    ) : null}
                                </div>
                                {worker.last_seen_at ? (
                                    <p className="text-xs tabular-nums text-text-faint">
                                        Last seen {formatDate(worker.last_seen_at)}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href="/workforce/workers">
                                    All workers
                                </Link>
                            </Button>
                            {canManage ? (
                                <Button
                                    type="button"
                                    onClick={() => setEditOpen(true)}
                                >
                                    Edit
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </header>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FactTile
                        label="Presence"
                        value={worker.present ? 'On site' : 'Off site'}
                        tone={worker.present ? 'ok' : 'neutral'}
                    />
                    <FactTile
                        label="RFID tag"
                        value={activeTag?.tag_uid ?? 'None assigned'}
                        tone={activeTag ? 'ok' : 'warn'}
                    />
                    <FactTile
                        label="Permit roles"
                        value={
                            readinessSummary
                                ? `${readinessSummary.ready_roles} ready · ${readinessSummary.blocked_roles} blocked`
                                : '—'
                        }
                        tone={readinessTone}
                    />
                    <FactTile
                        label="Certificates"
                        value={
                            readinessSummary
                                ? `${readinessSummary.verified_docs} verified · ${readinessSummary.missing_recommended} needed`
                                : `${documents.length} on file`
                        }
                        tone={
                            readinessSummary &&
                            readinessSummary.missing_recommended > 0
                                ? 'warn'
                                : readinessSummary &&
                                    readinessSummary.verified_docs > 0
                                  ? 'ok'
                                  : 'neutral'
                        }
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    <Panel
                        title="Profile"
                        subtitle="Identity and contact"
                        className="xl:col-span-5"
                    >
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <DetailField
                                label="Badge"
                                value={worker.badge_number}
                            />
                            <DetailField
                                label="Employee code"
                                value={worker.employee_code}
                            />
                            <DetailField label="Phone" value={worker.phone} />
                            <DetailField
                                label="Role"
                                value={worker.role_title}
                            />
                            <div className="sm:col-span-2">
                                <DetailField
                                    label="Notes"
                                    value={worker.notes}
                                />
                            </div>
                        </dl>
                    </Panel>

                    <Panel
                        title="Active tag"
                        subtitle="Assignment history"
                        className="xl:col-span-7"
                    >
                        {activeTag ? (
                            <div className="mb-3 flex items-center justify-between rounded-[var(--radius-sm)] border border-[color:var(--ok)]/35 bg-[color:var(--ok-bg)] px-3 py-2.5">
                                <span className="font-mono text-sm font-medium text-text">
                                    {activeTag.tag_uid}
                                </span>
                                <StatusPill label="Assigned" tone="ok" />
                            </div>
                        ) : (
                            <p className="mb-3 rounded-md border border-[color:var(--warn)]/30 bg-[color:var(--warn-bg)] px-3 py-2 text-sm text-text">
                                No tag currently assigned.
                            </p>
                        )}
                        <p className="eyebrow mb-2">Tag history</p>
                        <ul className="flex flex-col gap-2 text-sm">
                            {tagHistory.map((tag) => (
                                <li
                                    key={tag.id}
                                    className={cn(
                                        'flex items-center justify-between gap-2 rounded-md border px-3 py-2',
                                        tag.status === 'assigned'
                                            ? 'border-[color:var(--ok)]/25 bg-[color:var(--ok-bg)]'
                                            : 'border-border bg-surface-2/30',
                                    )}
                                >
                                    <span className="font-mono text-xs text-text-dim">
                                        {tag.tag_uid}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <span className="text-xs tabular-nums text-text-faint">
                                            {formatDate(tag.assigned_at)}
                                        </span>
                                        <StatusPill
                                            label={tag.status_label}
                                            tone={tagStatusTone(tag.status)}
                                        />
                                    </span>
                                </li>
                            ))}
                            {tagHistory.length === 0 ? (
                                <li className="text-text-faint">
                                    No tags ever assigned.
                                </li>
                            ) : null}
                        </ul>
                    </Panel>
                </div>

                <div className="grid gap-4 xl:grid-cols-12">
                    {canSeeEntryExit ? (
                        <Panel
                            title="Entry / exit"
                            subtitle="Most recent 10"
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
                                        className={cn(
                                            'flex items-center justify-between gap-2 rounded-md border px-3 py-2',
                                            log.direction === 'in'
                                                ? 'border-[color:var(--ok)]/25 bg-[color:var(--ok-bg)]'
                                                : 'border-border bg-surface-2/30',
                                        )}
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
                                        <span className="text-xs tabular-nums text-text-faint">
                                            {formatDate(log.occurred_at)}
                                        </span>
                                    </li>
                                ))}
                                {entryExitLogs.length === 0 ? (
                                    <li className="text-text-faint">
                                        No entry/exit history.
                                    </li>
                                ) : null}
                            </ul>
                        </Panel>
                    ) : null}

                    {canSeePortableDevices ? (
                        <Panel
                            title="Portable devices"
                            subtitle="Registered gear"
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
                                        className={cn(
                                            'flex items-center justify-between gap-2 rounded-md border px-3 py-2',
                                            device.status === 'approved'
                                                ? 'border-[color:var(--ok)]/25 bg-[color:var(--ok-bg)]'
                                                : 'border-[color:var(--crit)]/25 bg-[color:var(--crit-bg)]',
                                        )}
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
                                {portableDevices.length === 0 ? (
                                    <li className="text-text-faint">
                                        No portable devices registered.
                                    </li>
                                ) : null}
                            </ul>
                        </Panel>
                    ) : null}
                </div>

                {canSeeIncidents || canSeeLsr ? (
                    <div className="grid gap-4 xl:grid-cols-12">
                        {canSeeIncidents ? (
                            <Panel
                                title="Incidents"
                                subtitle="Involvements"
                                className="xl:col-span-6"
                            >
                                <ul className="flex flex-col gap-2 text-sm">
                                    {incidents.map((incident) => (
                                        <li
                                            key={incident.id}
                                            className="flex items-center justify-between gap-2 rounded-md border border-border bg-surface-2/30 px-3 py-2"
                                        >
                                            <div>
                                                <Link
                                                    href={`/incidents/${incident.id}`}
                                                    className="font-mono text-xs text-[color:var(--accent)] hover:underline"
                                                >
                                                    {incident.incident_number}
                                                </Link>
                                                <p className="text-xs text-text-dim">
                                                    {incident.involvement_label}
                                                </p>
                                            </div>
                                            <StatusPill
                                                label={incident.status_label}
                                                tone="info"
                                                showDot={false}
                                            />
                                        </li>
                                    ))}
                                    {incidents.length === 0 ? (
                                        <li className="text-text-faint">
                                            No incident involvements.
                                        </li>
                                    ) : null}
                                </ul>
                            </Panel>
                        ) : null}

                        {canSeeLsr ? (
                            <Panel
                                title="LSR"
                                subtitle="Life saving rule records"
                                className="xl:col-span-6"
                            >
                                <ul className="flex flex-col gap-2 text-sm">
                                    {lsrViolations.map((lsr) => (
                                        <li
                                            key={lsr.id}
                                            className={cn(
                                                'flex items-center justify-between gap-2 rounded-md border px-3 py-2',
                                                lsr.status_label === 'Closed'
                                                    ? 'border-[color:var(--ok)]/25 bg-[color:var(--ok-bg)]'
                                                    : 'border-[color:var(--warn)]/25 bg-[color:var(--warn-bg)]',
                                            )}
                                        >
                                            <div>
                                                <Link
                                                    href={`/lsr-violations/${lsr.id}`}
                                                    className="text-[color:var(--accent)] hover:underline"
                                                >
                                                    LSR #{lsr.id} ·{' '}
                                                    {lsr.category_label}
                                                </Link>
                                                <p className="text-xs tabular-nums text-text-faint">
                                                    {formatDate(lsr.occurred_at)}
                                                </p>
                                            </div>
                                            <StatusPill
                                                label={lsr.status_label}
                                                tone={
                                                    lsr.status_label ===
                                                    'Closed'
                                                        ? 'ok'
                                                        : 'warn'
                                                }
                                            />
                                        </li>
                                    ))}
                                    {lsrViolations.length === 0 ? (
                                        <li className="text-text-faint">
                                            No LSR violations logged.
                                        </li>
                                    ) : null}
                                </ul>
                            </Panel>
                        ) : null}
                    </div>
                ) : null}

                {canManageDocuments ? (
                    <Panel
                        title="Certificates"
                        subtitle="Upload once · reuse on every permit"
                        className="border-[color:var(--accent)]/25"
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

                {canManage ? (
                    <section className="rounded-[var(--radius)] border border-border bg-surface p-4 shadow-[var(--shadow-card)]">
                        <p className="eyebrow">Lifecycle</p>
                        <p className="mt-1 text-sm text-text-dim">
                            Deactivate keeps history. Offboard ends the
                            assignment permanently.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
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
                                                    processing ||
                                                    worker.present
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
                                                    processing ||
                                                    worker.present
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
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Reactivate
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </div>
                    </section>
                ) : null}
            </div>

            {canManage ? (
                <Dialog open={editOpen} onOpenChange={setEditOpen}>
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                        <DialogHeader>
                            <DialogTitle>Edit worker</DialogTitle>
                            <DialogDescription>
                                Update profile details for {worker.name}.
                            </DialogDescription>
                        </DialogHeader>
                        <WorkerForm
                            action={`/workforce/workers/${worker.id}`}
                            method="put"
                            workerTypes={workerTypes}
                            defaults={{
                                name: worker.name,
                                contractor: worker.contractor,
                                worker_type: worker.worker_type,
                                role_title: worker.role_title,
                                badge_number: worker.badge_number,
                                employee_code: worker.employee_code,
                                phone: worker.phone,
                                notes: worker.notes,
                            }}
                            submitLabel="Save changes"
                            className="space-y-4"
                            onSuccess={() => setEditOpen(false)}
                        />
                    </DialogContent>
                </Dialog>
            ) : null}
        </>
    );
}

WorkersShow.layout = {
    breadcrumbs: [
        { title: 'Workforce', href: '/workforce/workers' },
        { title: 'Workers', href: '/workforce/workers' },
    ],
};
