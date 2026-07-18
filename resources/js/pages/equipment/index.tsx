import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { EquipmentStatus, EquipmentStatusLabels } from '@/types/enums';
import type {
    Equipment,
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

const ALL = 'all';

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
    const [search, setSearch] = useState(filters.search);
    const [equipmentType, setEquipmentType] = useState(
        filters.equipment_type || ALL,
    );
    const [status, setStatus] = useState(filters.status || ALL);
    const [checkoutState, setCheckoutState] = useState(
        filters.checkout_state || ALL,
    );
    const [overdue, setOverdue] = useState(
        filters.overdue === null ? ALL : filters.overdue ? '1' : '0',
    );

    const statuses =
        statusOptions ??
        Object.values(EquipmentStatus).map((value) => ({
            value,
            label: EquipmentStatusLabels[value],
        }));

    const queryParams = {
        search: search || undefined,
        equipment_type: equipmentType === ALL ? undefined : equipmentType,
        status: status === ALL ? undefined : status,
        checkout_state: checkoutState === ALL ? undefined : checkoutState,
        overdue: overdue === ALL ? undefined : overdue,
    };

    function applyFilters(): void {
        router.get('/equipment', queryParams, {
            preserveState: true,
            replace: true,
        });
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

    const columns: SettingsColumn<Equipment>[] = [
        ...(canManage
            ? [
                  {
                      key: 'select',
                      header: (
                          <Checkbox
                              checked={
                                  equipment.data.length > 0 &&
                                  selected.length === equipment.data.length
                              }
                              onCheckedChange={toggleAll}
                              aria-label="Select all"
                          />
                      ),
                      className: 'w-8',
                      cell: (row: Equipment) => (
                          <Checkbox
                              checked={selected.includes(row.id)}
                              onCheckedChange={() => toggleSelected(row.id)}
                              aria-label={`Select ${row.equipment_code}`}
                          />
                      ),
                  } satisfies SettingsColumn<Equipment>,
              ]
            : []),
        {
            key: 'code',
            header: 'Code',
            cell: (row) => (
                <span className="font-mono text-xs">{row.equipment_code}</span>
            ),
        },
        {
            key: 'name',
            header: 'Name',
            cell: (row) => (
                <Link
                    href={`/equipment/${row.id}`}
                    className="font-medium text-text hover:underline"
                >
                    {row.name}
                </Link>
            ),
        },
        { key: 'type', header: 'Type', cell: (row) => row.equipment_type },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => <EquipmentStatusBadge status={row.status} />,
        },
        {
            key: 'custody',
            header: 'Custody',
            cell: (row) => (
                <CustodyBadge
                    state={row.checkout_state}
                    workerName={row.open_checkout?.worker?.name}
                />
            ),
        },
        {
            key: 'due',
            header: 'Due',
            cell: (row) => (
                <div className="flex flex-wrap gap-1">
                    <OverdueBadge
                        isInspectionOverdue={row.is_inspection_overdue}
                        isServiceOverdue={row.is_service_overdue}
                        isDueSoon={row.is_due_soon}
                        isReturnOverdue={
                            row.checkout_state === 'overdue_return'
                        }
                    />
                    {!row.is_inspection_overdue &&
                        !row.is_service_overdue &&
                        !row.is_due_soon &&
                        row.checkout_state !== 'overdue_return' && (
                            <span className="text-xs text-text-faint">—</span>
                        )}
                </div>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-24 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <QrLabelButton
                        equipmentId={row.id}
                        label="Print"
                        size="sm"
                        variant="ghost"
                    />
                    <Button asChild size="sm" variant="ghost">
                        <Link href={`/equipment/${row.id}`}>View</Link>
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Equipment" />
            <SettingsPageShell
                title="Equipment"
                description="QR registry, inspections, maintenance, and custody."
                actions={
                    <>
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
                    </>
                }
                filters={
                    <>
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Code, name, type…"
                            className="w-full sm:w-56"
                            aria-label="Search equipment"
                        />
                        <Select
                            value={equipmentType}
                            onValueChange={setEquipmentType}
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All types
                                    </SelectItem>
                                    {typeOptions.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All statuses
                                    </SelectItem>
                                    {statuses.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select
                            value={checkoutState}
                            onValueChange={setCheckoutState}
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Custody" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>All</SelectItem>
                                    <SelectItem value="available">
                                        Available
                                    </SelectItem>
                                    <SelectItem value="checked_out">
                                        Checked out
                                    </SelectItem>
                                    <SelectItem value="overdue_return">
                                        Overdue return
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select value={overdue} onValueChange={setOverdue}>
                            <SelectTrigger className="w-32">
                                <SelectValue placeholder="Due" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>Any</SelectItem>
                                    <SelectItem value="1">Overdue</SelectItem>
                                    <SelectItem value="0">
                                        Not overdue
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={applyFilters}
                        >
                            Apply
                        </Button>
                    </>
                }
            >
                {canManage && selected.length > 0 ? (
                    <div className="mb-3 flex flex-wrap gap-2">
                        <QrLabelsBulkButton ids={selected} />
                    </div>
                ) : null}
                <SettingsDataTable
                    columns={columns}
                    rows={equipment.data}
                    rowKey={(row) => row.id}
                    meta={equipment.meta}
                    pageUrl="/equipment"
                    queryParams={queryParams}
                    emptyTitle="No equipment"
                    emptyDescription="No equipment matches these filters."
                />
            </SettingsPageShell>

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

EquipmentIndex.layout = {
    breadcrumbs: [{ title: 'Equipment', href: '/equipment' }],
};
