import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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

function docToneForEligibility(
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
}: Props) {
    const [permitTypeId, setPermitTypeId] = useState(
        permitTypes[0]?.id?.toString() ?? '',
    );
    const [personnel, setPersonnel] = useState<PersonnelRow[]>([
        { worker_id: '', role_code: '' },
    ]);
    const [checklist, setChecklist] = useState<Record<string, boolean>>({});
    const [showBlockedWorkers, setShowBlockedWorkers] = useState(false);

    const selectedType = useMemo(
        () =>
            permitTypes.find(
                (type) => type.id.toString() === permitTypeId,
            ) ?? null,
        [permitTypeId, permitTypes],
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

    function toggleChecklistItem(code: string, checked: boolean): void {
        setChecklist((current) => ({ ...current, [code]: checked }));
    }

    function workersForRole(roleCode: string): WorkerOption[] {
        if (!roleCode || !permitTypeId) {
            return workers;
        }

        return workers.filter((worker) => {
            const eligibility = eligibilityFor(worker, permitTypeId, roleCode);
            if (showBlockedWorkers) {
                return true;
            }

            return eligibility?.ready ?? true;
        });
    }

    return (
        <>
            <Head title="Request permit" />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Request permit"
                        description="Assign crew by role. Documents are managed on the worker profile — only ready workers appear by default."
                    />
                    <Button asChild variant="outline">
                        <Link href="/workforce/permits">Back</Link>
                    </Button>
                </div>

                <Form
                    action="/workforce/permits"
                    method="post"
                    className="space-y-6 rounded-lg border border-border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="permit_type_id">
                                    Permit type
                                </Label>
                                <select
                                    id="permit_type_id"
                                    name="permit_type_id"
                                    value={permitTypeId}
                                    onChange={(event) => {
                                        setPermitTypeId(event.target.value);
                                        setChecklist({});
                                        setPersonnel((rows) =>
                                            rows.map((row) => ({
                                                ...row,
                                                role_code: '',
                                                worker_id: '',
                                            })),
                                        );
                                    }}
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    required
                                >
                                    {permitTypes.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.permit_type_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.permit_type_id}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="zone_id">Zone</Label>
                                <select
                                    id="zone_id"
                                    name="zone_id"
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option value="">—</option>
                                    {zones.map((zone) => (
                                        <option key={zone.id} value={zone.id}>
                                            {zone.name}
                                            {zone.requires_permit
                                                ? ' (permit required)'
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {workOrders.length > 0 ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="work_order_id">
                                        Work order (optional)
                                    </Label>
                                    <select
                                        id="work_order_id"
                                        name="work_order_id"
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    >
                                        <option value="">—</option>
                                        {workOrders.map((order) => (
                                            <option
                                                key={order.id}
                                                value={order.id}
                                            >
                                                {order.reference}
                                                {order.zone
                                                    ? ` · ${order.zone.name}`
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.work_order_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.work_order_id}
                                        </p>
                                    )}
                                </div>
                            ) : null}

                            <div className="grid gap-2">
                                <Label htmlFor="task_description">Task</Label>
                                <textarea
                                    id="task_description"
                                    name="task_description"
                                    rows={4}
                                    required
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    placeholder="Describe the work to be performed…"
                                />
                                {errors.task_description && (
                                    <p className="text-sm text-destructive">
                                        {errors.task_description}
                                    </p>
                                )}
                            </div>

                            {selectedType?.allows_extended && (
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="is_extended"
                                        value="1"
                                        className="rounded border-input"
                                    />
                                    Extended permit (requires approver)
                                </label>
                            )}

                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <Label>Personnel</Label>
                                        <p className="text-xs text-muted-foreground">
                                            Pick role first, then a ready worker.
                                            Missing docs? Fix them on the worker
                                            profile.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <input
                                                type="checkbox"
                                                checked={showBlockedWorkers}
                                                onChange={(event) =>
                                                    setShowBlockedWorkers(
                                                        event.target.checked,
                                                    )
                                                }
                                                className="rounded border-input"
                                            />
                                            Show blocked workers
                                        </label>
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
                                            Add row
                                        </Button>
                                    </div>
                                </div>
                                {(selectedType?.roles.length ?? 0) === 0 ? (
                                    <p className="rounded-md border border-dashed border-border px-3 py-2 text-sm text-muted-foreground">
                                        No crew roles are configured for this
                                        permit type. Add roles under Access →
                                        Crew roles before assigning personnel.
                                    </p>
                                ) : null}
                                {personnel.map((row, index) => {
                                    const worker = workers.find(
                                        (item) =>
                                            item.id.toString() === row.worker_id,
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
                                                <select
                                                    name={`personnel[${index}][role_code]`}
                                                    value={row.role_code}
                                                    onChange={(event) =>
                                                        updatePersonnelRow(
                                                            index,
                                                            'role_code',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                                    disabled={
                                                        (selectedType?.roles
                                                            .length ?? 0) === 0
                                                    }
                                                    required={
                                                        row.worker_id !== ''
                                                    }
                                                >
                                                    <option value="">
                                                        Select role
                                                    </option>
                                                    {(
                                                        selectedType?.roles ?? []
                                                    ).map((role) => (
                                                        <option
                                                            key={role.role_code}
                                                            value={
                                                                role.role_code
                                                            }
                                                        >
                                                            {role.label}
                                                            {role.is_mandatory
                                                                ? ` (min ${role.min_count})`
                                                                : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label>Worker</Label>
                                                <select
                                                    name={`personnel[${index}][worker_id]`}
                                                    value={row.worker_id}
                                                    onChange={(event) =>
                                                        updatePersonnelRow(
                                                            index,
                                                            'worker_id',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                                    disabled={
                                                        row.role_code === ''
                                                    }
                                                >
                                                    <option value="">
                                                        {row.role_code === ''
                                                            ? 'Select role first'
                                                            : roleWorkers.length ===
                                                                0
                                                              ? 'No ready workers'
                                                              : 'Select worker'}
                                                    </option>
                                                    {roleWorkers.map((item) => {
                                                        const itemEligibility =
                                                            eligibilityFor(
                                                                item,
                                                                permitTypeId,
                                                                row.role_code,
                                                            );
                                                        const ready =
                                                            itemEligibility?.ready ??
                                                            true;

                                                        return (
                                                            <option
                                                                key={item.id}
                                                                value={item.id}
                                                            >
                                                                {item.label}
                                                                {item.reference
                                                                    ? ` (${item.reference})`
                                                                    : ''}
                                                                {ready
                                                                    ? ''
                                                                    : ' — blocked'}
                                                            </option>
                                                        );
                                                    })}
                                                </select>
                                            </div>
                                            <div className="flex flex-col items-start gap-1 self-end">
                                                {row.worker_id &&
                                                    row.role_code && (
                                                        <>
                                                            <StatusPill
                                                                label={
                                                                    eligibility?.ready
                                                                        ? 'Ready'
                                                                        : 'Blocked'
                                                                }
                                                                tone={docToneForEligibility(
                                                                    eligibility,
                                                                )}
                                                            />
                                                            {eligibility &&
                                                                !eligibility.ready && (
                                                                    <p className="max-w-[12rem] text-xs text-destructive">
                                                                        Missing:{' '}
                                                                        {(
                                                                            eligibility.missing_labels
                                                                                .length >
                                                                            0
                                                                                ? eligibility.missing_labels
                                                                                : eligibility.missing
                                                                        ).join(
                                                                            ', ',
                                                                        )}
                                                                        .{' '}
                                                                        <Link
                                                                            href={`/workforce/workers/${row.worker_id}?onboarding=1`}
                                                                            className="underline"
                                                                        >
                                                                            Fix
                                                                            on
                                                                            worker
                                                                        </Link>
                                                                    </p>
                                                                )}
                                                        </>
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
                                <p className="text-xs text-muted-foreground">
                                    Need a new crew member?{' '}
                                    <Link
                                        href="/workforce/workers/create"
                                        className="underline"
                                    >
                                        Add worker
                                    </Link>{' '}
                                    and complete their documents before
                                    assigning them here.
                                </p>
                            </div>

                            {selectedType &&
                                selectedType.checklist_items.length > 0 && (
                                    <div className="space-y-3">
                                        <Label>Checklist / JSA</Label>
                                        <ul className="space-y-2">
                                            {selectedType.checklist_items.map(
                                                (item) => (
                                                    <li
                                                        key={item.code}
                                                        className="flex items-start gap-2 rounded-md border border-border px-3 py-2 text-sm"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            id={`checklist-${item.code}`}
                                                            name={`checklist[${item.code}]`}
                                                            value="1"
                                                            checked={
                                                                checklist[
                                                                    item.code
                                                                ] ?? false
                                                            }
                                                            onChange={(event) =>
                                                                toggleChecklistItem(
                                                                    item.code,
                                                                    event.target
                                                                        .checked,
                                                                )
                                                            }
                                                            className="mt-0.5 rounded border-input"
                                                        />
                                                        <label
                                                            htmlFor={`checklist-${item.code}`}
                                                            className="flex-1"
                                                        >
                                                            {item.label}
                                                            {item.is_mandatory && (
                                                                <span className="text-muted-foreground">
                                                                    {' '}
                                                                    (required)
                                                                </span>
                                                            )}
                                                        </label>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                        {errors.checklist && (
                                            <p className="text-sm text-destructive">
                                                {errors.checklist}
                                            </p>
                                        )}
                                    </div>
                                )}

                            <div className="flex justify-end gap-2">
                                <Button type="submit" disabled={processing}>
                                    Save draft
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
