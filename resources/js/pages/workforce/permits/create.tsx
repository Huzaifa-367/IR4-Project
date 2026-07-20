import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type {
    PermitTypeSummary,
    WorkerOption,
    ZoneOption,
} from '@/types/permit';

type Props = {
    permitTypes: PermitTypeSummary[];
    zones: ZoneOption[];
    workers: WorkerOption[];
};

type PersonnelRow = {
    worker_id: string;
    role_code: string;
};

export default function PermitCreate({ permitTypes, zones, workers }: Props) {
    const [permitTypeId, setPermitTypeId] = useState(
        permitTypes[0]?.id?.toString() ?? '',
    );
    const [personnel, setPersonnel] = useState<PersonnelRow[]>([
        { worker_id: '', role_code: '' },
    ]);

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
                                    onChange={(event) =>
                                        setPermitTypeId(event.target.value)
                                    }
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
                                    >
                                        Add row
                                    </Button>
                                </div>
                                {personnel.map((row, index) => (
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
                                                {workers.map((worker) => (
                                                    <option
                                                        key={worker.id}
                                                        value={worker.id}
                                                    >
                                                        {worker.label}
                                                        {worker.reference
                                                            ? ` (${worker.reference})`
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
                                            >
                                                <option value="">—</option>
                                                {selectedType?.roles.map(
                                                    (role) => (
                                                        <option
                                                            key={
                                                                role.role_code
                                                            }
                                                            value={
                                                                role.role_code
                                                            }
                                                        >
                                                            {role.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </div>
                                        {personnel.length > 1 && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                className="self-end"
                                                onClick={() =>
                                                    removePersonnelRow(index)
                                                }
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                {errors.personnel && (
                                    <p className="text-sm text-destructive">
                                        {errors.personnel}
                                    </p>
                                )}
                            </div>

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
