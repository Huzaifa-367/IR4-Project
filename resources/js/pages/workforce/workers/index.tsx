import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { WorkerIdentityCell } from '@/components/ir4/worker-identity-cell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Worker, WorkerListFilters } from '@/types/worker';

type Props = {
    workers: {
        data: Worker[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: WorkerListFilters;
    workerTypes: Array<{ value: string; label: string }>;
    canManage: boolean;
    canSeeIdentity: boolean;
};

type FormState = { mode: 'create' } | { mode: 'edit'; worker: Worker };

export default function WorkersIndex({
    workers,
    filters,
    workerTypes,
    canManage,
    canSeeIdentity,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [contractor, setContractor] = useState(filters.contractor);
    const [workerType, setWorkerType] = useState(filters.worker_type || 'all');
    const [form, setForm] = useState<FormState | null>(null);
    const [editType, setEditType] = useState('contractor');

    const queryParams = {
        search: search || undefined,
        contractor: contractor || undefined,
        worker_type: workerType === 'all' ? undefined : workerType,
    };

    const applyFilters = (): void => {
        router.get('/workforce/workers', queryParams, {
            preserveState: true,
            replace: true,
        });
    };

    const columns: SettingsColumn<Worker>[] = [
        {
            key: 'name',
            header: 'Name',
            cell: (worker) => (
                <Link
                    href={`/workforce/workers/${worker.id}`}
                    className="font-medium text-text hover:underline"
                >
                    <WorkerIdentityCell name={worker.name} />
                </Link>
            ),
        },
        {
            key: 'contractor',
            header: 'Contractor',
            cell: (worker) => worker.contractor,
        },
        {
            key: 'type',
            header: 'Type',
            cell: (worker) => worker.worker_type_label,
        },
        {
            key: 'role',
            header: 'Role',
            cell: (worker) => worker.role_title ?? '—',
        },
        {
            key: 'present',
            header: 'Present',
            cell: (worker) => (
                <StatusPill
                    label={worker.present ? 'On site' : 'Off site'}
                    tone={worker.present ? 'ok' : 'neutral'}
                />
            ),
        },
        {
            key: 'active',
            header: 'Active',
            cell: (worker) => (
                <StatusPill
                    label={worker.is_active ? 'Active' : 'Inactive'}
                    tone={worker.is_active ? 'ok' : 'crit'}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-32 text-right',
            cell: (worker) => (
                <div className="flex justify-end gap-1">
                    {canManage ? (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                setEditType(worker.worker_type);
                                setForm({ mode: 'edit', worker });
                            }}
                        >
                            Edit
                        </Button>
                    ) : null}
                    <Button asChild size="sm" variant="ghost">
                        <Link href={`/workforce/workers/${worker.id}`}>
                            View
                        </Link>
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Workers" />
            <SettingsPageShell
                eyebrow="Workforce"
                title="Workers"
                description="Site personnel registry. Identity fields require view-worker-identity."
                actions={
                    canManage ? (
                        <>
                            <Button asChild variant="outline">
                                <Link href="/workforce/workers/import">
                                    Import
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                onClick={() => {
                                    setEditType('contractor');
                                    setForm({ mode: 'create' });
                                }}
                            >
                                <Plus data-icon="inline-start" />
                                Add worker
                            </Button>
                        </>
                    ) : undefined
                }
                filters={
                    <>
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={
                                canSeeIdentity
                                    ? 'Name, badge…'
                                    : 'Contractor or role'
                            }
                            className="w-full sm:w-56"
                            aria-label="Search workers"
                        />
                        <Input
                            value={contractor}
                            onChange={(event) =>
                                setContractor(event.target.value)
                            }
                            placeholder="Contractor"
                            className="w-40"
                            aria-label="Filter by contractor"
                        />
                        <Select
                            value={workerType}
                            onValueChange={setWorkerType}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        All types
                                    </SelectItem>
                                    {workerTypes.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={applyFilters}
                        >
                            Apply
                        </Button>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={workers.data}
                    rowKey={(worker) => worker.id}
                    meta={workers.meta}
                    pageUrl="/workforce/workers"
                    queryParams={queryParams}
                    emptyTitle="No workers"
                    emptyDescription="No workers match these filters."
                />
            </SettingsPageShell>

            <CrudFormDialog
                open={form !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setForm(null);
                    }
                }}
                title={form?.mode === 'edit' ? 'Edit worker' : 'Add worker'}
                action={
                    form?.mode === 'edit'
                        ? `/workforce/workers/${form.worker.id}`
                        : '/workforce/workers'
                }
                method={form?.mode === 'edit' ? 'put' : 'post'}
                submitLabel={
                    form?.mode === 'edit' ? 'Save worker' : 'Create worker'
                }
                encType="multipart/form-data"
                transform={(data) => ({ ...data, worker_type: editType })}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="worker-name">Name</Label>
                            <Input
                                id="worker-name"
                                name="name"
                                required
                                maxLength={150}
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? form.worker.name
                                        : ''
                                }
                            />
                            {errors.name ? (
                                <p className="text-sm text-destructive">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="worker-contractor">
                                Contractor
                            </Label>
                            <Input
                                id="worker-contractor"
                                name="contractor"
                                required
                                maxLength={150}
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? form.worker.contractor
                                        : ''
                                }
                            />
                            {errors.contractor ? (
                                <p className="text-sm text-destructive">
                                    {errors.contractor}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Worker type</Label>
                            <Select
                                value={editType}
                                onValueChange={setEditType}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {workerTypes.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="worker-role">Role title</Label>
                            <Input
                                id="worker-role"
                                name="role_title"
                                maxLength={150}
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? (form.worker.role_title ?? '')
                                        : ''
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="worker-badge">Badge number</Label>
                            <Input
                                id="worker-badge"
                                name="badge_number"
                                maxLength={100}
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? (form.worker.badge_number ?? '')
                                        : ''
                                }
                            />
                            {errors.badge_number ? (
                                <p className="text-sm text-destructive">
                                    {errors.badge_number}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="worker-employee-code">
                                Employee code
                            </Label>
                            <Input
                                id="worker-employee-code"
                                name="employee_code"
                                maxLength={100}
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? (form.worker.employee_code ?? '')
                                        : ''
                                }
                            />
                            {errors.employee_code ? (
                                <p className="text-sm text-destructive">
                                    {errors.employee_code}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="worker-phone">Phone</Label>
                            <Input
                                id="worker-phone"
                                name="phone"
                                maxLength={40}
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? (form.worker.phone ?? '')
                                        : ''
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="worker-photo">Photo</Label>
                            <Input
                                id="worker-photo"
                                name="photo"
                                type="file"
                                accept="image/jpeg,image/png"
                            />
                            {errors.photo ? (
                                <p className="text-sm text-destructive">
                                    {errors.photo}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="worker-notes">Notes</Label>
                            <textarea
                                id="worker-notes"
                                name="notes"
                                rows={3}
                                maxLength={5000}
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? (form.worker.notes ?? '')
                                        : ''
                                }
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                    </>
                )}
            </CrudFormDialog>
        </>
    );
}

WorkersIndex.layout = {
    breadcrumbs: [{ title: 'Workforce', href: '/workforce/workers' }, { title: 'Workers', href: '/workforce/workers' }],
};
