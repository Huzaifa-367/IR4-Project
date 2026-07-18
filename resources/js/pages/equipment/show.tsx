import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { CheckoutDialog } from '@/components/ir4/checkout-dialog';
import {
    CustodyBadge,
    EquipmentStatusBadge,
    OverdueBadge,
} from '@/components/ir4/equipment-badges';
import { EquipmentForm } from '@/components/ir4/equipment-form';
import { InspectionForm } from '@/components/ir4/inspection-form';
import { MaintenanceForm } from '@/components/ir4/maintenance-form';
import { QrLabelButton } from '@/components/ir4/qr-label-button';
import { ReturnDialog } from '@/components/ir4/return-dialog';
import { ScheduleEditor } from '@/components/ir4/schedule-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const TABS: Array<{ id: TabId; label: string }> = [
    { id: 'overview', label: 'Overview' },
    { id: 'inspections', label: 'Inspections' },
    { id: 'maintenance', label: 'Maintenance' },
    { id: 'schedules', label: 'Schedules' },
    { id: 'documents', label: 'Documents' },
    { id: 'custody', label: 'Custody' },
];

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

    return (
        <>
            <Head title={equipment.name} />
            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title={equipment.name}
                            description={`${equipment.equipment_code} · ${equipment.equipment_type}`}
                        />
                        <div className="flex flex-wrap gap-2">
                            <EquipmentStatusBadge status={equipment.status} />
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
                                isServiceOverdue={equipment.is_service_overdue}
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
                            <Link href="/equipment">Back</Link>
                        </Button>
                        <QrLabelButton equipmentId={equipment.id} />
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
                                action={`/equipment/${equipment.id}/retire`}
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

                <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt className="text-muted-foreground">Location</dt>
                        <dd>{equipment.location_label ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Next inspection
                        </dt>
                        <dd>{equipment.next_inspection_due ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Next service</dt>
                        <dd>{equipment.next_service_due ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">QR token</dt>
                        <dd className="font-mono text-xs break-all">
                            {equipment.qr_token}
                        </dd>
                    </div>
                </dl>

                <div className="flex flex-wrap gap-1 border-b border-border pb-2">
                    {TABS.map((item) => (
                        <Button
                            key={item.id}
                            type="button"
                            size="sm"
                            variant={tab === item.id ? 'default' : 'ghost'}
                            onClick={() => setTab(item.id)}
                        >
                            {item.label}
                        </Button>
                    ))}
                </div>

                {tab === 'overview' && (
                    <section className="max-w-xl space-y-4">
                        <p className="text-sm whitespace-pre-wrap">
                            {equipment.description ?? 'No description.'}
                        </p>
                        {canManage && !isRetired && (
                            <EquipmentForm
                                action={`/equipment/${equipment.id}`}
                                method="put"
                                defaults={equipment}
                                submitLabel="Save changes"
                                allowStatus
                            />
                        )}
                        {isRetired && (
                            <p className="rounded-md border border-border bg-muted/40 p-3 text-sm">
                                Retired — inspections and status changes are
                                locked. Documents may still be added for
                                record-keeping.
                            </p>
                        )}
                    </section>
                )}

                {tab === 'inspections' && (
                    <section className="grid gap-6 lg:grid-cols-2">
                        {canManage && !isRetired && (
                            <InspectionForm equipmentId={equipment.id} />
                        )}
                        <ul className="space-y-2 text-sm">
                            {equipment.inspections.map((row) => (
                                <li
                                    key={row.id}
                                    className="rounded-md border border-border px-3 py-2"
                                >
                                    <div className="font-medium">
                                        {row.inspected_at} ·{' '}
                                        {row.outcome_label ??
                                            InspectionOutcomeLabels[
                                                row.outcome as InspectionOutcome
                                            ] ??
                                            row.outcome}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {row.inspector?.name ?? '—'}
                                        {row.next_due
                                            ? ` · next ${row.next_due}`
                                            : ''}
                                    </div>
                                    {row.notes && (
                                        <p className="mt-1">{row.notes}</p>
                                    )}
                                </li>
                            ))}
                            {equipment.inspections.length === 0 && (
                                <li className="text-muted-foreground">
                                    No inspections yet.
                                </li>
                            )}
                        </ul>
                    </section>
                )}

                {tab === 'maintenance' && (
                    <section className="grid gap-6 lg:grid-cols-2">
                        {canManage && !isRetired && (
                            <MaintenanceForm equipmentId={equipment.id} />
                        )}
                        <ul className="space-y-2 text-sm">
                            {equipment.maintenances.map((row) => (
                                <li
                                    key={row.id}
                                    className="rounded-md border border-border px-3 py-2"
                                >
                                    <div className="font-medium">
                                        {row.performed_at} ·{' '}
                                        {row.maintenance_type_label ??
                                            MaintenanceTypeLabels[
                                                row.maintenance_type as MaintenanceType
                                            ] ??
                                            row.maintenance_type}
                                    </div>
                                    <p className="mt-1">{row.description}</p>
                                    <div className="text-muted-foreground">
                                        {row.performed_by_name ?? '—'}
                                        {row.next_due
                                            ? ` · next ${row.next_due}`
                                            : ''}
                                    </div>
                                </li>
                            ))}
                            {equipment.maintenances.length === 0 && (
                                <li className="text-muted-foreground">
                                    No maintenance logged.
                                </li>
                            )}
                        </ul>
                    </section>
                )}

                {tab === 'schedules' && (
                    <section className="max-w-xl">
                        {canManage && !isRetired ? (
                            <ScheduleEditor
                                equipmentId={equipment.id}
                                schedules={equipment.schedules}
                            />
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {equipment.schedules.map((row) => (
                                    <li key={row.id}>
                                        {row.schedule_type_label ??
                                            row.schedule_type}
                                        : every {row.interval_days} days
                                        {row.notes ? ` · ${row.notes}` : ''}
                                    </li>
                                ))}
                                {equipment.schedules.length === 0 && (
                                    <li className="text-muted-foreground">
                                        No schedules set.
                                    </li>
                                )}
                            </ul>
                        )}
                    </section>
                )}

                {tab === 'documents' && (
                    <section className="grid gap-6 lg:grid-cols-2">
                        {canManage && (
                            <Form
                                action={`/equipment/${equipment.id}/documents`}
                                method="post"
                                encType="multipart/form-data"
                                className="space-y-3"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="title">Title</Label>
                                            <Input
                                                id="title"
                                                name="title"
                                                required
                                                maxLength={150}
                                            />
                                            {errors.title && (
                                                <p className="text-sm text-destructive">
                                                    {errors.title}
                                                </p>
                                            )}
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
                                            />
                                            {errors.file && (
                                                <p className="text-sm text-destructive">
                                                    {errors.file}
                                                </p>
                                            )}
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
                        )}
                        <ul className="space-y-2 text-sm">
                            {equipment.documents.map((doc) => (
                                <li
                                    key={doc.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2"
                                >
                                    <span>
                                        {doc.title}
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {doc.mime}
                                        </span>
                                    </span>
                                    <div className="flex gap-2">
                                        {doc.download_url && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a href={doc.download_url}>
                                                    Download
                                                </a>
                                            </Button>
                                        )}
                                        {canManage && (
                                            <Form
                                                action={`/equipment/${equipment.id}/documents/${doc.id}`}
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
                                                        disabled={processing}
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </div>
                                </li>
                            ))}
                            {equipment.documents.length === 0 && (
                                <li className="text-muted-foreground">
                                    No documents.
                                </li>
                            )}
                        </ul>
                    </section>
                )}

                {tab === 'custody' && (
                    <section className="space-y-4">
                        {!equipment.is_checkoutable && (
                            <p className="text-sm text-muted-foreground">
                                This item is not checkoutable. Enable
                                checkoutable on Overview to use custody
                                tracking.
                            </p>
                        )}
                        {equipment.open_checkout && (
                            <div className="rounded-md border border-border p-3 text-sm">
                                <p className="font-medium">
                                    Currently checked out
                                </p>
                                <p>
                                    {equipment.open_checkout.worker?.name ??
                                        'Worker'}{' '}
                                    since{' '}
                                    {new Date(
                                        equipment.open_checkout.checked_out_at,
                                    ).toLocaleString()}
                                </p>
                                {equipment.open_checkout.reason && (
                                    <p>
                                        Reason: {equipment.open_checkout.reason}
                                    </p>
                                )}
                                {canManage && (
                                    <Button
                                        className="mt-2"
                                        size="sm"
                                        onClick={() => setReturnOpen(true)}
                                    >
                                        Return now
                                    </Button>
                                )}
                            </div>
                        )}
                        <h3 className="text-sm font-medium">History</h3>
                        <ul className="space-y-2 text-sm">
                            {equipment.checkouts.map((row) => (
                                <li
                                    key={row.id}
                                    className="rounded-md border border-border px-3 py-2"
                                >
                                    <div>
                                        {row.worker?.name ??
                                            `Worker #${row.worker_id}`}{' '}
                                        · out{' '}
                                        {new Date(
                                            row.checked_out_at,
                                        ).toLocaleString()}
                                        {row.returned_at
                                            ? ` · back ${new Date(row.returned_at).toLocaleString()}`
                                            : ' · open'}
                                    </div>
                                    {(row.reason || row.zone) && (
                                        <div className="text-muted-foreground">
                                            {row.reason ?? '—'}
                                            {row.zone
                                                ? ` · ${row.zone.name}`
                                                : ''}
                                        </div>
                                    )}
                                </li>
                            ))}
                            {equipment.checkouts.length === 0 && (
                                <li className="text-muted-foreground">
                                    No custody history.
                                </li>
                            )}
                        </ul>
                    </section>
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
