import { Head, Link } from '@inertiajs/react';
import { MoreHorizontal, Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import settings from '@/routes/settings';
import type { HardwareOption, Paginated } from '@/types/hardware';

type ZoneRow = {
    id: number;
    uuid: string;
    name: string;
    zone_type: string;
    zone_type_label: string;
    requires_authorization: boolean;
    requires_permit: boolean;
    occupancy_limit: number | null;
    is_active: boolean;
    color: string | null;
    current_readers: number;
    access_list_count: number;
};

type Props = {
    zones: Paginated<ZoneRow>;
    zoneTypes: HardwareOption[];
};

type FormState = { mode: 'create' } | { mode: 'edit'; zone: ZoneRow };

export default function ZonesIndex({ zones, zoneTypes }: Props) {
    const [form, setForm] = useState<FormState | null>(null);
    const [deactivateTarget, setDeactivateTarget] = useState<ZoneRow | null>(
        null,
    );
    const [deleteTarget, setDeleteTarget] = useState<ZoneRow | null>(null);
    const [editType, setEditType] = useState('work');
    const [requiresAuth, setRequiresAuth] = useState(false);
    const [requiresPermit, setRequiresPermit] = useState(false);

    const columns: SettingsColumn<ZoneRow>[] = [
        {
            key: 'number',
            header: 'Number',
            className: 'w-24',
            cell: (zone) => (
                <Link
                    href={settings.zones.show(zone.uuid)}
                    className="font-mono text-xs hover:underline"
                >
                    Zone #{zone.id}
                </Link>
            ),
        },
        {
            key: 'name',
            header: 'Name',
            cell: (zone) => (
                <Link
                    href={settings.zones.show(zone.uuid)}
                    className="font-medium text-text hover:underline"
                >
                    {zone.name}
                </Link>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (zone) => zone.zone_type_label,
        },
        {
            key: 'auth',
            header: 'Auth',
            cell: (zone) =>
                zone.requires_authorization ? (
                    <StatusPill label="Required" tone="warn" />
                ) : (
                    <span className="text-text-faint">—</span>
                ),
        },
        {
            key: 'permit',
            header: 'PTW',
            cell: (zone) =>
                zone.requires_permit ? (
                    <StatusPill label="Required" tone="accent" />
                ) : (
                    <span className="text-text-faint">—</span>
                ),
        },
        {
            key: 'occupancy',
            header: 'Occupancy limit',
            className: 'text-right font-mono tabular-nums',
            cell: (zone) => zone.occupancy_limit ?? '—',
        },
        {
            key: 'readers',
            header: 'Readers',
            className: 'text-right font-mono tabular-nums',
            cell: (zone) => zone.current_readers,
        },
        {
            key: 'access',
            header: 'Access list',
            className: 'text-right font-mono tabular-nums',
            cell: (zone) => zone.access_list_count,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (zone) => (
                <StatusPill
                    label={zone.is_active ? 'Active' : 'Inactive'}
                    tone={zone.is_active ? 'ok' : 'neutral'}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12 text-right',
            cell: (zone) => (
                <div className="flex justify-end gap-1">
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            setEditType(zone.zone_type);
                            setRequiresAuth(zone.requires_authorization);
                            setRequiresPermit(zone.requires_permit);
                            setForm({ mode: 'edit', zone });
                        }}
                    >
                        Edit
                    </Button>
                    <DropdownActions
                        zone={zone}
                        onDeactivate={() => setDeactivateTarget(zone)}
                        onDelete={() => setDeleteTarget(zone)}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Zones" />
            <SettingsPageShell
                title="Zones"
                description="Logical areas. RFID readers bind to a zone; readings resolve through that binding."
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={settings.repositioning()}>
                                Repositioning
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                setEditType('work');
                                setRequiresAuth(false);
                                setForm({ mode: 'create' });
                            }}
                        >
                            <Plus data-icon="inline-start" />
                            Add zone
                        </Button>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={zones.data}
                    rowKey={(zone) => zone.id}
                    meta={zones.meta}
                    pageUrl={settings.zones.index.url()}
                    emptyTitle="No zones"
                    emptyDescription="Create the first logical area to begin binding readers."
                />
            </SettingsPageShell>

            <CrudFormDialog
                open={form !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setForm(null);
                    }
                }}
                title={form?.mode === 'edit' ? 'Edit zone' : 'Add zone'}
                action={
                    form?.mode === 'edit'
                        ? settings.zones.update.url(form.zone.uuid)
                        : settings.zones.store.url()
                }
                method={form?.mode === 'edit' ? 'put' : 'post'}
                submitLabel={
                    form?.mode === 'edit' ? 'Save zone' : 'Create zone'
                }
                transform={(data) => ({
                    ...data,
                    zone_type: editType,
                    requires_authorization: requiresAuth ? '1' : '0',
                    requires_permit: requiresPermit ? '1' : '0',
                })}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="zone-name">Name</Label>
                            <Input
                                id="zone-name"
                                name="name"
                                required
                                maxLength={150}
                                defaultValue={
                                    form?.mode === 'edit' ? form.zone.name : ''
                                }
                            />
                            {errors.name ? (
                                <p className="text-sm text-destructive">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Type</Label>
                            <SearchableSelect
                                value={editType}
                                onValueChange={setEditType}
                                options={zoneTypes.map((type) => ({
                                    value: type.value,
                                    label: type.label,
                                }))}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="zone-occupancy">
                                Occupancy limit
                            </Label>
                            <Input
                                id="zone-occupancy"
                                name="occupancy_limit"
                                type="number"
                                min={1}
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? (form.zone.occupancy_limit ?? '')
                                        : ''
                                }
                            />
                            {errors.occupancy_limit ? (
                                <p className="text-sm text-destructive">
                                    {errors.occupancy_limit}
                                </p>
                            ) : null}
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={requiresAuth}
                                onCheckedChange={(checked) =>
                                    setRequiresAuth(checked === true)
                                }
                            />
                            Requires authorization (access list)
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={requiresPermit}
                                onCheckedChange={(checked) =>
                                    setRequiresPermit(checked === true)
                                }
                            />
                            Requires permit to work (PTW zone)
                        </label>
                    </>
                )}
            </CrudFormDialog>

            <ConfirmActionDialog
                open={deactivateTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeactivateTarget(null);
                    }
                }}
                title="Deactivate zone"
                description={
                    deactivateTarget ? (
                        <>
                            Deactivate <strong>{deactivateTarget.name}</strong>?
                            It will drop off active lists but history stays
                            intact.
                        </>
                    ) : (
                        ''
                    )
                }
                action={
                    deactivateTarget
                        ? settings.zones.deactivate.url(deactivateTarget.uuid)
                        : undefined
                }
                method="post"
                confirmLabel="Deactivate"
            />

            <ConfirmActionDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                title="Delete zone"
                description={
                    deleteTarget ? (
                        <>
                            Delete <strong>{deleteTarget.name}</strong>?{' '}
                            {deleteTarget.current_readers > 0
                                ? 'This zone has bound readers — unbind them first.'
                                : 'This permanently removes the zone.'}
                        </>
                    ) : (
                        ''
                    )
                }
                action={
                    deleteTarget
                        ? settings.zones.destroy.url(deleteTarget.uuid)
                        : undefined
                }
                method="delete"
                confirmLabel="Delete"
                destructive
                disabled={
                    deleteTarget !== null && deleteTarget.current_readers > 0
                }
            />
        </>
    );
}

function DropdownActions({
    zone,
    onDeactivate,
    onDelete,
}: {
    zone: ZoneRow;
    onDeactivate: () => void;
    onDelete: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <Button
                size="icon"
                variant="ghost"
                aria-label="More actions"
                onClick={() => setOpen((value) => !value)}
            >
                <MoreHorizontal />
            </Button>
            {open ? (
                <div
                    className="absolute top-full right-0 z-10 mt-1 w-40 rounded-[var(--radius-sm)] border border-border bg-surface-2 py-1 shadow-[var(--shadow-pop)]"
                    onMouseLeave={() => setOpen(false)}
                >
                    {zone.is_active ? (
                        <button
                            type="button"
                            className="block w-full px-3 py-1.5 text-left text-sm text-text hover:bg-surface-3"
                            onClick={() => {
                                setOpen(false);
                                onDeactivate();
                            }}
                        >
                            Deactivate
                        </button>
                    ) : null}
                    <button
                        type="button"
                        className="block w-full px-3 py-1.5 text-left text-sm text-destructive hover:bg-surface-3"
                        onClick={() => {
                            setOpen(false);
                            onDelete();
                        }}
                    >
                        Delete
                    </button>
                </div>
            ) : null}
        </div>
    );
}

ZonesIndex.layout = {
    breadcrumbs: [{ title: 'Zones', href: settings.zones.index() }],
};
