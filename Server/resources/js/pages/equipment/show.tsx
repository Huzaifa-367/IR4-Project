import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { CheckoutDialog } from '@/components/ir4/checkout-dialog';
import {
    CustodyBadge,
    EquipmentStatusBadge,
    OverdueBadge,
} from '@/components/ir4/equipment-badges';
import { EquipmentForm } from '@/components/ir4/equipment-form';
import { FactTile } from '@/components/ir4/fact-tile';
import { InspectionForm } from '@/components/ir4/inspection-form';
import { MaintenanceForm } from '@/components/ir4/maintenance-form';
import { Panel } from '@/components/ir4/panel';
import { QrLabelButton } from '@/components/ir4/qr-label-button';
import { ReturnDialog } from '@/components/ir4/return-dialog';
import { ScheduleEditor } from '@/components/ir4/schedule-editor';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { InspectionOutcomeLabels, MaintenanceTypeLabels } from '@/types/enums';
import type { InspectionOutcome, MaintenanceType } from '@/types/enums';
import type {
    EquipmentByToken,
    EquipmentDetail,
    EquipmentWorkerRef,
    EquipmentZoneRef,
} from '@/types/equipment';

type TabId =
    | 'overview'
    | 'inspections'
    | 'maintenance'
    | 'schedules'
    | 'documents'
    | 'custody';

type Props = {
    equipment: EquipmentDetail;
    workers?: EquipmentWorkerRef[];
    zones?: EquipmentZoneRef[];
    canManage: boolean;
};

function inspectionTone(outcome: string): StatusPillTone {
    if (outcome === 'fail') {
        return 'crit';
    }

    if (outcome === 'pass_with_notes') {
        return 'warn';
    }

    return 'ok';
}

function maintenanceTone(type: string): StatusPillTone {
    return type === 'corrective' ? 'warn' : 'accent';
}

export default function EquipmentShow({
    equipment,
    workers = [],
    zones = [],
    canManage,
}: Props) {
    const [tab, setTab] = useState<TabId>('overview');
    const [checkoutOpen, setCheckoutOpen] = useState(false);
    const [returnOpen, setReturnOpen] = useState(false);
    const isRetired = equipment.status === 'retired';

    const byToken: EquipmentByToken = {
        ...equipment,
        open_checkout: equipment.open_checkout,
    };

    const heroTone = (() => {
        if (
            equipment.checkout_state === 'overdue_return' ||
            equipment.is_inspection_overdue ||
            equipment.is_service_overdue
        ) {
            return 'bg-[color:var(--crit)]';
        }

        if (equipment.status === 'out_of_service') {
            return 'bg-[color:var(--warn)]';
        }

        if (
            equipment.status === 'under_maintenance' ||
            equipment.checkout_state === 'checked_out'
        ) {
            return 'bg-[color:var(--accent)]';
        }

        if (equipment.status === 'retired') {
            return 'bg-text-faint';
        }

        return 'bg-[color:var(--ok)]';
    })();

    const tabs: Array<{ id: TabId; label: string; count?: number }> = [
        { id: 'overview', label: 'Overview' },
        {
            id: 'inspections',
            label: 'Inspections',
            count: equipment.inspections.length,
        },
        {
            id: 'maintenance',
            label: 'Maintenance',
            count: equipment.maintenances.length,
        },
        {
            id: 'schedules',
            label: 'Schedules',
            count: equipment.schedules.length,
        },
        {
            id: 'documents',
            label: 'Documents',
            count: equipment.documents.length,
        },
        {
            id: 'custody',
            label: 'Custody',
            count: equipment.checkouts.length,
        },
    ];

    return (
        <>
            <Head title={equipment.name} />
            <div className="mx-auto flex max-w-6xl flex-col gap-4 p-4 md:p-5">
                <header className="overflow-hidden rounded-[var(--radius)] border border-border bg-surface shadow-[var(--shadow-card)]">
                    <div className={cn('h-1.5 w-full', heroTone)} aria-hidden />
                    <div className="flex flex-wrap items-start justify-between gap-4 p-4 md:p-5">
                        <div className="min-w-0 space-y-2">
                            <p className="eyebrow">
                                {equipment.equipment_code} ·{' '}
                                {equipment.equipment_type}
                            </p>
                            <h1 className="font-display text-2xl font-semibold tracking-tight text-text md:text-3xl">
                                {equipment.name}
                            </h1>
                            <div className="flex flex-wrap gap-2">
                                <EquipmentStatusBadge
                                    status={equipment.status}
                                />
                                <CustodyBadge
                                    state={equipment.checkout_state}
                                    workerName={
                                        equipment.open_checkout?.worker?.name
                                    }
                                />
                                <OverdueBadge
                                    isInspectionOverdue={
                                        equipment.is_inspection_overdue
                                    }
                                    isServiceOverdue={
                                        equipment.is_service_overdue
                                    }
                                    isDueSoon={equipment.is_due_soon}
                                    isReturnOverdue={
                                        equipment.checkout_state ===
                                        'overdue_return'
                                    }
                                />
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline" size="sm">
                                <Link href="/equipment">All equipment</Link>
                            </Button>
                            <QrLabelButton equipmentUuid={equipment.uuid} />
                            {canManage &&
                                equipment.is_checkoutable &&
                                !isRetired &&
                                !equipment.open_checkout && (
                                    <Button
                                        size="sm"
                                        onClick={() => setCheckoutOpen(true)}
                                    >
                                        Check out
                                    </Button>
                                )}
                            {canManage && equipment.open_checkout && (
                                <Button
                                    size="sm"
                                    onClick={() => setReturnOpen(true)}
                                >
                                    Return
                                </Button>
                            )}
                            {canManage && !isRetired && (
                                <Form
                                    action={`/equipment/${equipment.uuid}/retire`}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            Retire
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </div>
                    </div>
                </header>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FactTile
                        label="Location"
                        value={equipment.location_label ?? '—'}
                        tone="accent"
                    />
                    <FactTile
                        label="Next inspection"
                        value={equipment.next_inspection_due ?? '—'}
                        tone={
                            equipment.is_inspection_overdue
                                ? 'crit'
                                : equipment.is_due_soon
                                  ? 'warn'
                                  : 'ok'
                        }
                    />
                    <FactTile
                        label="Next service"
                        value={equipment.next_service_due ?? '—'}
                        tone={
                            equipment.is_service_overdue ? 'crit' : 'neutral'
                        }
                    />
                    <FactTile
                        label="QR token"
                        value={
                            <span className="font-mono text-xs break-all">
                                {equipment.qr_token}
                            </span>
                        }
                        tone="neutral"
                    />
                </div>

                <div className="flex flex-wrap gap-1 rounded-[var(--radius)] border border-border bg-surface p-1.5 shadow-[var(--shadow-card)]">
                    {tabs.map((item) => (
                        <Button
                            key={item.id}
                            type="button"
                            size="sm"
                            variant={tab === item.id ? 'default' : 'ghost'}
                            onClick={() => setTab(item.id)}
                        >
                            {item.label}
                            {item.count !== undefined ? (
                                <span
                                    className={cn(
                                        'ml-1.5 rounded-pill px-1.5 py-0 text-[10px] font-semibold tabular-nums',
                                        tab === item.id
                                            ? 'bg-white/20'
                                            : 'bg-surface-3 text-text-dim',
                                    )}
                                >
                                    {item.count}
                                </span>
                            ) : null}
                        </Button>
                    ))}
                </div>

                {tab === 'overview' && (
                    <div className="grid gap-4 lg:grid-cols-5">
                        <Panel
                            title="Description"
                            subtitle="What this asset is for"
                            className="lg:col-span-2"
                        >
                            <p className="rounded-md border border-border bg-surface-2/30 px-3 py-2.5 text-sm leading-relaxed whitespace-pre-wrap text-text">
                                {equipment.description ?? 'No description.'}
                            </p>
                            {isRetired ? (
                                <p className="mt-3 rounded-md border border-[color:var(--warn)]/35 bg-[color:var(--warn-bg)] px-3 py-2 text-sm">
                                    Retired — inspections and status changes are
                                    locked. Documents may still be added for
                                    record-keeping.
                                </p>
                            ) : null}
                        </Panel>
                        <Panel
                            title="Edit profile"
                            subtitle="Identity, location, and status"
                            className="border-l-[3px] border-l-[color:var(--accent)] lg:col-span-3"
                        >
                            {canManage && !isRetired ? (
                                <EquipmentForm
                                    action={`/equipment/${equipment.uuid}`}
                                    method="put"
                                    defaults={equipment}
                                    submitLabel="Save changes"
                                    allowStatus
                                />
                            ) : (
                                <p className="text-sm text-text-dim">
                                    {isRetired
                                        ? 'Profile editing is locked for retired equipment.'
                                        : 'You do not have permission to edit this item.'}
                                </p>
                            )}
                        </Panel>
                    </div>
                )}

                {tab === 'inspections' && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {canManage && !isRetired ? (
                            <Panel
                                title="Log inspection"
                                subtitle="Record outcome and next due"
                                className="border-l-[3px] border-l-[color:var(--ok)]"
                            >
                                <InspectionForm equipmentUuid={equipment.uuid} />
                            </Panel>
                        ) : null}
                        <Panel
                            title="Inspection history"
                            subtitle={`${equipment.inspections.length} recorded`}
                            className={
                                canManage && !isRetired
                                    ? undefined
                                    : 'lg:col-span-2'
                            }
                        >
                            <ul className="space-y-2 text-sm">
                                {equipment.inspections.map((row) => (
                                    <li
                                        key={row.id}
                                        className={cn(
                                            'rounded-md border px-3 py-2.5',
                                            row.outcome === 'fail'
                                                ? 'border-[color:var(--crit)]/30 bg-[color:var(--crit-bg)]'
                                                : row.outcome ===
                                                    'pass_with_notes'
                                                  ? 'border-[color:var(--warn)]/30 bg-[color:var(--warn-bg)]'
                                                  : 'border-[color:var(--ok)]/25 bg-[color:var(--ok-bg)]',
                                        )}
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-medium tabular-nums">
                                                {row.inspected_at}
                                            </span>
                                            <StatusPill
                                                label={
                                                    row.outcome_label ??
                                                    InspectionOutcomeLabels[
                                                        row.outcome as InspectionOutcome
                                                    ] ??
                                                    row.outcome
                                                }
                                                tone={inspectionTone(
                                                    row.outcome,
                                                )}
                                            />
                                        </div>
                                        <p className="mt-1 text-xs text-text-dim">
                                            {row.inspector?.name ?? '—'}
                                            {row.next_due
                                                ? ` · next ${row.next_due}`
                                                : ''}
                                        </p>
                                        {row.notes ? (
                                            <p className="mt-1.5 text-sm text-text">
                                                {row.notes}
                                            </p>
                                        ) : null}
                                    </li>
                                ))}
                                {equipment.inspections.length === 0 ? (
                                    <li className="rounded-md border border-dashed border-border px-3 py-6 text-center text-text-faint">
                                        No inspections yet.
                                    </li>
                                ) : null}
                            </ul>
                        </Panel>
                    </div>
                )}

                {tab === 'maintenance' && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {canManage && !isRetired ? (
                            <Panel
                                title="Log maintenance"
                                subtitle="Preventive or corrective work"
                                className="border-l-[3px] border-l-[color:var(--accent)]"
                            >
                                <MaintenanceForm equipmentUuid={equipment.uuid} />
                            </Panel>
                        ) : null}
                        <Panel
                            title="Maintenance history"
                            subtitle={`${equipment.maintenances.length} recorded`}
                            className={
                                canManage && !isRetired
                                    ? undefined
                                    : 'lg:col-span-2'
                            }
                        >
                            <ul className="space-y-2 text-sm">
                                {equipment.maintenances.map((row) => (
                                    <li
                                        key={row.id}
                                        className={cn(
                                            'rounded-md border px-3 py-2.5',
                                            row.maintenance_type ===
                                                'corrective'
                                                ? 'border-[color:var(--warn)]/30 bg-[color:var(--warn-bg)]'
                                                : 'border-[color:var(--accent)]/25 bg-[color:var(--accent-dim)]',
                                        )}
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-medium tabular-nums">
                                                {row.performed_at}
                                            </span>
                                            <StatusPill
                                                label={
                                                    row.maintenance_type_label ??
                                                    MaintenanceTypeLabels[
                                                        row.maintenance_type as MaintenanceType
                                                    ] ??
                                                    row.maintenance_type
                                                }
                                                tone={maintenanceTone(
                                                    row.maintenance_type,
                                                )}
                                            />
                                        </div>
                                        <p className="mt-1.5 text-sm text-text">
                                            {row.description}
                                        </p>
                                        <p className="mt-1 text-xs text-text-dim">
                                            {row.performed_by_name ?? '—'}
                                            {row.next_due
                                                ? ` · next ${row.next_due}`
                                                : ''}
                                        </p>
                                    </li>
                                ))}
                                {equipment.maintenances.length === 0 ? (
                                    <li className="rounded-md border border-dashed border-border px-3 py-6 text-center text-text-faint">
                                        No maintenance logged.
                                    </li>
                                ) : null}
                            </ul>
                        </Panel>
                    </div>
                )}

                {tab === 'schedules' && (
                    <Panel
                        title="Maintenance schedules"
                        subtitle="Interval-driven due dates"
                        className="border-l-[3px] border-l-[color:var(--accent)]"
                    >
                        {canManage && !isRetired ? (
                            <ScheduleEditor
                                equipmentUuid={equipment.uuid}
                                schedules={equipment.schedules}
                            />
                        ) : (
                            <ul className="grid gap-2 sm:grid-cols-2">
                                {equipment.schedules.map((row) => (
                                    <li
                                        key={row.id}
                                        className="rounded-md border border-[color:var(--accent)]/25 bg-[color:var(--accent-dim)] px-3 py-2.5 text-sm"
                                    >
                                        <p className="font-semibold text-text">
                                            {row.schedule_type_label ??
                                                row.schedule_type}
                                        </p>
                                        <p className="mt-0.5 text-text-dim">
                                            Every {row.interval_days} days
                                            {row.notes
                                                ? ` · ${row.notes}`
                                                : ''}
                                        </p>
                                    </li>
                                ))}
                                {equipment.schedules.length === 0 ? (
                                    <li className="rounded-md border border-dashed border-border px-3 py-6 text-center text-text-faint sm:col-span-2">
                                        No schedules set.
                                    </li>
                                ) : null}
                            </ul>
                        )}
                    </Panel>
                )}

                {tab === 'documents' && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {canManage ? (
                            <Panel
                                title="Upload document"
                                subtitle="PDF manuals and certificates"
                                className="border-l-[3px] border-l-[color:var(--accent)]"
                            >
                                <Form
                                    action={`/equipment/${equipment.uuid}/documents`}
                                    method="post"
                                    encType="multipart/form-data"
                                    className="space-y-3"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="title">
                                                    Title
                                                </Label>
                                                <Input
                                                    id="title"
                                                    name="title"
                                                    required
                                                    maxLength={150}
                                                    className="bg-surface"
                                                />
                                                {errors.title ? (
                                                    <p className="text-sm text-destructive">
                                                        {errors.title}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="file">
                                                    PDF (≤50 MB)
                                                </Label>
                                                <Input
                                                    id="file"
                                                    name="file"
                                                    type="file"
                                                    accept="application/pdf,.pdf"
                                                    required
                                                    className="bg-surface"
                                                />
                                                {errors.file ? (
                                                    <p className="text-sm text-destructive">
                                                        {errors.file}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Upload document
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </Panel>
                        ) : null}
                        <Panel
                            title="On file"
                            subtitle={`${equipment.documents.length} document${equipment.documents.length === 1 ? '' : 's'}`}
                            className={canManage ? undefined : 'lg:col-span-2'}
                        >
                            <ul className="space-y-2 text-sm">
                                {equipment.documents.map((doc) => (
                                    <li
                                        key={doc.id}
                                        className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-surface-2/30 px-3 py-2.5"
                                    >
                                        <div>
                                            <p className="font-medium text-text">
                                                {doc.title}
                                            </p>
                                            <p className="text-xs text-text-faint">
                                                {doc.mime}
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            {doc.download_url ? (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <a href={doc.download_url}>
                                                        Download
                                                    </a>
                                                </Button>
                                            ) : null}
                                            {canManage ? (
                                                <Form
                                                    action={`/equipment/${equipment.uuid}/documents/${doc.uuid}`}
                                                    method="delete"
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="ghost"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Remove
                                                        </Button>
                                                    )}
                                                </Form>
                                            ) : null}
                                        </div>
                                    </li>
                                ))}
                                {equipment.documents.length === 0 ? (
                                    <li className="rounded-md border border-dashed border-border px-3 py-6 text-center text-text-faint">
                                        No documents.
                                    </li>
                                ) : null}
                            </ul>
                        </Panel>
                    </div>
                )}

                {tab === 'custody' && (
                    <div className="grid gap-4 lg:grid-cols-5">
                        <div className="space-y-4 lg:col-span-2">
                            {!equipment.is_checkoutable ? (
                                <section className="rounded-[var(--radius)] border-l-4 border-[color:var(--warn)] bg-[color:var(--warn-bg)] p-4 shadow-[var(--shadow-card)]">
                                    <p className="eyebrow text-[color:var(--warn)]">
                                        Not checkoutable
                                    </p>
                                    <p className="mt-1 text-sm text-text-dim">
                                        Enable checkoutable on Overview to use
                                        custody tracking.
                                    </p>
                                </section>
                            ) : null}
                            {equipment.open_checkout ? (
                                <section className="rounded-[var(--radius)] border-l-4 border-[color:var(--accent)] bg-[color:var(--accent-dim)] p-4 shadow-[var(--shadow-card)]">
                                    <p className="eyebrow text-[color:var(--accent)]">
                                        Currently out
                                    </p>
                                    <h2 className="mt-1 font-display text-lg font-semibold text-text">
                                        {equipment.open_checkout.worker
                                            ?.name ?? 'Worker'}
                                    </h2>
                                    <p className="mt-1 text-sm tabular-nums text-text-dim">
                                        Since{' '}
                                        {new Date(
                                            equipment.open_checkout
                                                .checked_out_at,
                                        ).toLocaleString()}
                                    </p>
                                    {equipment.open_checkout.reason ? (
                                        <p className="mt-2 rounded-md border border-border/60 bg-surface/60 px-3 py-2 text-sm">
                                            {equipment.open_checkout.reason}
                                        </p>
                                    ) : null}
                                    {canManage ? (
                                        <Button
                                            className="mt-3"
                                            size="sm"
                                            onClick={() => setReturnOpen(true)}
                                        >
                                            Return now
                                        </Button>
                                    ) : null}
                                </section>
                            ) : equipment.is_checkoutable ? (
                                <section className="rounded-[var(--radius)] border-l-4 border-[color:var(--ok)] bg-[color:var(--ok-bg)] p-4 shadow-[var(--shadow-card)]">
                                    <p className="eyebrow text-[color:var(--ok)]">
                                        Available
                                    </p>
                                    <p className="mt-1 text-sm text-text-dim">
                                        No open checkout. Use Check out above to
                                        issue this item.
                                    </p>
                                </section>
                            ) : null}
                        </div>

                        <Panel
                            title="Custody history"
                            subtitle={`${equipment.checkouts.length} event${equipment.checkouts.length === 1 ? '' : 's'}`}
                            className="lg:col-span-3"
                        >
                            <ul className="relative space-y-0 text-sm before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px before:bg-border">
                                {equipment.checkouts.map((row) => (
                                    <li
                                        key={row.id}
                                        className="relative flex gap-3 py-2.5 pl-5"
                                    >
                                        <span
                                            className={cn(
                                                'absolute top-3.5 left-0 size-3.5 rounded-full border-2 bg-surface',
                                                row.returned_at
                                                    ? 'border-[color:var(--ok)]'
                                                    : 'border-[color:var(--accent)]',
                                            )}
                                            aria-hidden
                                        />
                                        <div className="min-w-0 flex-1 rounded-md border border-border bg-surface-2/30 px-3 py-2">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <p className="font-medium text-text">
                                                    {row.worker?.name ??
                                                        `Worker #${row.worker_id}`}
                                                </p>
                                                <StatusPill
                                                    label={
                                                        row.returned_at
                                                            ? 'Returned'
                                                            : 'Open'
                                                    }
                                                    tone={
                                                        row.returned_at
                                                            ? 'ok'
                                                            : 'accent'
                                                    }
                                                />
                                            </div>
                                            <p className="mt-1 text-xs tabular-nums text-text-dim">
                                                Out{' '}
                                                {new Date(
                                                    row.checked_out_at,
                                                ).toLocaleString()}
                                                {row.returned_at
                                                    ? ` · back ${new Date(row.returned_at).toLocaleString()}`
                                                    : ''}
                                            </p>
                                            {(row.reason || row.zone) && (
                                                <p className="mt-1 text-xs text-text-faint">
                                                    {row.reason ?? '—'}
                                                    {row.zone
                                                        ? ` · ${row.zone.name}`
                                                        : ''}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                                {equipment.checkouts.length === 0 ? (
                                    <li className="pl-5 text-text-faint">
                                        No custody history.
                                    </li>
                                ) : null}
                            </ul>
                        </Panel>
                    </div>
                )}
            </div>

            <CheckoutDialog
                open={checkoutOpen}
                onOpenChange={setCheckoutOpen}
                equipment={byToken}
                workers={workers}
                zones={zones}
            />
            <ReturnDialog
                open={returnOpen}
                onOpenChange={setReturnOpen}
                checkout={equipment.open_checkout}
                equipmentLabel={`${equipment.equipment_code} · ${equipment.name}`}
            />
        </>
    );
}

EquipmentShow.layout = {
    breadcrumbs: [
        { title: 'Equipment', href: '/equipment' },
        { title: 'Items', href: '/equipment' },
    ],
};
