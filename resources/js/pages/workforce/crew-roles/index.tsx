import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PermitTypeOption = {
    id: number;
    code: string;
    name: string;
};

type CrewRoleRow = {
    id: number;
    permit_type_id: number;
    role_code: string;
    label: string;
    min_count: number;
    is_mandatory: boolean;
    sort_order: number;
    permit_type: {
        id: number;
        code: string;
        name: string;
        is_active: boolean;
    } | null;
};

type Props = {
    roles: CrewRoleRow[];
    permitTypes: PermitTypeOption[];
};

type DialogState =
    | { kind: 'create' }
    | { kind: 'edit'; role: CrewRoleRow }
    | null;

export default function CrewRolesIndex({ roles, permitTypes }: Props) {
    const [dialog, setDialog] = useState<DialogState>(null);

    const columns: SettingsColumn<CrewRoleRow>[] = [
        {
            key: 'type',
            header: 'Permit type',
            cell: (row) =>
                row.permit_type ? (
                    <Link
                        href={`/workforce/permit-types/${row.permit_type.id}`}
                        className="text-[color:var(--accent)] hover:underline"
                    >
                        {row.permit_type.name}
                    </Link>
                ) : (
                    '—'
                ),
        },
        {
            key: 'code',
            header: 'Code',
            cell: (row) => (
                <span className="font-mono text-xs">{row.role_code}</span>
            ),
        },
        { key: 'label', header: 'Label', cell: (row) => row.label },
        {
            key: 'min',
            header: 'Min',
            cell: (row) => row.min_count,
        },
        {
            key: 'mandatory',
            header: 'Mandatory',
            cell: (row) => (
                <StatusPill
                    label={row.is_mandatory ? 'Required' : 'Optional'}
                    tone={row.is_mandatory ? 'warn' : 'neutral'}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-40 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setDialog({ kind: 'edit', role: row })}
                    >
                        Edit
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            if (
                                confirm(
                                    `Remove crew role “${row.label}” from this permit type?`,
                                )
                            ) {
                                router.delete(
                                    `/workforce/crew-roles/${row.id}`,
                                );
                            }
                        }}
                    >
                        Remove
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Crew roles" />
            <SettingsPageShell
                eyebrow="Catalogue"
                title="Crew roles"
                description="Permit personnel roles (entrant, fire watch, standby, …) assigned to workers on each permit — not login permissions."
                actions={
                    <Button
                        type="button"
                        onClick={() => setDialog({ kind: 'create' })}
                    >
                        Add crew role
                    </Button>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={roles}
                    rowKey={(row) => row.id}
                    emptyTitle="No crew roles"
                    emptyDescription="Seed the permit catalogue or add a role for a permit type."
                />
            </SettingsPageShell>

            <CrudFormDialog
                open={dialog?.kind === 'create'}
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                    }
                }}
                title="Add crew role"
                action="/workforce/crew-roles"
                method="post"
                submitLabel="Create role"
            >
                {() => (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1 sm:col-span-2">
                            <Label htmlFor="permit_type_id">Permit type</Label>
                            <select
                                id="permit_type_id"
                                name="permit_type_id"
                                required
                                defaultValue=""
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none"
                            >
                                <option value="" disabled>
                                    Select type
                                </option>
                                {permitTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="role_code">Code</Label>
                            <Input
                                id="role_code"
                                name="role_code"
                                required
                                placeholder="fire_watch"
                                pattern="[a-z][a-z0-9_]*"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="label">Label</Label>
                            <Input
                                id="label"
                                name="label"
                                required
                                placeholder="Fire watch"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="min_count">Minimum count</Label>
                            <Input
                                id="min_count"
                                name="min_count"
                                type="number"
                                min={0}
                                max={50}
                                defaultValue={1}
                                required
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="sort_order">Sort order</Label>
                            <Input
                                id="sort_order"
                                name="sort_order"
                                type="number"
                                min={0}
                                defaultValue={0}
                            />
                        </div>
                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                name="is_mandatory"
                                value="1"
                                defaultChecked
                                className="size-4 rounded border border-input"
                            />
                            Mandatory on this permit type
                        </label>
                    </div>
                )}
            </CrudFormDialog>

            <CrudFormDialog
                open={dialog?.kind === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                    }
                }}
                title="Edit crew role"
                action={
                    dialog?.kind === 'edit'
                        ? `/workforce/crew-roles/${dialog.role.id}`
                        : '/workforce/crew-roles'
                }
                method="put"
                submitLabel="Save role"
            >
                {() =>
                    dialog?.kind === 'edit' ? (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <p className="sm:col-span-2 text-sm text-text-dim">
                                Permit type:{' '}
                                {dialog.role.permit_type?.name ?? '—'}
                            </p>
                            <div className="grid gap-1">
                                <Label htmlFor="edit_role_code">Code</Label>
                                <Input
                                    id="edit_role_code"
                                    name="role_code"
                                    required
                                    pattern="[a-z][a-z0-9_]*"
                                    defaultValue={dialog.role.role_code}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="edit_label">Label</Label>
                                <Input
                                    id="edit_label"
                                    name="label"
                                    required
                                    defaultValue={dialog.role.label}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="edit_min_count">
                                    Minimum count
                                </Label>
                                <Input
                                    id="edit_min_count"
                                    name="min_count"
                                    type="number"
                                    min={0}
                                    max={50}
                                    defaultValue={dialog.role.min_count}
                                    required
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="edit_sort_order">
                                    Sort order
                                </Label>
                                <Input
                                    id="edit_sort_order"
                                    name="sort_order"
                                    type="number"
                                    min={0}
                                    defaultValue={dialog.role.sort_order}
                                />
                            </div>
                            <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                <input
                                    type="checkbox"
                                    name="is_mandatory"
                                    value="1"
                                    defaultChecked={dialog.role.is_mandatory}
                                    className="size-4 rounded border border-input"
                                />
                                Mandatory on this permit type
                            </label>
                        </div>
                    ) : null
                }
            </CrudFormDialog>
        </>
    );
}

CrewRolesIndex.layout = {
    breadcrumbs: [
        { title: 'Catalogue', href: '/workforce/permit-types' },
        { title: 'Crew roles', href: '/workforce/crew-roles' },
    ],
};
