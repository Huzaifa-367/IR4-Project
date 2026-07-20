import { Head, usePage } from '@inertiajs/react';
import { MoreHorizontal, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { RequirePermission } from '@/components/ir4/require-permission';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import {
    SettingsDataTable,
} from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import type { RoleOption, UserRow } from '@/types/settings-admin';

type TemporaryPassword = {
    user_id: number;
    user_name: string;
    email: string;
    password: string;
};

type Props = {
    users: UserRow[];
    roles: RoleOption[];
    temporaryPassword: TemporaryPassword | null;
};

type UserFormState =
    | { mode: 'create' }
    | { mode: 'edit'; user: UserRow };

export default function UsersIndex({
    users,
    roles,
    temporaryPassword: initialTemp,
}: Props) {
    const page = usePage();
    const authUserId = (page.props.auth as { user?: { id: number } } | undefined)
        ?.user?.id;
    const [form, setForm] = useState<UserFormState | null>(null);
    const [lifecycleTarget, setLifecycleTarget] = useState<UserRow | null>(
        null,
    );
    const [tempPassword, setTempPassword] = usePropSyncedState(initialTemp);
    const [createRole, setCreateRole] = useState('');
    const [editRole, setEditRole] = useState('');

    const columns: SettingsColumn<UserRow>[] = [
        {
            key: 'name',
            header: 'Name',
            cell: (user) => (
                <div>
                    <div className="font-medium">{user.name}</div>
                    <div className="text-xs text-text-dim">{user.email}</div>
                </div>
            ),
        },
        {
            key: 'role',
            header: 'Role',
            cell: (user) => user.role ?? '—',
        },
        {
            key: 'active',
            header: 'Status',
            cell: (user) => (
                <StatusPill
                    label={user.is_active ? 'Active' : 'Inactive'}
                    tone={user.is_active ? 'ok' : 'warn'}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12 text-right',
            cell: (user) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="icon" variant="ghost" aria-label="Actions">
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onClick={() => {
                                setEditRole(user.role ?? '');
                                setForm({ mode: 'edit', user });
                            }}
                        >
                            Edit
                        </DropdownMenuItem>
                        {user.is_active ? (
                            <DropdownMenuItem
                                className="text-destructive"
                                disabled={authUserId === user.id}
                                onClick={() => setLifecycleTarget(user)}
                            >
                                Deactivate
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                onClick={() => setLifecycleTarget(user)}
                            >
                                Activate
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <RequirePermission permission="manage-users">
            <Head title="Users" />
            <SettingsPageShell
                eyebrow="Access"
                title="Users"
                description="Provision accounts and assign exactly one role."
                actions={
                    <Button
                        type="button"
                        onClick={() => {
                            setCreateRole('');
                            setForm({ mode: 'create' });
                        }}
                    >
                        <Plus data-icon="inline-start" />
                        Add user
                    </Button>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={users}
                    rowKey={(user) => user.id}
                    emptyTitle="No users"
                    emptyDescription="Create an operator account to get started."
                />
            </SettingsPageShell>

            <CrudFormDialog
                open={form?.mode === 'create'}
                onOpenChange={(open) => {
                    if (!open) {
                        setForm(null);
                    }
                }}
                title="Add user"
                description="A temporary password is shown once after creation."
                action="/access/users"
                method="post"
                submitLabel="Create user"
                transform={(data) => ({
                    ...data,
                    role: createRole,
                })}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="user-name">Name</Label>
                            <Input id="user-name" name="name" required />
                            {errors.name ? (
                                <p className="text-destructive text-sm">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="user-email">Email</Label>
                            <Input
                                id="user-email"
                                name="email"
                                type="email"
                                required
                            />
                            {errors.email ? (
                                <p className="text-destructive text-sm">
                                    {errors.email}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="user-password">
                                Temporary password (optional)
                            </Label>
                            <Input
                                id="user-password"
                                name="password"
                                type="password"
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Role</Label>
                            <SearchableSelect
                                value={createRole}
                                onValueChange={setCreateRole}
                                placeholder="Select role"
                                options={roles.map((role) => ({
                                    value: role.name,
                                    label: role.name,
                                }))}
                            />
                            {errors.role ? (
                                <p className="text-destructive text-sm">
                                    {errors.role}
                                </p>
                            ) : null}
                        </div>
                    </>
                )}
            </CrudFormDialog>

            <CrudFormDialog
                open={form?.mode === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        setForm(null);
                    }
                }}
                title="Edit user"
                action={
                    form?.mode === 'edit'
                        ? `/access/users/${form.user.id}`
                        : '/access/users'
                }
                method="put"
                submitLabel="Save user"
                transform={(data) => ({
                    ...data,
                    role: editRole,
                })}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="edit-user-name">Name</Label>
                            <Input
                                id="edit-user-name"
                                name="name"
                                required
                                defaultValue={
                                    form?.mode === 'edit' ? form.user.name : ''
                                }
                            />
                            {errors.name ? (
                                <p className="text-destructive text-sm">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Role</Label>
                            <SearchableSelect
                                value={editRole}
                                onValueChange={setEditRole}
                                placeholder="Select role"
                                options={roles.map((role) => ({
                                    value: role.name,
                                    label: role.name,
                                }))}
                            />
                            {errors.role ? (
                                <p className="text-destructive text-sm">
                                    {errors.role}
                                </p>
                            ) : null}
                        </div>
                    </>
                )}
            </CrudFormDialog>

            <ConfirmActionDialog
                open={lifecycleTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setLifecycleTarget(null);
                    }
                }}
                title={
                    lifecycleTarget?.is_active
                        ? 'Deactivate user'
                        : 'Activate user'
                }
                description={
                    lifecycleTarget ? (
                        <>
                            {lifecycleTarget.is_active
                                ? 'Deactivating immediately blocks sign-in for'
                                : 'Reactivating restores access for'}{' '}
                            <strong>{lifecycleTarget.name}</strong>.
                        </>
                    ) : (
                        ''
                    )
                }
                action={
                    lifecycleTarget
                        ? `/access/users/${lifecycleTarget.id}`
                        : undefined
                }
                method="put"
                data={
                    lifecycleTarget
                        ? { is_active: !lifecycleTarget.is_active }
                        : undefined
                }
                confirmLabel={
                    lifecycleTarget?.is_active ? 'Deactivate' : 'Activate'
                }
                destructive={lifecycleTarget?.is_active === true}
            />

            <Dialog
                open={tempPassword !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setTempPassword(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Temporary password</DialogTitle>
                        <DialogDescription>
                            {tempPassword
                                ? `Copy credentials for ${tempPassword.user_name}. The password will not be shown again.`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>
                    {tempPassword ? (
                        <div className="rounded-[var(--radius-sm)] border border-border bg-surface-2 p-3 font-mono text-xs">
                            <div>Email: {tempPassword.email}</div>
                            <div className="mt-1 break-all">
                                Password: {tempPassword.password}
                            </div>
                        </div>
                    ) : null}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={async () => {
                                if (!tempPassword) {
                                    return;
                                }

                                await navigator.clipboard.writeText(
                                    tempPassword.password,
                                );
                                toast.success('Password copied');
                            }}
                        >
                            Copy password
                        </Button>
                        <Button
                            type="button"
                            onClick={() => setTempPassword(null)}
                        >
                            Done
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </RequirePermission>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Access', href: '/access/users' }, { title: 'Users', href: '/access/users' }],
};
