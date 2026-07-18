import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { RequirePermission } from '@/components/ir4/require-permission';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type RoleRow = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
    is_read_only: boolean;
    users_count: number;
    permissions: string[];
};

type Props = {
    roles: RoleRow[];
    catalogue: Record<string, string[]>;
};

export default function RolesIndex({ roles, catalogue }: Props) {
    return (
        <RequirePermission permission="manage-roles">
            <Head title="Roles" />
            <div className="space-y-8 p-6">
                <Heading
                    title="Roles"
                    description="Compose roles from the permission catalogue. Super Admin is locked."
                />

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Role</th>
                                <th className="px-3 py-2 font-medium">Flags</th>
                                <th className="px-3 py-2 font-medium">Users</th>
                                <th className="px-3 py-2 font-medium">
                                    Permissions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {roles.map((role) => (
                                <tr
                                    key={role.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">
                                        <div className="font-medium">
                                            {role.name}
                                        </div>
                                        {role.description && (
                                            <div className="text-muted-foreground">
                                                {role.description}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {[
                                            role.is_system ? 'system' : null,
                                            role.is_read_only
                                                ? 'read-only'
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(', ') || '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {role.users_count}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {role.is_system
                                            ? 'all'
                                            : role.permissions.length === 0
                                              ? 'none'
                                              : role.permissions.join(', ')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="max-w-xl space-y-4 rounded-lg border border-border p-4">
                    <h2 className="font-medium">Create role</h2>
                    <Form
                        action="/settings/roles"
                        method="post"
                        className="space-y-4"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        maxLength={150}
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-danger">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Input
                                        id="description"
                                        name="description"
                                        maxLength={500}
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="is_read_only"
                                        value="1"
                                    />
                                    Read-only (view-* only)
                                </label>
                                <div className="space-y-2">
                                    <p className="text-sm font-medium">
                                        Permissions
                                    </p>
                                    <div className="max-h-64 space-y-3 overflow-y-auto rounded border border-border p-3">
                                        {Object.entries(catalogue).map(
                                            ([group, permissions]) => (
                                                <div key={group}>
                                                    <p className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                                        {group}
                                                    </p>
                                                    <div className="grid gap-1">
                                                        {permissions.map(
                                                            (permission) => (
                                                                <label
                                                                    key={
                                                                        permission
                                                                    }
                                                                    className="flex items-center gap-2 text-sm"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        name="permissions[]"
                                                                        value={
                                                                            permission
                                                                        }
                                                                    />
                                                                    <span className="font-mono text-xs">
                                                                        {
                                                                            permission
                                                                        }
                                                                    </span>
                                                                </label>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                    {errors.permissions && (
                                        <p className="text-sm text-danger">
                                            {errors.permissions}
                                        </p>
                                    )}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Create role
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </RequirePermission>
    );
}
