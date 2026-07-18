import { Head } from '@inertiajs/react';
import { MoreHorizontal, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { RequirePermission } from '@/components/ir4/require-permission';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import {
    SettingsDataTable,
    type SettingsColumn,
} from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { RoleRow } from '@/types/settings-admin';

type Props = {
    roles: RoleRow[];
    catalogue: Record<string, string[]>;
};

type RoleFormState = {
    mode: 'create' | 'edit';
    role?: RoleRow;
};

export default function RolesIndex({ roles, catalogue }: Props) {
    const [form, setForm] = useState<RoleFormState | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<RoleRow | null>(null);
    const [readOnly, setReadOnly] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);

    const openCreate = (): void => {
        setReadOnly(false);
        setSelected([]);
        setForm({ mode: 'create' });
    };

    const openEdit = (role: RoleRow): void => {
        setReadOnly(role.is_read_only);
        setSelected(role.permissions);
        setForm({ mode: 'edit', role });
    };

    const viewOnlyPermissions = useMemo(
        () =>
            Object.values(catalogue)
                .flat()
                .filter((permission) => permission.startsWith('view-')),
        [catalogue],
    );

    const togglePermission = (permission: string, checked: boolean): void => {
        setSelected((current) => {
            if (checked) {
                return current.includes(permission)
                    ? current
                    : [...current, permission];
            }
            return current.filter((item) => item !== permission);
        });
    };

    const columns: SettingsColumn<RoleRow>[] = [
        {
            key: 'role',
            header: 'Role',
            cell: (role) => (
                <div>
                    <div className="font-medium text-text">{role.name}</div>
                    {role.description ? (
                        <div className="text-xs text-text-dim">
                            {role.description}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'flags',
            header: 'Flags',
            cell: (role) => (
                <div className="flex flex-wrap gap-1">
                    {role.is_system ? (
                        <Badge variant="outline">System</Badge>
                    ) : null}
                    {role.is_read_only ? (
                        <Badge variant="secondary">Read-only</Badge>
                    ) : null}
                    {!role.is_system && !role.is_read_only ? '—' : null}
                </div>
            ),
        },
        {
            key: 'users',
            header: 'Users',
            className: 'text-right font-mono tabular-nums',
            cell: (role) => role.users_count,
        },
        {
            key: 'permissions',
            header: 'Permissions',
            cell: (role) =>
                role.is_system ? (
                    <span className="text-text-dim">All</span>
                ) : (
                    <span className="font-mono text-xs text-text-dim">
                        {role.permissions.length} assigned
                    </span>
                ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12 text-right',
            cell: (role) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="icon" variant="ghost" aria-label="Actions">
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => openEdit(role)}>
                            {role.is_system ? 'View' : 'Edit'}
                        </DropdownMenuItem>
                        {!role.is_system ? (
                            <DropdownMenuItem
                                className="text-destructive"
                                onClick={() => setDeleteTarget(role)}
                            >
                                Delete
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <RequirePermission permission="manage-roles">
            <Head title="Roles" />
            <SettingsPageShell
                title="Roles"
                description="Compose roles from the permission catalogue. Super Admin is locked."
                actions={
                    <Button type="button" onClick={openCreate}>
                        <Plus data-icon="inline-start" />
                        Create role
                    </Button>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={roles}
                    rowKey={(role) => role.id}
                    emptyTitle="No roles"
                    emptyDescription="Create a custom role to get started."
                />
            </SettingsPageShell>

            <CrudFormDialog
                open={form !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setForm(null);
                    }
                }}
                title={
                    form?.mode === 'edit'
                        ? form.role?.is_system
                            ? 'System role'
                            : 'Edit role'
                        : 'Create role'
                }
                description="Permissions sync immediately for every user holding this role."
                action={
                    form?.mode === 'edit' && form.role
                        ? `/settings/roles/${form.role.id}`
                        : '/settings/roles'
                }
                method={form?.mode === 'edit' ? 'put' : 'post'}
                submitLabel={form?.mode === 'edit' ? 'Save role' : 'Create role'}
                disableSubmit={form?.role?.is_system === true}
                transform={(data) => ({
                    ...data,
                    is_read_only: readOnly ? '1' : '0',
                    permissions: selected,
                })}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="role-name">Name</Label>
                            <Input
                                id="role-name"
                                name="name"
                                required
                                maxLength={150}
                                defaultValue={form?.role?.name ?? ''}
                                disabled={form?.role?.is_system}
                            />
                            {errors.name ? (
                                <p className="text-destructive text-sm">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="role-description">Description</Label>
                            <Textarea
                                id="role-description"
                                name="description"
                                maxLength={500}
                                defaultValue={form?.role?.description ?? ''}
                                disabled={form?.role?.is_system}
                            />
                        </div>
                        <div className="flex items-center justify-between gap-3 rounded-[var(--radius-sm)] border border-border px-3 py-2">
                            <div>
                                <p className="text-sm font-medium">Read-only</p>
                                <p className="text-xs text-text-dim">
                                    Limits selection to view-* permissions
                                </p>
                            </div>
                            <Switch
                                checked={readOnly}
                                disabled={form?.role?.is_system}
                                onCheckedChange={(checked) => {
                                    setReadOnly(checked);
                                    if (checked) {
                                        setSelected((current) =>
                                            current.filter((item) =>
                                                item.startsWith('view-'),
                                            ),
                                        );
                                    }
                                }}
                            />
                        </div>
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-medium">
                                    Permissions ({selected.length})
                                </p>
                                {!form?.role?.is_system ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setSelected(
                                                readOnly
                                                    ? viewOnlyPermissions
                                                    : Object.values(
                                                          catalogue,
                                                      ).flat(),
                                            )
                                        }
                                    >
                                        Select all
                                    </Button>
                                ) : null}
                            </div>
                            <div className="max-h-72 overflow-y-auto rounded-[var(--radius-sm)] border border-border p-3">
                                {form?.role?.is_system ? (
                                    <p className="text-sm text-text-dim">
                                        Super Admin holds the full permission
                                        catalogue and cannot be edited.
                                    </p>
                                ) : (
                                    Object.entries(catalogue).map(
                                        ([group, permissions]) => (
                                            <div
                                                key={group}
                                                className="mb-4 last:mb-0"
                                            >
                                                <p className="mb-2 text-[11px] font-semibold tracking-[0.08em] text-text-faint uppercase">
                                                    {group}
                                                </p>
                                                <div className="grid gap-2">
                                                    {permissions.map(
                                                        (permission) => {
                                                            const blocked =
                                                                readOnly &&
                                                                !permission.startsWith(
                                                                    'view-',
                                                                );
                                                            return (
                                                                <label
                                                                    key={
                                                                        permission
                                                                    }
                                                                    className="flex items-center gap-2 text-sm"
                                                                >
                                                                    <Checkbox
                                                                        checked={selected.includes(
                                                                            permission,
                                                                        )}
                                                                        disabled={
                                                                            blocked
                                                                        }
                                                                        onCheckedChange={(
                                                                            checked,
                                                                        ) =>
                                                                            togglePermission(
                                                                                permission,
                                                                                checked ===
                                                                                    true,
                                                                            )
                                                                        }
                                                                    />
                                                                    <span className="font-mono text-xs">
                                                                        {
                                                                            permission
                                                                        }
                                                                    </span>
                                                                </label>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            </div>
                                        ),
                                    )
                                )}
                            </div>
                            {errors.permissions ? (
                                <p className="text-destructive text-sm">
                                    {errors.permissions}
                                </p>
                            ) : null}
                        </div>
                        {form?.role?.is_system ? (
                            <StatusPill label="Locked system role" tone="warn" />
                        ) : null}
                    </>
                )}
            </CrudFormDialog>

            <ConfirmActionDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                title="Delete role"
                description={
                    deleteTarget ? (
                        <>
                            Delete <strong>{deleteTarget.name}</strong>?{' '}
                            {deleteTarget.users_count > 0
                                ? `Reassign ${deleteTarget.users_count} user(s) first.`
                                : 'This cannot be undone.'}
                        </>
                    ) : (
                        ''
                    )
                }
                action={
                    deleteTarget
                        ? `/settings/roles/${deleteTarget.id}`
                        : undefined
                }
                method="delete"
                confirmLabel="Delete"
                destructive
                disabled={
                    deleteTarget === null ||
                    deleteTarget.is_system ||
                    deleteTarget.users_count > 0
                }
            />
        </RequirePermission>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: '/settings/roles' }],
};
