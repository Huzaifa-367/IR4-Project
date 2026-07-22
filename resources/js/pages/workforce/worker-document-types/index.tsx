import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
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

type DocumentTypeRow = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    category: string;
    requires_expiry: boolean;
    requires_file: boolean;
    is_active: boolean;
    sort_order: number;
    documents_count: number;
};

type Props = {
    documentTypes: DocumentTypeRow[];
    categories: string[];
};

type FormState =
    | { mode: 'create' }
    | { mode: 'edit'; documentType: DocumentTypeRow };

function categoryLabel(category: string): string {
    return category
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export default function WorkerDocumentTypesIndex({
    documentTypes,
    categories,
}: Props) {
    const [form, setForm] = useState<FormState | null>(null);
    const [category, setCategory] = useState('competence');

    const columns: SettingsColumn<DocumentTypeRow>[] = [
        {
            key: 'code',
            header: 'Code',
            cell: (row) => (
                <span className="font-mono text-xs">{row.code}</span>
            ),
        },
        { key: 'name', header: 'Name', cell: (row) => row.name },
        {
            key: 'category',
            header: 'Category',
            cell: (row) => categoryLabel(row.category),
        },
        {
            key: 'requirements',
            header: 'Requirements',
            cell: (row) => (
                <span className="text-xs text-text-faint">
                    {[
                        row.requires_file ? 'File' : null,
                        row.requires_expiry ? 'Expiry' : null,
                    ]
                        .filter(Boolean)
                        .join(' · ') || 'None'}
                </span>
            ),
        },
        {
            key: 'documents',
            header: 'On file',
            cell: (row) => row.documents_count,
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
            key: 'actions',
            header: '',
            className: 'w-44 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            setCategory(row.category);
                            setForm({ mode: 'edit', documentType: row });
                        }}
                    >
                        Edit
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.put(
                                `/workforce/worker-document-types/${row.id}`,
                                { is_active: !row.is_active },
                            )
                        }
                    >
                        {row.is_active ? 'Deactivate' : 'Activate'}
                    </Button>
                </div>
            ),
        },
    ];

    const editing = form?.mode === 'edit' ? form.documentType : null;

    return (
        <>
            <Head title="Worker document types" />
            <SettingsPageShell
                eyebrow="Catalogue"
                title="Worker document types"
                description="Competence, medical, and identity document catalogue — required before permit crew assignment."
                actions={
                    <Button
                        type="button"
                        onClick={() => {
                            setCategory('competence');
                            setForm({ mode: 'create' });
                        }}
                    >
                        <Plus data-icon="inline-start" />
                        Add document type
                    </Button>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={documentTypes}
                    rowKey={(row) => row.id}
                    emptyTitle="No document types"
                    emptyDescription="Seed the permit catalogue or add a document type."
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
                        ? 'Edit document type'
                        : 'Add document type'
                }
                description={
                    form?.mode === 'edit' && editing && editing.documents_count > 0
                        ? 'This type has documents on file — deactivate instead of deleting.'
                        : undefined
                }
                action={
                    form?.mode === 'edit' && editing
                        ? `/workforce/worker-document-types/${editing.id}`
                        : '/workforce/worker-document-types'
                }
                method={form?.mode === 'edit' ? 'put' : 'post'}
                submitLabel={
                    form?.mode === 'edit' ? 'Save changes' : 'Create type'
                }
                transform={(data) => ({
                    ...data,
                    category,
                })}
            >
                {({ errors }) => (
                    <>
                        {form?.mode === 'create' ? (
                            <div className="grid gap-1">
                                <Label htmlFor="code">Code</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    required
                                    placeholder="gas_tester"
                                    pattern="[a-z][a-z0-9_]*"
                                />
                                {errors.code ? (
                                    <p className="text-sm text-destructive">
                                        {errors.code}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}

                        <div className="grid gap-1">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                defaultValue={editing?.name ?? ''}
                            />
                            {errors.name ? (
                                <p className="text-sm text-destructive">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                name="description"
                                defaultValue={editing?.description ?? ''}
                            />
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="category">Category</Label>
                            <SearchableSelect
                                id="category"
                                required
                                value={category}
                                onValueChange={setCategory}
                                options={categories.map((item) => ({
                                    value: item,
                                    label: categoryLabel(item),
                                }))}
                            />
                            {errors.category ? (
                                <p className="text-sm text-destructive">
                                    {errors.category}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="sort_order">Sort order</Label>
                            <Input
                                id="sort_order"
                                name="sort_order"
                                type="number"
                                min={0}
                                max={1000}
                                defaultValue={editing?.sort_order ?? 0}
                            />
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                name="requires_file"
                                value="1"
                                defaultChecked={editing?.requires_file ?? true}
                            />
                            Requires file attachment
                        </label>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                name="requires_expiry"
                                value="1"
                                defaultChecked={editing?.requires_expiry ?? true}
                            />
                            Requires expiry date
                        </label>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                name="is_active"
                                value="1"
                                defaultChecked={editing?.is_active ?? true}
                            />
                            Active in catalogue
                        </label>
                    </>
                )}
            </CrudFormDialog>
        </>
    );
}

WorkerDocumentTypesIndex.layout = {
    breadcrumbs: [
        { title: 'Catalogue', href: '/workforce/permit-types' },
        {
            title: 'Document types',
            href: '/workforce/worker-document-types',
        },
    ],
};
