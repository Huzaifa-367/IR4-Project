import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import {
    CustodyBadge,
    EquipmentStatusBadge,
    OverdueBadge,
} from '@/components/ir4/equipment-badges';
import { EquipmentForm } from '@/components/ir4/equipment-form';
import { EquipmentScanEntry } from '@/components/ir4/equipment-scan-entry';
import {
    QrLabelButton,
    QrLabelsBulkButton,
} from '@/components/ir4/qr-label-button';
import { RequirePermission } from '@/components/ir4/require-permission';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { EquipmentStatus, EquipmentStatusLabels } from '@/types/enums';
import type {
    EquipmentListFilters,
    EquipmentOption,
    EquipmentWorkerRef,
    EquipmentZoneRef,
    PaginatedEquipment,
} from '@/types/equipment';

type Props = {
    equipment: PaginatedEquipment;
    filters: EquipmentListFilters;
    statusOptions?: EquipmentOption[];
    typeOptions?: string[];
    workers?: EquipmentWorkerRef[];
    zones?: EquipmentZoneRef[];
    canManage: boolean;
};

export default function EquipmentIndex({
    equipment,
    filters,
    statusOptions,
    typeOptions = [],
    workers = [],
    zones = [],
    canManage,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [createOpen, setCreateOpen] = useState(false);

    const statuses =
        statusOptions ??
        Object.values(EquipmentStatus).map((value) => ({
            value,
            label: EquipmentStatusLabels[value],
        }));

    function applyFilters(patch: Partial<EquipmentListFilters>): void {
        router.get(
            '/equipment',
            {
                search: patch.search ?? filters.search,
                equipment_type: patch.equipment_type ?? filters.equipment_type,
                status: patch.status ?? filters.status,
                overdue:
                    patch.overdue === undefined
                        ? filters.overdue === null
                            ? undefined
                            : filters.overdue
                              ? '1'
                              : '0'
                        : patch.overdue === null
                          ? undefined
                          : patch.overdue
                            ? '1'
                            : '0',
                checkout_state: patch.checkout_state ?? filters.checkout_state,
                sort: patch.sort ?? filters.sort,
                direction: patch.direction ?? filters.direction,
            },
            { preserveState: true, replace: true },
        );
    }

    function toggleSelected(id: number): void {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((row) => row !== id)
                : [...current, id],
        );
    }

    function toggleAll(): void {
        if (selected.length === equipment.data.length) {
            setSelected([]);

            return;
        }

        setSelected(equipment.data.map((row) => row.id));
    }

    return (
        <>
            <Head title="Equipment" />
            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Equipment"
                        description="QR registry, inspections, maintenance, and custody."
                    />
                    <div className="flex flex-wrap gap-2">
                        <RequirePermission permission="manage-equipment">
                            <EquipmentScanEntry
                                workers={workers}
                                zones={zones}
                                canManage={canManage}
                            />
                        </RequirePermission>
                        {canManage && (
                            <>
                                <Button asChild variant="outline" size="sm">
                                    <Link href="/equipment/import">Import</Link>
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={() => setCreateOpen(true)}
                                >
                                    Add equipment
                                </Button>
                            </>
                        )}
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/equipment/checkouts">Checkouts</Link>
                        </Button>
                    </div>
                </div>

                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        applyFilters({
                            search: String(form.get('search') ?? ''),
                            equipment_type: String(
                                form.get('equipment_type') ?? '',
                            ),
                            status: String(form.get('status') ?? ''),
                            checkout_state: String(
                                form.get('checkout_state') ?? '',
                            ),
                            overdue:
                                form.get('overdue') === '1'
                                    ? true
                                    : form.get('overdue') === '0'
                                      ? false
                                      : null,
                        });
                    }}
                >
                    <div className="grid gap-1">
                        <label
                            className="text-xs text-muted-foreground"
                            htmlFor="search"
                        >
                            Search
                        </label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Code, name, type…"
                            className="w-48"
                        />
                    </div>
                    <div className="grid gap-1">
                        <label
                            className="text-xs text-muted-foreground"
                            htmlFor="equipment_type"
                        >
                            Type
                        </label>
                        {typeOptions.length > 0 ? (
                            <select
                                id="equipment_type"
                                name="equipment_type"
                                defaultValue={filters.equipment_type}
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">All</option>
                                {typeOptions.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <Input
                                id="equipment_type"
                                name="equipment_type"
                                defaultValue={filters.equipment_type}
                                className="w-40"
                            />
                        )}
                    </div>
                    <div className="grid gap-1">
                        <label
                            className="text-xs text-muted-foreground"
                            htmlFor="status"
                        >
                            Status
                        </label>
                        <select
                            id="status"
                            name="status"
                            defaultValue={filters.status}
                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">All</option>
                            {statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <label
                            className="text-xs text-muted-foreground"
                            htmlFor="checkout_state"
                        >
                            Custody
                        </label>
                        <select
                            id="checkout_state"
                            name="checkout_state"
                            defaultValue={filters.checkout_state}
                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">All</option>
                            <option value="available">Available</option>
                            <option value="checked_out">Checked out</option>
                            <option value="overdue_return">
                                Overdue return
                            </option>
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <label
                            className="text-xs text-muted-foreground"
                            htmlFor="overdue"
                        >
                            Due
                        </label>
                        <select
                            id="overdue"
                            name="overdue"
                            defaultValue={
                                filters.overdue === null
                                    ? ''
                                    : filters.overdue
                                      ? '1'
                                      : '0'
                            }
                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Any</option>
                            <option value="1">Overdue</option>
                            <option value="0">Not overdue</option>
                        </select>
                    </div>
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <QrLabelsBulkButton ids={selected} />
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                {canManage && (
                                    <th className="px-3 py-2">
                                        <input
                                            type="checkbox"
                                            checked={
                                                equipment.data.length > 0 &&
                                                selected.length ===
                                                    equipment.data.length
                                            }
                                            onChange={toggleAll}
                                            aria-label="Select all"
                                        />
                                    </th>
                                )}
                                <th className="px-3 py-2 font-medium">Code</th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Custody
                                </th>
                                <th className="px-3 py-2 font-medium">Due</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {equipment.data.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-t border-border"
                                >
                                    {canManage && (
                                        <td className="px-3 py-2">
                                            <input
                                                type="checkbox"
                                                checked={selected.includes(
                                                    row.id,
                                                )}
                                                onChange={() =>
                                                    toggleSelected(row.id)
                                                }
                                                aria-label={`Select ${row.equipment_code}`}
                                            />
                                        </td>
                                    )}
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {row.equipment_code}
                                    </td>
                                    <td className="px-3 py-2">{row.name}</td>
                                    <td className="px-3 py-2">
                                        {row.equipment_type}
                                    </td>
                                    <td className="px-3 py-2">
                                        <EquipmentStatusBadge
                                            status={row.status}
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <CustodyBadge
                                            state={row.checkout_state}
                                            workerName={
                                                row.open_checkout?.worker?.name
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-1">
                                            <OverdueBadge
                                                isInspectionOverdue={
                                                    row.is_inspection_overdue
                                                }
                                                isServiceOverdue={
                                                    row.is_service_overdue
                                                }
                                                isDueSoon={row.is_due_soon}
                                                isReturnOverdue={
                                                    row.checkout_state ===
                                                    'overdue_return'
                                                }
                                            />
                                            {!row.is_inspection_overdue &&
                                                !row.is_service_overdue &&
                                                !row.is_due_soon &&
                                                row.checkout_state !==
                                                    'overdue_return' && (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <div className="flex justify-end gap-1">
                                            <QrLabelButton
                                                equipmentId={row.id}
                                                label="Print"
                                                size="sm"
                                                variant="ghost"
                                            />
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <Link
                                                    href={`/equipment/${row.id}`}
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {equipment.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canManage ? 8 : 7}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No equipment matches these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-sm text-muted-foreground">
                    Page {equipment.meta.current_page} of{' '}
                    {equipment.meta.last_page} · {equipment.meta.total} total
                </p>
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Add equipment</DialogTitle>
                    </DialogHeader>
                    <EquipmentForm
                        action="/equipment"
                        method="post"
                        submitLabel="Create equipment"
                    />
                </DialogContent>
            </Dialog>
        </>
    );
}
