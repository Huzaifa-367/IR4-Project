import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { RequirePermission } from '@/components/ir4/require-permission';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    role: string | null;
};

type RoleOption = {
    id: number;
    name: string;
    is_system: boolean;
    is_read_only: boolean;
};

type Props = {
    users: UserRow[];
    roles: RoleOption[];
};

export default function UsersIndex({ users, roles }: Props) {
    return (
        <RequirePermission permission="manage-users">
            <Head title="Users" />
            <div className="space-y-8 p-6">
                <Heading
                    title="Users"
                    description="Provision accounts and assign exactly one role."
                />

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Email</th>
                                <th className="px-3 py-2 font-medium">Role</th>
                                <th className="px-3 py-2 font-medium">
                                    Active
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">{user.name}</td>
                                    <td className="px-3 py-2">{user.email}</td>
                                    <td className="px-3 py-2">
                                        {user.role ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {user.is_active ? 'yes' : 'no'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="max-w-xl space-y-4 rounded-lg border border-border p-4">
                    <h2 className="font-medium">Create user</h2>
                    <Form
                        action="/settings/users"
                        method="post"
                        className="space-y-4"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input id="name" name="name" required />
                                    {errors.name && (
                                        <p className="text-sm text-danger">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                    />
                                    {errors.email && (
                                        <p className="text-sm text-danger">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        Temporary password (optional)
                                    </Label>
                                    <Input
                                        id="password"
                                        name="password"
                                        type="password"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="role">Role</Label>
                                    <select
                                        id="role"
                                        name="role"
                                        required
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">Select role</option>
                                        {roles.map((role) => (
                                            <option
                                                key={role.id}
                                                value={role.name}
                                            >
                                                {role.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.role && (
                                        <p className="text-sm text-danger">
                                            {errors.role}
                                        </p>
                                    )}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Create user
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </RequirePermission>
    );
}
