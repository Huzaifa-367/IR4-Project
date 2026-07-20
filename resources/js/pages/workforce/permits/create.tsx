import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type {
    PermitDocumentRequirement,
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

function docToneForWorker(
    worker: WorkerOption | undefined,
    roleCode: string,
    requirements: PermitDocumentRequirement[],
): StatusPillTone {
    if (!worker || !roleCode) {
        return 'neutral';
    }

    const mandatory = requirements.filter(
        (req) =>
            req.is_mandatory &&
            (req.role_code === null || req.role_code === roleCode),
    );

    if (mandatory.length === 0) {
        return 'ok';
    }

    const verified = new Set(worker.verified_document_codes ?? []);
    const missing = mandatory.filter(
        (req) =>
            req.worker_document_type &&
            !verified.has(req.worker_document_type.code),
    );

    if (missing.length > 0) {
        return 'crit';
    }

    return 'ok';
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
            rows.map((row, i) =>
                i === index ? { ...row, [field]: value } : row,
            ),
        );
    }

    function toggleChecklistItem(code: string, checked: boolean): void {
        setChecklist((current) => ({ ...current, [code]: checked }));
    }

    return (
        <>
            <Head title="Request permit" />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Request permit"
                        description="Select type, describe the task, and assign crew with required roles."
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
                                <div className="flex items-center justify-between gap-2">
                                    <Label>Personnel</Label>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={addPersonnelRow}
                                        disabled={
                                            (selectedType?.roles.length ?? 0) ===
                                            0
                                        }
                                    >
                                        Add row
                                    </Button>
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
                                    const docTone = docToneForWorker(
                                        worker,
                                        row.role_code,
                                        selectedType?.document_requirements ?? [],
                                    );

                                    return (
                                        <div
                                            key={index}
                                            className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-[1fr_1fr_auto]"
                                        >
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
                                                >
                                                    <option value="">—</option>
                                                    {workers.map((item) => (
                                                        <option
                                                            key={item.id}
                                                            value={item.id}
                                                        >
                                                            {item.label}
                                                            {item.reference
                                                                ? ` (${item.reference})`
                                                                : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
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
                                            <div className="flex flex-col items-start gap-1 self-end">
                                                {row.worker_id &&
                                                    row.role_code && (
                                                        <StatusPill
                                                            label="Documents"
                                                            tone={docTone}
                                                        />
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
