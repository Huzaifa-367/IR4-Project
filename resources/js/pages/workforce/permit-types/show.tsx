import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CrewRole = {
    id: number;
    role_code: string;
    label: string;
    min_count: number;
    is_mandatory: boolean;
    sort_order: number;
};

type PermitTypeDetail = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    sa_form_code: string | null;
    requires_gas_test: boolean;
    requires_approver: boolean;
    requires_joint_inspection: boolean;
    default_validity_minutes: number;
    is_active: boolean;
    roles: CrewRole[];
};

type Props = {
    permitType: PermitTypeDetail;
};

export default function PermitTypeShow({ permitType }: Props) {
    const [showCreate, setShowCreate] = useState(false);

    const columns: SettingsColumn<CrewRole>[] = [
        {
            key: 'code',
            header: 'Code',
            cell: (row) => (
                <span className="font-mono text-xs">{row.role_code}</span>
            ),
        },
        { key: 'label', header: 'Label', cell: (row) => row.label },
        { key: 'min', header: 'Min', cell: (row) => row.min_count },
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
                        if (confirm(`Remove “${row.label}”?`)) {
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
            <Head title={permitType.name} />
            <SettingsPageShell
                eyebrow="Workforce"
                title={permitType.name}
                description={
                    permitType.description ??
                    `${permitType.code}${permitType.sa_form_code ? ` · ${permitType.sa_form_code}` : ''}`
                }
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/workforce/permit-types">Back</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/access/crew-roles">All crew roles</Link>
                        </Button>
                        <Button
                            type="button"
                            onClick={() => setShowCreate((value) => !value)}
                        >
                            {showCreate ? 'Cancel' : 'Add crew role'}
                        </Button>
                    </div>
                }
            >
                <div className="mb-6 flex flex-wrap gap-2">
                    <StatusPill
                        label={permitType.is_active ? 'Active' : 'Inactive'}
                        tone={permitType.is_active ? 'ok' : 'neutral'}
                    />
                    {permitType.requires_gas_test && (
                        <StatusPill label="Gas test" tone="warn" />
                    )}
                    {permitType.requires_joint_inspection && (
                        <StatusPill label="Joint inspection" tone="warn" />
                    )}
                    {permitType.requires_approver && (
                        <StatusPill label="Approver" tone="warn" />
                    )}
                    <StatusPill
                        label={`Validity ${permitType.default_validity_minutes} min`}
                        tone="neutral"
                    />
                </div>

                <h2 className="mb-3 text-sm font-semibold tracking-tight text-text">
                    Crew roles
                </h2>
                <p className="mb-4 text-xs text-text-dim">
                    Workers assigned to this permit type must use one of these
                    role codes (e.g. fire watch on hot work).
                </p>

                {showCreate && (
                    <Form
                        action="/access/crew-roles"
                        method="post"
                        className="mb-6 grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2 lg:grid-cols-4"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setShowCreate(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="permit_type_id"
                                    value={permitType.id}
                                />
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
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="min_count">Min</Label>
                                    <Input
                                        id="min_count"
                                        name="min_count"
                                        type="number"
                                        min={0}
                                        defaultValue={1}
                                        required
                                    />
                                </div>
                                <label className="flex items-end gap-2 pb-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="is_mandatory"
                                        value="1"
                                        defaultChecked
                                        className="size-4 rounded border border-input"
                                    />
                                    Mandatory
                                </label>
                                <div className="sm:col-span-2 lg:col-span-4">
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
                    rows={permitType.roles}
                    rowKey={(row) => row.id}
                    emptyTitle="No crew roles"
                    emptyDescription="Add the personnel roles this permit type requires."
                />
            </SettingsPageShell>
        </>
    );
}

PermitTypeShow.layout = {
    breadcrumbs: [
        { title: 'Workforce', href: '/workforce/workers' },
        { title: 'Permit types', href: '/workforce/permit-types' },
    ],
};
