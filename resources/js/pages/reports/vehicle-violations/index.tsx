import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PaginatedMeta } from '@/types/hardware';
import type { VehicleViolation } from '@/types/report';

type Props = {
    violations: { data: VehicleViolation[]; meta: PaginatedMeta };
    filters: { search: string };
    violationTypes: string[];
    cameras: Array<{ id: number; name: string; reference: string }>;
    canCreate: boolean;
};

export default function VehicleViolationsIndex({
    violations,
    filters,
    violationTypes,
    cameras,
    canCreate,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [logOpen, setLogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<VehicleViolation | null>(
        null,
    );
    const form = useForm({
        observed_at: '',
        vehicle_description: '',
        violation_type: violationTypes[0] ?? 'speeding',
        description: '',
        action_taken: '',
        camera_id: '',
    });

    function applyFilters(): void {
        router.get(
            '/reports/vehicle-violations',
            { search: search || undefined },
            { preserveState: true, replace: true },
        );
    }

    const columns: SettingsColumn<VehicleViolation>[] = [
        {
            key: 'observed',
            header: 'Observed',
            cell: (row) =>
                row.observed_at
                    ? new Date(row.observed_at).toLocaleString()
                    : '—',
        },
        {
            key: 'vehicle',
            header: 'Vehicle',
            cell: (row) => row.vehicle_description,
        },
        { key: 'type', header: 'Type', cell: (row) => row.violation_type },
        {
            key: 'action',
            header: 'Action taken',
            cell: (row) => row.action_taken,
        },
        {
            key: 'actions',
            header: '',
            className: 'w-20 text-right',
            cell: (row) => (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => setDeleteTarget(row)}
                >
                    Remove
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Vehicle violations" />
            <SettingsPageShell
                title="Vehicle Violations"
                description="Manual item vii for the weekly report"
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <Link href="/reports">Back to reports</Link>
                        </Button>
                        {canCreate && (
                            <Button
                                type="button"
                                onClick={() => setLogOpen(true)}
                            >
                                <Plus data-icon="inline-start" />
                                Log violation
                            </Button>
                        )}
                    </>
                }
                filters={
                    <>
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Vehicle, type, description…"
                            className="w-full sm:w-64"
                            aria-label="Search vehicle violations"
                        />
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
                <SettingsDataTable
                    columns={columns}
                    rows={violations.data}
                    rowKey={(row) => row.id}
                    meta={violations.meta}
                    pageUrl="/reports/vehicle-violations"
                    queryParams={{ search: search || undefined }}
                    emptyTitle="No vehicle violations"
                    emptyDescription="No vehicle violations match these filters."
                />
            </SettingsPageShell>

            <Dialog open={logOpen} onOpenChange={setLogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Log vehicle violation</DialogTitle>
                    </DialogHeader>
                    <form
                        className="grid gap-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/reports/vehicle-violations', {
                                preserveScroll: true,
                                onSuccess: () => {
                                    form.reset();
                                    setLogOpen(false);
                                },
                            });
                        }}
                    >
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="observed_at">Observed at</Label>
                            <Input
                                id="observed_at"
                                type="datetime-local"
                                value={form.data.observed_at}
                                onChange={(event) =>
                                    form.setData(
                                        'observed_at',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="vehicle_description">Vehicle</Label>
                            <Input
                                id="vehicle_description"
                                value={form.data.vehicle_description}
                                onChange={(event) =>
                                    form.setData(
                                        'vehicle_description',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Type</Label>
                            <Select
                                value={form.data.violation_type}
                                onValueChange={(value) =>
                                    form.setData('violation_type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {violationTypes.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {type}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Camera (optional)</Label>
                            <Select
                                value={form.data.camera_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'camera_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="none">
                                            None
                                        </SelectItem>
                                        {cameras.map((camera) => (
                                            <SelectItem
                                                key={camera.id}
                                                value={String(camera.id)}
                                            >
                                                {camera.name ||
                                                    camera.reference}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-2 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-2 sm:col-span-2">
                            <Label htmlFor="action_taken">
                                Action taken (required)
                            </Label>
                            <Input
                                id="action_taken"
                                value={form.data.action_taken}
                                onChange={(event) =>
                                    form.setData(
                                        'action_taken',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            {form.errors.action_taken && (
                                <p className="text-sm text-destructive">
                                    {form.errors.action_taken}
                                </p>
                            )}
                        </div>
                        <DialogFooter className="sm:col-span-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setLogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Log violation
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmActionDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                title="Remove vehicle violation"
                description={
                    deleteTarget ? (
                        <>
                            Remove the logged violation for{' '}
                            <strong>{deleteTarget.vehicle_description}</strong>?
                        </>
                    ) : (
                        ''
                    )
                }
                action={
                    deleteTarget
                        ? `/reports/vehicle-violations/${deleteTarget.id}`
                        : undefined
                }
                method="delete"
                confirmLabel="Remove"
                destructive
            />
        </>
    );
}

VehicleViolationsIndex.layout = {
    breadcrumbs: [
        { title: 'Reports', href: '/reports' },
        { title: 'Vehicle violations', href: '/reports/vehicle-violations' },
    ],
};
