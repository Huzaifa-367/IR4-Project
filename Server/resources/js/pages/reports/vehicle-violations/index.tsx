import { Head, Link, useForm } from '@inertiajs/react';
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
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
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

    const queryParams = { search: search || undefined };

    function applyFilters(
        patch: Partial<{ search: string }> = {},
    ): void {
        const nextSearch = patch.search ?? search;

        visitFilters('/reports/vehicle-violations', {
            search: nextSearch || undefined,
        });
    }

    const [debouncedApplySearch] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

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
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);
                                debouncedApplySearch(value);
                            }}
                            placeholder="Vehicle, type, description…"
                            className="w-full sm:w-64"
                            aria-label="Search vehicle violations"
                        />
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={violations.data}
                    rowKey={(row) => row.id}
                    meta={violations.meta}
                    pageUrl="/reports/vehicle-violations"
                    queryParams={queryParams}
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
                            {form.errors.observed_at ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.observed_at}
                                </p>
                            ) : null}
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
                                maxLength={255}
                            />
                            {form.errors.vehicle_description ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.vehicle_description}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Type</Label>
                            <SearchableSelect
                                value={form.data.violation_type}
                                onValueChange={(value) =>
                                    form.setData('violation_type', value)
                                }
                                options={violationTypes.map((type) => ({
                                    value: type,
                                    label: type,
                                }))}
                            />
                            {form.errors.violation_type ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.violation_type}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Camera (optional)</Label>
                            <SearchableSelect
                                value={form.data.camera_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'camera_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                options={[
                                    { value: 'none', label: 'None' },
                                    ...cameras.map((camera) => ({
                                        value: String(camera.id),
                                        label:
                                            camera.name || camera.reference,
                                    })),
                                ]}
                            />
                            {form.errors.camera_id ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.camera_id}
                                </p>
                            ) : null}
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
                                maxLength={2000}
                            />
                            {form.errors.description ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.description}
                                </p>
                            ) : null}
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
                                maxLength={5000}
                            />
                            {form.errors.action_taken ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.action_taken}
                                </p>
                            ) : null}
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
                        ? `/reports/vehicle-violations/${deleteTarget.uuid}`
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
