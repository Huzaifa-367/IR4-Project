import { Form, Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
import type { PaginatedMeta } from '@/types/hardware';
import type { HseOption, LsrPrefill, LsrViolation } from '@/types/hse';

type Named = { id: number; name: string };

type Props = {
    violations: { data: LsrViolation[]; meta: PaginatedMeta };
    filters: {
        search: string;
        status: string;
        category: string;
        sort: string;
        direction: string;
    };
    categoryOptions: HseOption[];
    statusOptions: HseOption[];
    canLog: boolean;
    canClose: boolean;
    prefill: LsrPrefill | null;
    zones: Named[];
    workers: Named[];
};

const ALL = 'all';

export default function LsrIndex({
    violations,
    filters,
    categoryOptions,
    canLog,
    canClose,
    prefill = null,
    zones = [],
    workers = [],
}: Props) {
    const [closing, setClosing] = useState<{ id: number; uuid: string } | null>(null);
    const [logOpen, setLogOpen] = useState(() => prefill !== null);
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || ALL);
    const [category, setCategory] = useState(filters.category || ALL);
    const isPpeLinked = prefill?.ppe_violation_id != null;
    const defaultOccurred =
        prefill?.occurred_at?.slice(0, 16) ??
        new Date().toISOString().slice(0, 16);
    const logForm = useForm({
        alert_id: prefill?.alert_id ? String(prefill.alert_id) : '',
        ppe_violation_id: prefill?.ppe_violation_id
            ? String(prefill.ppe_violation_id)
            : '',
        camera_id: prefill?.camera_id ? String(prefill.camera_id) : '',
        category: prefill?.category ?? '',
        occurred_at: defaultOccurred,
        zone_id: prefill?.zone_id ? String(prefill.zone_id) : '',
        worker_id: prefill?.worker_id ? String(prefill.worker_id) : '',
        description: prefill?.description ?? '',
    });

    useEffect(() => {
        if (!logOpen) {
            return;
        }

        logForm.setData({
            alert_id: prefill?.alert_id ? String(prefill.alert_id) : '',
            ppe_violation_id: prefill?.ppe_violation_id
                ? String(prefill.ppe_violation_id)
                : '',
            camera_id: prefill?.camera_id ? String(prefill.camera_id) : '',
            category: prefill?.category ?? '',
            occurred_at:
                prefill?.occurred_at?.slice(0, 16) ??
                new Date().toISOString().slice(0, 16),
            zone_id: prefill?.zone_id ? String(prefill.zone_id) : '',
            worker_id: prefill?.worker_id ? String(prefill.worker_id) : '',
            description: prefill?.description ?? '',
        });
        logForm.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset when dialog opens
    }, [logOpen, prefill]);
    function applyFilters(
        patch: Partial<{
            search: string;
            status: string;
            category: string;
        }> = {},
    ): void {
        const nextSearch = patch.search ?? search;
        const nextStatus = patch.status ?? status;
        const nextCategory = patch.category ?? category;

        visitFilters('/lsr-violations', {
            search: nextSearch || undefined,
            status: nextStatus === ALL ? undefined : nextStatus,
            category: nextCategory === ALL ? undefined : nextCategory,
        });
    }

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const queryParams = {
        search: search || undefined,
        status: status === ALL ? undefined : status,
        category: category === ALL ? undefined : category,
    };

    const columns: SettingsColumn<LsrViolation>[] = [
        {
            key: 'category',
            header: 'Category',
            cell: (row) => (
                <Link
                    href={`/lsr-violations/${row.uuid}`}
                    className="font-medium text-text hover:underline"
                >
                    {row.category_label}
                </Link>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <StatusPill
                    label={row.status_label}
                    tone={row.status === 'closed' ? 'ok' : 'warn'}
                />
            ),
        },
        {
            key: 'worker',
            header: 'Worker',
            cell: (row) => row.worker_label ?? '—',
        },
        {
            key: 'occurred',
            header: 'Occurred',
            cell: (row) =>
                row.occurred_at
                    ? new Date(row.occurred_at).toLocaleString()
                    : '—',
        },
        {
            key: 'actions',
            header: '',
            className: 'w-32 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button asChild size="sm" variant="ghost">
                        <Link href={`/lsr-violations/${row.uuid}`}>Open</Link>
                    </Button>
                    {canClose && row.status === 'open' && (
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            onClick={() => setClosing({ id: row.id, uuid: row.uuid })}
                        >
                            Close
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="LSR Violations" />
            <SettingsPageShell
                title="Life Saving Rules"
                description="All LSR rows are user-authored. Closing requires action taken."
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href="/lsr-violations/summary">Summary</Link>
                        </Button>
                        {canLog && (
                            <Button
                                type="button"
                                onClick={() => setLogOpen(true)}
                            >
                                Log LSR
                            </Button>
                        )}
                    </>
                }
                filters={
                    <>
                        <Input
                            value={search}
                            onChange={(event) => {
                                const value = event.target.value;
                                setSearch(value);
                                debouncedApplySearch(value);
                            }}
                            placeholder="Search description…"
                            className="w-full sm:w-56"
                            aria-label="Search LSR"
                        />
                        <div className="flex gap-1.5">
                            {(
                                [
                                    ['all', 'All'],
                                    ['open', 'Open'],
                                    ['closed', 'Closed'],
                                ] as const
                            ).map(([value, label]) => (
                                <Button
                                    key={value}
                                    type="button"
                                    size="sm"
                                    variant={
                                        status === value ? 'default' : 'outline'
                                    }
                                    onClick={() => {
                                        setStatus(value);
                                        cancelDebounce();
                                        applyFilters({ status: value });
                                    }}
                                >
                                    {label}
                                </Button>
                            ))}
                        </div>
                        <SearchableSelect
                            value={category}
                            onValueChange={(value) => {
                                setCategory(value);
                                cancelDebounce();
                                applyFilters({ category: value });
                            }}
                            placeholder="Category"
                            triggerClassName="w-48"
                            options={[
                                { value: ALL, label: 'All categories' },
                                ...categoryOptions.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                })),
                            ]}
                        />
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={violations.data}
                    rowKey={(row) => row.id}
                    meta={violations.meta}
                    pageUrl="/lsr-violations"
                    queryParams={queryParams}
                    emptyTitle="No LSR entries"
                    emptyDescription="No LSR entries match these filters."
                />
            </SettingsPageShell>

            <Dialog
                open={logOpen}
                onOpenChange={(open) => {
                    setLogOpen(open);
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Log LSR</DialogTitle>
                        <DialogDescription>
                            {prefill
                                ? `Prefill from alert #${prefill.alert_id} — review and submit.`
                                : 'Manual Life Saving Rule entry.'}
                        </DialogDescription>
                    </DialogHeader>

                    {isPpeLinked && (
                        <p className="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                            PPE-linked LSR keeps worker identity null (camera
                            never identified anyone).
                        </p>
                    )}

                    <form
                        className="grid gap-4"
                        onSubmit={(event: FormEvent<HTMLFormElement>) => {
                            event.preventDefault();

                            if (logForm.data.category === '') {
                                logForm.setError(
                                    'category',
                                    'Select a category.',
                                );

                                return;
                            }

                            if (logForm.data.occurred_at === '') {
                                logForm.setError(
                                    'occurred_at',
                                    'Occurred at is required.',
                                );

                                return;
                            }

                            logForm.post('/lsr-violations', {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setLogOpen(false);
                                    logForm.reset();
                                    logForm.clearErrors();
                                },
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="log-category">Category</Label>
                            <SearchableSelect
                                id="log-category"
                                required
                                value={logForm.data.category}
                                onValueChange={(value) => {
                                    logForm.setData('category', value);
                                    logForm.clearErrors('category');
                                }}
                                options={categoryOptions}
                            />
                            {logForm.errors.category ? (
                                <p className="text-sm text-destructive">
                                    {logForm.errors.category}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="log-occurred_at">Occurred at</Label>
                            <Input
                                id="log-occurred_at"
                                type="datetime-local"
                                required
                                value={logForm.data.occurred_at}
                                onChange={(event) =>
                                    logForm.setData(
                                        'occurred_at',
                                        event.target.value,
                                    )
                                }
                            />
                            {logForm.errors.occurred_at ? (
                                <p className="text-sm text-destructive">
                                    {logForm.errors.occurred_at}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="log-zone_id">Zone</Label>
                            <SearchableSelect
                                id="log-zone_id"
                                value={logForm.data.zone_id}
                                onValueChange={(value) =>
                                    logForm.setData('zone_id', value)
                                }
                                allowClear
                                clearLabel="—"
                                placeholder="—"
                                options={zones.map((zone) => ({
                                    value: String(zone.id),
                                    label: zone.name,
                                }))}
                            />
                            {logForm.errors.zone_id ? (
                                <p className="text-sm text-destructive">
                                    {logForm.errors.zone_id}
                                </p>
                            ) : null}
                        </div>

                        {!isPpeLinked ? (
                            <div className="grid gap-2">
                                <Label htmlFor="log-worker_id">Worker</Label>
                                <SearchableSelect
                                    id="log-worker_id"
                                    value={logForm.data.worker_id}
                                    onValueChange={(value) =>
                                        logForm.setData('worker_id', value)
                                    }
                                    allowClear
                                    clearLabel="—"
                                    placeholder="—"
                                    options={workers.map((worker) => ({
                                        value: String(worker.id),
                                        label: worker.name,
                                    }))}
                                />
                                {logForm.errors.worker_id ? (
                                    <p className="text-sm text-destructive">
                                        {logForm.errors.worker_id}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}

                        <div className="grid gap-2">
                            <Label htmlFor="log-description">Description</Label>
                            <textarea
                                id="log-description"
                                rows={4}
                                value={logForm.data.description}
                                onChange={(event) =>
                                    logForm.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                            {logForm.errors.description ? (
                                <p className="text-sm text-destructive">
                                    {logForm.errors.description}
                                </p>
                            ) : null}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setLogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={logForm.processing}
                            >
                                Submit LSR
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={closing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setClosing(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Close LSR #{closing?.id}</DialogTitle>
                    </DialogHeader>
                    {closing ? (
                    <Form
                        action={`/lsr-violations/${closing.uuid}/close`}
                        method="post"
                        className="flex flex-col gap-4"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setClosing(null)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="action_taken">
                                        Action taken (required)
                                    </Label>
                                    <Input
                                        id="action_taken"
                                        name="action_taken"
                                        required
                                        minLength={10}
                                    />
                                    {errors.action_taken && (
                                        <p className="text-sm text-destructive">
                                            {errors.action_taken}
                                        </p>
                                    )}
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setClosing(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Close
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

LsrIndex.layout = {
    breadcrumbs: [{ title: 'LSR', href: '/lsr-violations' }],
};
