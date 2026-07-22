import { Head, Link } from '@inertiajs/react';
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
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
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

    function applyFilters(
        patch: Partial<{
            search: string;
            equipment_type: string;
            status: string;
            checkout_state: string;
            overdue: string;
        }> = {},
    ): void {
        const nextSearch = patch.search ?? search;
        const nextEquipmentType = patch.equipment_type ?? equipmentType;
        const nextStatus = patch.status ?? status;
        const nextCheckoutState = patch.checkout_state ?? checkoutState;
        const nextOverdue = patch.overdue ?? overdue;

        visitFilters('/equipment', {
            search: nextSearch || undefined,
            equipment_type:
                nextEquipmentType === ALL ? undefined : nextEquipmentType,
            status: nextStatus === ALL ? undefined : nextStatus,
            checkout_state:
                nextCheckoutState === ALL ? undefined : nextCheckoutState,
            overdue: nextOverdue === ALL ? undefined : nextOverdue,
        });
    }

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

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
            className: 'w-36 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <QrLabelButton
                        equipmentId={row.id}
                        label="QR"
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
                        <RequirePermission permission="create-equipment">
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
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);
                                debouncedApplySearch(value);
                            }}
                            placeholder="Code, name, type…"
                            className="w-full sm:w-56"
                            aria-label="Search equipment"
                        />
                        <SearchableSelect
                            value={equipmentType}
                            onValueChange={(value) => {
                                setEquipmentType(value);
                                cancelDebounce();
                                applyFilters({ equipment_type: value });
                            }}
                            placeholder="Type"
                            triggerClassName="w-36"
                            options={[
                                { value: ALL, label: 'All types' },
                                ...typeOptions.map((type) => ({
                                    value: type,
                                    label: type,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={status}
                            onValueChange={(value) => {
                                setStatus(value);
                                cancelDebounce();
                                applyFilters({ status: value });
                            }}
                            placeholder="Status"
                            triggerClassName="w-36"
                            options={[
                                { value: ALL, label: 'All statuses' },
                                ...statuses.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={checkoutState}
                            onValueChange={(value) => {
                                setCheckoutState(value);
                                cancelDebounce();
                                applyFilters({ checkout_state: value });
                            }}
                            placeholder="Custody"
                            triggerClassName="w-36"
                            options={[
                                { value: ALL, label: 'All' },
                                { value: 'available', label: 'Available' },
                                { value: 'checked_out', label: 'Checked out' },
                                {
                                    value: 'overdue_return',
                                    label: 'Overdue return',
                                },
                            ]}
                        />
                        <SearchableSelect
                            value={overdue}
                            onValueChange={(value) => {
                                setOverdue(value);
                                cancelDebounce();
                                applyFilters({ overdue: value });
                            }}
                            placeholder="Due"
                            triggerClassName="w-32"
                            options={[
                                { value: ALL, label: 'Any' },
                                { value: '1', label: 'Overdue' },
                                { value: '0', label: 'Not overdue' },
                            ]}
                        />
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
