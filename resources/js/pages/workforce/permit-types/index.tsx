import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PermitTypeCatalogueRow } from '@/types/permit';

type Props = {
    permitTypes: PermitTypeCatalogueRow[];
};

export default function PermitTypesIndex({ permitTypes }: Props) {
    const [showCreate, setShowCreate] = useState(false);

    const columns: SettingsColumn<PermitTypeCatalogueRow>[] = [
        {
            key: 'code',
            header: 'Code',
            cell: (row) => (
                <span className="font-mono text-xs">{row.code}</span>
            ),
        },
        { key: 'name', header: 'Name', cell: (row) => row.name },
        {
            key: 'sa_form',
            header: 'SA form',
            cell: (row) => row.sa_form_code ?? '—',
        },
        {
            key: 'roles',
            header: 'Roles',
            cell: (row) => row.roles_count,
        },
        {
            key: 'checklist',
            header: 'Checklist',
            cell: (row) => row.checklist_items_count,
        },
        {
            key: 'docs',
            header: 'Doc reqs',
            cell: (row) => row.document_requirements_count,
        },
        {
            key: 'gas',
            header: 'Gas',
            cell: (row) => (row.requires_gas_test ? 'Yes' : 'No'),
        },
        {
            key: 'active',
            header: 'Active',
            cell: (row) => (
                <StatusPill
                    label={row.is_active ? 'Active' : 'Inactive'}
                    tone={row.is_active ? 'ok' : 'neutral'}
                />
            ),
        },
        {
            key: 'open',
            header: '',
            className: 'w-36 text-right',
            cell: (row) => (
                <Button asChild size="sm">
                    <Link href={`/workforce/permit-types/${row.id}`}>
                        Configure
                    </Link>
                </Button>
            ),
        },
        {
            key: 'toggle',
            header: '',
            className: 'w-28 text-right',
            cell: (row) => (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.put(`/workforce/permit-types/${row.id}`, {
                            is_active: !row.is_active,
                        })
                    }
                >
                    {row.is_active ? 'Deactivate' : 'Activate'}
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Permit types" />
            <SettingsPageShell
                eyebrow="Catalogue"
                title="Permit types"
                description="Open a type to manage its crew roles, checklist, gas pack, SIMOPS conflicts, and document requirements."
                actions={
                    <Button type="button" onClick={() => setShowCreate((v) => !v)}>
                        {showCreate ? 'Cancel' : 'Add type'}
                    </Button>
                }
            >
                {showCreate && (
                    <form
                        className="mb-6 grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const form = event.currentTarget;
                            const data = new FormData(form);
                            router.post('/workforce/permit-types', Object.fromEntries(data), {
                                onSuccess: () => {
                                    setShowCreate(false);
                                    form.reset();
                                },
                            });
                        }}
                    >
                        <div className="grid gap-1">
                            <Label htmlFor="code">Code</Label>
                            <Input id="code" name="code" required />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" name="name" required />
                        </div>
                        <div className="grid gap-1 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Input id="description" name="description" />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox name="requires_gas_test" value="1" />
                            Requires gas test
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox name="requires_joint_inspection" value="1" defaultChecked />
                            Requires joint inspection
                        </label>
                        <div className="sm:col-span-2">
                            <Button type="submit">Create type</Button>
                        </div>
                    </form>
                )}
                <SettingsDataTable
                    columns={columns}
                    rows={permitTypes}
                    rowKey={(row) => row.id}
                    emptyTitle="No permit types"
                    emptyDescription="Seed the catalogue or add a type."
                />
            </SettingsPageShell>
        </>
    );
}

PermitTypesIndex.layout = {
    breadcrumbs: [
        { title: 'Catalogue', href: '/workforce/permit-types' },
        { title: 'Permit types', href: '/workforce/permit-types' },
    ],
};
