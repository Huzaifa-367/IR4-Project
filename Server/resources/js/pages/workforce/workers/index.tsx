import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { WorkerForm } from '@/components/ir4/worker-form';
import { WorkerIdentityCell } from '@/components/ir4/worker-identity-cell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { FILTER_SEARCH_DEBOUNCE_MS, visitFilters } from '@/lib/visit-filters';
import tracking from '@/routes/tracking';
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
    openCreate?: boolean;
};

type FormState = { mode: 'create' } | { mode: 'edit'; worker: Worker };

export default function WorkersIndex({
    workers,
    filters,
    workerTypes,
    canManage,
    canSeeIdentity,
    openCreate = false,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [contractor, setContractor] = useState(filters.contractor);
    const [workerType, setWorkerType] = useState(filters.worker_type || 'all');
    const [form, setForm] = useState<FormState | null>(() =>
        openCreate && canManage ? { mode: 'create' } : null,
    );

    const applyFilters = (
        patch: Partial<{
            search: string;
            contractor: string;
            worker_type: string;
        }> = {},
    ): void => {
        const nextSearch = patch.search ?? search;
        const nextContractor = patch.contractor ?? contractor;
        const nextWorkerType = patch.worker_type ?? workerType;

        visitFilters(tracking.workers.index.url(), {
            search: nextSearch || undefined,
            contractor: nextContractor || undefined,
            worker_type: nextWorkerType === 'all' ? undefined : nextWorkerType,
        });
    };

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (patch: Partial<{ search: string; contractor: string }>) =>
            applyFilters(patch),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const queryParams = {
        search: search || undefined,
        contractor: contractor || undefined,
        worker_type: workerType === 'all' ? undefined : workerType,
    };

    const columns: SettingsColumn<Worker>[] = [
        {
            key: 'number',
            header: 'Number',
            className: 'w-28',
            cell: (worker) => (
                <Link
                    href={tracking.workers.show(worker.uuid)}
                    className="font-mono text-xs hover:underline"
                >
                    Worker #{worker.id}
                </Link>
            ),
        },
        {
            key: 'name',
            header: 'Name',
            cell: (worker) => (
                <Link
                    href={tracking.workers.show(worker.uuid)}
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
                                setForm({ mode: 'edit', worker });
                            }}
                        >
                            Edit
                        </Button>
                    ) : null}
                    <Button asChild size="sm" variant="ghost">
                        <Link href={tracking.workers.show(worker.uuid)}>
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
                                <Link href={tracking.workers.import()}>
                                    Import
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                onClick={() => {
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
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);
                                debouncedApplySearch({ search: value });
                            }}
                            placeholder={
                                canSeeIdentity
                                    ? 'Name, badge, government ID…'
                                    : 'Contractor, role, or nationality'
                            }
                            className="w-full sm:w-56"
                            aria-label="Search workers"
                        />
                        <Input
                            value={contractor}
                            onChange={(event) => {
                                const value = event.target.value;
                                setContractor(value);
                                debouncedApplySearch({ contractor: value });
                            }}
                            placeholder="Contractor"
                            className="w-40"
                            aria-label="Filter by contractor"
                        />
                        <SearchableSelect
                            value={workerType}
                            onValueChange={(value) => {
                                setWorkerType(value);
                                cancelDebounce();
                                applyFilters({ worker_type: value });
                            }}
                            placeholder="Type"
                            triggerClassName="w-40"
                            options={[
                                { value: 'all', label: 'All types' },
                                ...workerTypes.map((type) => ({
                                    value: type.value,
                                    label: type.label,
                                })),
                            ]}
                        />
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={workers.data}
                    rowKey={(worker) => worker.id}
                    meta={workers.meta}
                    pageUrl={tracking.workers.index.url()}
                    queryParams={queryParams}
                    emptyTitle="No workers"
                    emptyDescription="No workers match these filters."
                />
            </SettingsPageShell>

            <Dialog
                open={form !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setForm(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            {form?.mode === 'edit'
                                ? 'Edit worker'
                                : 'Add worker'}
                        </DialogTitle>
                        <DialogDescription>
                            {form?.mode === 'edit'
                                ? 'Update profile details.'
                                : 'Register a worker. Certifications and vaccination belong on the Documents tab after create.'}
                        </DialogDescription>
                    </DialogHeader>
                    {form ? (
                        <WorkerForm
                            action={
                                form.mode === 'edit'
                                    ? tracking.workers.update.url(
                                          form.worker.uuid,
                                      )
                                    : tracking.workers.store.url()
                            }
                            method={form.mode === 'edit' ? 'put' : 'post'}
                            workerTypes={workerTypes}
                            defaults={
                                form.mode === 'edit'
                                    ? {
                                          name: form.worker.name,
                                          contractor: form.worker.contractor,
                                          worker_type: form.worker.worker_type,
                                          role_title: form.worker.role_title,
                                          nationality: form.worker.nationality,
                                          date_of_birth:
                                              form.worker.date_of_birth,
                                          joined_on: form.worker.joined_on,
                                          government_id_number:
                                              form.worker.government_id_number,
                                          badge_number:
                                              form.worker.badge_number,
                                          employee_code:
                                              form.worker.employee_code,
                                          phone: form.worker.phone,
                                          notes: form.worker.notes,
                                      }
                                    : undefined
                            }
                            submitLabel={
                                form.mode === 'edit'
                                    ? 'Save worker'
                                    : 'Create worker'
                            }
                            className="space-y-4"
                            onSuccess={() => setForm(null)}
                        />
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

WorkersIndex.layout = {
    breadcrumbs: [
        { title: 'Workforce', href: tracking.workers.index() },
        { title: 'Workers', href: tracking.workers.index() },
    ],
};
