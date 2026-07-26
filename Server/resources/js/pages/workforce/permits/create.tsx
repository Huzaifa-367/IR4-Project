import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import type {
    PermitTypeSummary,
    WorkerOption,
    WorkOrderOption,
    ZoneOption,
} from '@/types/permit';

type Props = {
    permitTypes: PermitTypeSummary[];
    zones: ZoneOption[];
    workOrders: WorkOrderOption[];
    workers: WorkerOption[];
    initialWorkOrderId?: number | null;
};

type PersonnelRow = {
    worker_id: string;
    role_code: string;
};

function eligibilityFor(
    worker: WorkerOption | undefined,
    permitTypeId: string,
    roleCode: string,
): { ready: boolean; missing: string[]; missing_labels: string[] } | null {
    if (!worker || !permitTypeId || !roleCode) {
        return null;
    }

    return (
        worker.role_eligibility?.[`${permitTypeId}:${roleCode}`] ?? {
            ready: true,
            missing: [],
            missing_labels: [],
        }
    );
}

function eligibilityTone(
    eligibility: { ready: boolean } | null,
): StatusPillTone {
    if (!eligibility) {
        return 'neutral';
    }

    return eligibility.ready ? 'ok' : 'crit';
}

export default function PermitCreate({
    permitTypes,
    zones,
    workOrders,
    workers,
    initialWorkOrderId = null,
}: Props) {
    const page = usePage();
    const queryWorkOrderId =
        typeof page.url === 'string'
            ? new URL(page.url, 'http://local').searchParams.get('work_order_id')
            : null;

    const resolvedWorkOrderId = (
        initialWorkOrderId ??
        (queryWorkOrderId ? Number(queryWorkOrderId) : null) ??
        ''
    ).toString();

    const initialZoneId =
        workOrders
            .find((order) => order.id.toString() === resolvedWorkOrderId)
            ?.zone?.id?.toString() ?? '';

    const [permitTypeId, setPermitTypeId] = useState(
        permitTypes[0]?.id?.toString() ?? '',
    );
    const [workOrderId, setWorkOrderId] = useState(resolvedWorkOrderId);
    const [zoneId, setZoneId] = useState(initialZoneId);
    const [personnel, setPersonnel] = useState<PersonnelRow[]>([
        { worker_id: '', role_code: '' },
    ]);

    const selectedType = useMemo(
        () =>
            permitTypes.find((type) => type.id.toString() === permitTypeId) ??
            null,
        [permitTypeId, permitTypes],
    );

    const selectedWorkOrder = useMemo(
        () =>
            workOrders.find((order) => order.id.toString() === workOrderId) ??
            null,
        [workOrderId, workOrders],
    );

    function addPersonnelRow(): void {
        setPersonnel((rows) => [...rows, { worker_id: '', role_code: '' }]);
    }

    function removePersonnelRow(index: number): void {
        setPersonnel((rows) => rows.filter((_, i) => i !== index));
    }

    function updatePersonnelRow(
        index: number,
        field: keyof PersonnelRow,
        value: string,
    ): void {
        setPersonnel((rows) =>
            rows.map((row, i) => {
                if (i !== index) {
                    return row;
                }

                if (field === 'role_code') {
                    return { ...row, role_code: value, worker_id: '' };
                }

                return { ...row, [field]: value };
            }),
        );
    }

    function workersForRole(roleCode: string): WorkerOption[] {
        if (!roleCode || !permitTypeId) {
            return workers;
        }

        return workers.filter((worker) => {
            const eligibility = eligibilityFor(worker, permitTypeId, roleCode);

            return eligibility?.ready ?? true;
        });
    }

    return (
        <>
            <Head title="Request permit" />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Request permit"
                        description="Job details + ready crew. Checklist and gas tests happen on the draft after create."
                    />
                    <Button asChild variant="outline">
                        <Link
                            href={
                                selectedWorkOrder
                                    ? `/workforce/work-orders/${selectedWorkOrder.uuid}`
                                    : '/workforce/permits'
                            }
                        >
                            Back
                        </Link>
                    </Button>
                </div>

                <Form
                    action="/workforce/permits"
                    method="post"
                    className="space-y-8"
                    transform={(data) => ({
                        ...data,
                        work_order_id: workOrderId || null,
                        permit_type_id: permitTypeId || null,
                        zone_id: zoneId || null,
                        personnel: personnel
                            .filter(
                                (row) =>
                                    row.worker_id !== '' ||
                                    row.role_code !== '',
                            )
                            .map((row) => ({
                                worker_id: row.worker_id,
                                role_code: row.role_code,
                            })),
                    })}
                >
                    {({ processing, errors }) => (
                        <>
                            <section className="space-y-4 rounded-lg border border-border p-4">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-text-faint">
                                        1 · Job
                                    </p>
                                    <h2 className="text-sm font-semibold text-text">
                                        What work is this for?
                                    </h2>
                                </div>

                                {workOrders.length > 0 ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="work_order_id">
                                            Work order
                                        </Label>
                                        <SearchableSelect
                                            id="work_order_id"
                                            value={workOrderId}
                                            onValueChange={(nextId) => {
                                                setWorkOrderId(nextId);
                                                const order = workOrders.find(
                                                    (item) =>
                                                        item.id.toString() ===
                                                        nextId,
                                                );

                                                if (order?.zone) {
                                                    setZoneId(
                                                        String(order.zone.id),
                                                    );
                                                }
                                            }}
                                            allowClear
                                            clearLabel="Standalone (no work order)"
                                            placeholder="Standalone (no work order)"
                                            options={workOrders.map((order) => ({
                                                value: String(order.id),
                                                label: `${order.reference}${
                                                    order.zone
                                                        ? ` · ${order.zone.name}`
                                                        : ''
                                                }`,
                                            }))}
                                        />
                                        {errors.work_order_id && (
                                            <p className="text-sm text-destructive">
                                                {errors.work_order_id}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Optional. Use a work order to group
                                            related permits for one job package.
                                        </p>
                                    </div>
                                ) : null}

                                <div className="grid gap-2">
                                    <Label htmlFor="permit_type_id">
                                        Permit type
                                    </Label>
                                    <SearchableSelect
                                        id="permit_type_id"
                                        value={permitTypeId}
                                        onValueChange={(value) => {
                                            setPermitTypeId(value);
                                            setPersonnel([
                                                {
                                                    worker_id: '',
                                                    role_code: '',
                                                },
                                            ]);
                                        }}
                                        required
                                        options={permitTypes.map((type) => ({
                                            value: String(type.id),
                                            label: type.name,
                                        }))}
                                    />
                                    {errors.permit_type_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.permit_type_id}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="zone_id">Zone</Label>
                                    <SearchableSelect
                                        id="zone_id"
                                        value={zoneId}
                                        onValueChange={setZoneId}
                                        allowClear
                                        clearLabel="—"
                                        placeholder="—"
                                        options={zones.map((zone) => ({
                                            value: String(zone.id),
                                            label: `${zone.name}${
                                                zone.requires_permit
                                                    ? ' (permit required)'
                                                    : ''
                                            }`,
                                        }))}
                                    />
                                    {errors.zone_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.zone_id}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="task_description">
                                        Task
                                    </Label>
                                    <textarea
                                        id="task_description"
                                        name="task_description"
                                        rows={3}
                                        required
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                        placeholder="Short description of the work…"
                                    />
                                    {errors.task_description && (
                                        <p className="text-sm text-destructive">
                                            {errors.task_description}
                                        </p>
                                    )}
                                </div>

                                {selectedType?.allows_extended ? (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="is_extended"
                                            value="1"
                                            className="rounded border-input"
                                        />
                                        Extended validity (needs approver)
                                    </label>
                                ) : null}
                            </section>

                            <section className="space-y-4 rounded-lg border border-border p-4">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="text-xs font-medium uppercase tracking-wide text-text-faint">
                                            2 · Crew
                                        </p>
                                        <h2 className="text-sm font-semibold text-text">
                                            Who is authorized?
                                        </h2>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Only workers ready for the role
                                            (documents verified).{' '}
                                            <Link
                                                href="/workforce/workers?create=1"
                                                className="underline"
                                            >
                                                Add worker
                                            </Link>
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={addPersonnelRow}
                                        disabled={
                                            (selectedType?.roles.length ??
                                                0) === 0
                                        }
                                    >
                                        Add person
                                    </Button>
                                </div>

                                {(selectedType?.roles.length ?? 0) === 0 ? (
                                    <p className="rounded-md border border-dashed border-border px-3 py-2 text-sm text-muted-foreground">
                                        No crew roles on this type. Configure
                                        them under Catalogue → Permit types.
                                    </p>
                                ) : null}

                                {personnel.map((row, index) => {
                                    const worker = workers.find(
                                        (item) =>
                                            item.id.toString() ===
                                            row.worker_id,
                                    );
                                    const eligibility = eligibilityFor(
                                        worker,
                                        permitTypeId,
                                        row.role_code,
                                    );
                                    const roleWorkers = workersForRole(
                                        row.role_code,
                                    );

                                    return (
                                        <div
                                            key={index}
                                            className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-[1fr_1fr_auto]"
                                        >
                                            <div className="grid gap-1">
                                                <Label>Role</Label>
                                                <SearchableSelect
                                                    value={row.role_code}
                                                    onValueChange={(value) =>
                                                        updatePersonnelRow(
                                                            index,
                                                            'role_code',
                                                            value,
                                                        )
                                                    }
                                                    disabled={
                                                        (selectedType?.roles
                                                            .length ?? 0) === 0
                                                    }
                                                    required={
                                                        row.worker_id !== ''
                                                    }
                                                    allowClear
                                                    clearLabel="Select role"
                                                    placeholder="Select role"
                                                    options={(
                                                        selectedType?.roles ??
                                                        []
                                                    ).map((role) => ({
                                                        value: role.role_code,
                                                        label: role.label,
                                                    }))}
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label>Worker</Label>
                                                <SearchableSelect
                                                    value={row.worker_id}
                                                    onValueChange={(value) =>
                                                        updatePersonnelRow(
                                                            index,
                                                            'worker_id',
                                                            value,
                                                        )
                                                    }
                                                    disabled={
                                                        row.role_code === ''
                                                    }
                                                    allowClear
                                                    clearLabel={
                                                        row.role_code === ''
                                                            ? 'Pick role first'
                                                            : roleWorkers.length ===
                                                                0
                                                              ? 'No ready workers'
                                                              : 'Select worker'
                                                    }
                                                    placeholder={
                                                        row.role_code === ''
                                                            ? 'Pick role first'
                                                            : roleWorkers.length ===
                                                                0
                                                              ? 'No ready workers'
                                                              : 'Select worker'
                                                    }
                                                    options={roleWorkers.map(
                                                        (item) => ({
                                                            value: String(
                                                                item.id,
                                                            ),
                                                            label: `${item.label}${
                                                                item.reference
                                                                    ? ` (${item.reference})`
                                                                    : ''
                                                            }`,
                                                        }),
                                                    )}
                                                />
                                            </div>
                                            <div className="flex flex-col items-start gap-1 self-end">
                                                {row.worker_id &&
                                                    row.role_code && (
                                                        <StatusPill
                                                            label={
                                                                eligibility?.ready
                                                                    ? 'Ready'
                                                                    : 'Blocked'
                                                            }
                                                            tone={eligibilityTone(
                                                                eligibility,
                                                            )}
                                                        />
                                                    )}
                                                {eligibility &&
                                                    !eligibility.ready &&
                                                    row.worker_id && (
                                                        <Link
                                                            href={`/workforce/workers/${row.worker_id}?onboarding=1`}
                                                            className="text-xs underline"
                                                        >
                                                            Fix documents
                                                        </Link>
                                                    )}
                                                {personnel.length > 1 && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            removePersonnelRow(
                                                                index,
                                                            )
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                                {errors.personnel && (
                                    <p className="text-sm text-destructive">
                                        {errors.personnel}
                                    </p>
                                )}
                            </section>

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-xs text-muted-foreground">
                                    Creates a draft. Complete checklist /
                                    inspection / gas on the next screen.
                                </p>
                                <Button type="submit" disabled={processing}>
                                    Create draft
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PermitCreate.layout = {
    breadcrumbs: [
        { title: 'Workforce', href: '/workforce/workers' },
        { title: 'Permits', href: '/workforce/permits' },
        { title: 'Request', href: '/workforce/permits/create' },
    ],
};
