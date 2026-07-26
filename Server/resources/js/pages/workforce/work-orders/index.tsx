import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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

type WorkOrderRow = {
    id: number;
    uuid: string;
    reference: string;
    description: string | null;
    status: string;
    zone: { id: number; name: string } | null;
    permits_count: number;
    created_at: string | null;
};

type Props = {
    workOrders: {
        data: WorkOrderRow[];
        meta: PaginatedMeta;
    };
    filters: {
        search: string;
        sort: string;
        direction: string;
    };
    zones: Array<{ id: number; name: string }>;
    canCreate: boolean;
};

export default function WorkOrdersIndex({
    workOrders,
    filters,
    zones,
    canCreate,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [createOpen, setCreateOpen] = useState(false);
    const [zoneId, setZoneId] = useState('');

    function applySearch(value: string): void {
        visitFilters('/workforce/work-orders', {
            search: value || undefined,
        });
    }

    const [debouncedApplySearch] = useDebouncedCallback(
        applySearch,
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const columns: SettingsColumn<WorkOrderRow>[] = [
        {
            key: 'reference',
            header: 'Reference',
            cell: (row) => (
                <Link
                    href={`/workforce/work-orders/${row.uuid}`}
                    className="font-mono text-xs font-medium hover:underline"
                >
                    {row.reference}
                </Link>
            ),
        },
        {
            key: 'zone',
            header: 'Zone',
            cell: (row) => row.zone?.name ?? '—',
        },
        {
            key: 'permits',
            header: 'Permits',
            className: 'text-right font-mono tabular-nums',
            cell: (row) => row.permits_count,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => row.status,
        },
        {
            key: 'actions',
            header: '',
            className: 'w-20 text-right',
            cell: (row) => (
                <Button asChild size="sm" variant="ghost">
                    <Link href={`/workforce/work-orders/${row.uuid}`}>Open</Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Work orders" />
            <SettingsPageShell
                eyebrow="Workforce"
                title="Work orders"
                description="Group related permits under a shared work order reference."
                actions={
                    canCreate ? (
                        <Button
                            type="button"
                            onClick={() => {
                                setZoneId('');
                                setCreateOpen(true);
                            }}
                        >
                            New work order
                        </Button>
                    ) : undefined
                }
                filters={
                    <Input
                        value={search}
                        onChange={(event) => {
                            const value = event.target.value;
                            setSearch(value);
                            debouncedApplySearch(value);
                        }}
                        placeholder="Search reference…"
                        className="w-full sm:w-56"
                        aria-label="Search work orders"
                    />
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={workOrders.data}
                    rowKey={(row) => row.id}
                    meta={workOrders.meta}
                    pageUrl="/workforce/work-orders"
                    emptyTitle="No work orders"
                    emptyDescription="Create a work order to link permits for a job package."
                />
            </SettingsPageShell>

            <Dialog
                open={createOpen}
                onOpenChange={(open) => {
                    setCreateOpen(open);

                    if (!open) {
                        setZoneId('');
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>New work order</DialogTitle>
                        <DialogDescription>
                            Job package reference. Add permits under it next
                            (hot work, CSE, …).
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        action="/workforce/work-orders"
                        method="post"
                        className="space-y-4"
                        options={{ preserveScroll: true }}
                        transform={(data) => ({
                            ...data,
                            zone_id: zoneId || null,
                        })}
                        onSuccess={() => {
                            setCreateOpen(false);
                            setZoneId('');
                        }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="reference">Reference</Label>
                                    <Input
                                        id="reference"
                                        name="reference"
                                        required
                                        maxLength={64}
                                        placeholder="WO-2026-0042"
                                    />
                                    {errors.reference ? (
                                        <p className="text-sm text-destructive">
                                            {errors.reference}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        rows={3}
                                        maxLength={5000}
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                        placeholder="Brief scope of work…"
                                    />
                                    {errors.description ? (
                                        <p className="text-sm text-destructive">
                                            {errors.description}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="zone_id">
                                        Zone (optional)
                                    </Label>
                                    <SearchableSelect
                                        id="zone_id"
                                        value={zoneId}
                                        onValueChange={setZoneId}
                                        options={zones.map((zone) => ({
                                            value: String(zone.id),
                                            label: zone.name,
                                        }))}
                                        placeholder="No zone"
                                        allowClear
                                        clearLabel="No zone"
                                    />
                                    {errors.zone_id ? (
                                        <p className="text-sm text-destructive">
                                            {errors.zone_id}
                                        </p>
                                    ) : null}
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setCreateOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Create work order
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
