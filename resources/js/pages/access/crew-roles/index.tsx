import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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

export default function CrewRolesIndex({ roles, permitTypes }: Props) {
    const [showCreate, setShowCreate] = useState(false);

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
            className: 'w-28 text-right',
            cell: (row) => (
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
                            router.delete(`/access/crew-roles/${row.id}`);
                        }
                    }}
                >
                    Remove
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Crew roles" />
            <SettingsPageShell
                eyebrow="Access"
                title="Crew roles"
                description="Permit personnel roles (entrant, fire watch, standby, …) — not login permissions. Assigned to workers on each permit."
                actions={
                    <Button
                        type="button"
                        onClick={() => setShowCreate((value) => !value)}
                    >
                        {showCreate ? 'Cancel' : 'Add crew role'}
                    </Button>
                }
            >
                {showCreate && (
                    <Form
                        action="/access/crew-roles"
                        method="post"
                        className="mb-6 grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2 lg:grid-cols-3"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setShowCreate(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1 sm:col-span-2 lg:col-span-1">
                                    <Label htmlFor="permit_type_id">
                                        Permit type
                                    </Label>
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
                                            <option
                                                key={type.id}
                                                value={type.id}
                                            >
                                                {type.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.permit_type_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.permit_type_id}
                                        </p>
                                    )}
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
                                    {errors.role_code && (
                                        <p className="text-sm text-destructive">
                                            {errors.role_code}
                                        </p>
                                    )}
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
                                    <Label htmlFor="min_count">
                                        Minimum count
                                    </Label>
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
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="is_mandatory"
                                        value="1"
                                        defaultChecked
                                        className="size-4 rounded border border-input"
                                    />
                                    Mandatory on this permit type
                                </label>
                                <div className="flex items-end">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Create role
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                <SettingsDataTable
                    columns={columns}
                    rows={roles}
                    rowKey={(row) => row.id}
                    emptyTitle="No crew roles"
                    emptyDescription="Seed the permit catalogue or add a role for a permit type."
                />
            </SettingsPageShell>
        </>
    );
}

CrewRolesIndex.layout = {
    breadcrumbs: [
        { title: 'Access', href: '/access/users' },
        { title: 'Crew roles', href: '/access/crew-roles' },
    ],
};
