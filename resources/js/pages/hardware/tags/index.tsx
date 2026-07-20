import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
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
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
import type { PaginatedMeta } from '@/types/hardware';

type TagRow = {
    id: number;
    tag_uid: string;
    status: string;
    status_label: string;
    worker_id: number | null;
    worker_name: string | null;
    assigned_at: string | null;
};

type Props = {
    tags: { data: TagRow[]; meta: PaginatedMeta };
    filters: { status: string; search: string };
    statuses: Array<{ value: string; label: string }>;
    workers: Array<{ id: number; name: string }>;
    spareCount: number;
    canManage: boolean;
};

const STATUS_TONE: Record<string, StatusPillTone> = {
    in_stock: 'accent',
    assigned: 'ok',
    lost: 'crit',
    damaged: 'warn',
    retired: 'neutral',
};

export default function TagsIndex({
    tags,
    filters,
    statuses,
    workers,
    spareCount,
    canManage,
}: Props) {
    const [status, setStatus] = useState(filters.status || 'all');
    const [search, setSearch] = useState(filters.search);
    const [addOpen, setAddOpen] = useState(false);
    const [assignTarget, setAssignTarget] = useState<TagRow | null>(null);
    const [assignWorker, setAssignWorker] = useState('');
    const [unassignTarget, setUnassignTarget] = useState<TagRow | null>(null);

    const queryParams = {
        status: status === 'all' ? undefined : status,
        search: search || undefined,
    };

    const applyFilters = (
        patch: Partial<{ status: string; search: string }> = {},
    ): void => {
        const nextStatus = patch.status ?? status;
        const nextSearch = patch.search ?? search;

        visitFilters('/hardware/tags', {
            status: nextStatus === 'all' ? undefined : nextStatus,
            search: nextSearch || undefined,
        });
    };

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ search: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const columns: SettingsColumn<TagRow>[] = [
        {
            key: 'uid',
            header: 'UID',
            cell: (tag) => (
                <span className="font-mono text-xs">{tag.tag_uid}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (tag) => (
                <StatusPill
                    label={tag.status_label}
                    tone={STATUS_TONE[tag.status] ?? 'neutral'}
                />
            ),
        },
        {
            key: 'worker',
            header: 'Worker',
            cell: (tag) => tag.worker_name ?? '—',
        },
        {
            key: 'actions',
            header: '',
            className: 'w-40 text-right',
            cell: (tag) =>
                canManage && tag.status === 'in_stock' ? (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            setAssignWorker('');
                            setAssignTarget(tag);
                        }}
                    >
                        Assign
                    </Button>
                ) : canManage && tag.status === 'assigned' ? (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setUnassignTarget(tag)}
                    >
                        Unassign
                    </Button>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="RFID tags" />
            <SettingsPageShell
                eyebrow="Hardware"
                title="RFID Tags"
                description={`${spareCount} in stock`}
                actions={
                    canManage ? (
                        <Button type="button" onClick={() => setAddOpen(true)}>
                            <Plus data-icon="inline-start" />
                            Add tag
                        </Button>
                    ) : undefined
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
                            placeholder="Search UID…"
                            className="w-full sm:w-56"
                            aria-label="Search tags"
                        />
                        <Select
                            value={status}
                            onValueChange={(value) => {
                                setStatus(value);
                                cancelDebounce();
                                applyFilters({ status: value });
                            }}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    {statuses.map((s) => (
                                        <SelectItem
                                            key={s.value}
                                            value={s.value}
                                        >
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={tags.data}
                    rowKey={(tag) => tag.id}
                    meta={tags.meta}
                    pageUrl="/hardware/tags"
                    queryParams={queryParams}
                    emptyTitle="No tags"
                    emptyDescription="Register the first RFID tag to build the spare pool."
                />
            </SettingsPageShell>

            <CrudFormDialog
                open={addOpen}
                onOpenChange={setAddOpen}
                title="Add tag"
                description="Registers a spare tag (status: in stock)."
                action="/hardware/tags"
                method="post"
                submitLabel="Create tag"
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="tag-uid">Tag UID</Label>
                            <Input
                                id="tag-uid"
                                name="tag_uid"
                                required
                                maxLength={150}
                            />
                            {errors.tag_uid ? (
                                <p className="text-sm text-destructive">
                                    {errors.tag_uid}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="tag-notes">Notes</Label>
                            <Input
                                id="tag-notes"
                                name="notes"
                                maxLength={5000}
                            />
                        </div>
                    </>
                )}
            </CrudFormDialog>

            <CrudFormDialog
                open={assignTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAssignTarget(null);
                    }
                }}
                title="Assign tag"
                description={
                    assignTarget
                        ? `Assign ${assignTarget.tag_uid} to a worker.`
                        : undefined
                }
                action={
                    assignTarget
                        ? `/hardware/tags/${assignTarget.id}/assign`
                        : ''
                }
                method="post"
                submitLabel="Assign"
                disableSubmit={!assignWorker}
                transform={(data) => ({ ...data, worker_id: assignWorker })}
            >
                {() => (
                    <div className="flex flex-col gap-2">
                        <Label>Worker</Label>
                        <Select
                            value={assignWorker}
                            onValueChange={setAssignWorker}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a worker…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {workers.map((w) => (
                                        <SelectItem
                                            key={w.id}
                                            value={String(w.id)}
                                        >
                                            {w.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                )}
            </CrudFormDialog>

            <ConfirmActionDialog
                open={unassignTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setUnassignTarget(null);
                    }
                }}
                title="Unassign tag"
                description={
                    unassignTarget ? (
                        <>
                            Return <strong>{unassignTarget.tag_uid}</strong> to
                            the spare pool
                            {unassignTarget.worker_name
                                ? ` (currently on ${unassignTarget.worker_name})`
                                : ''}
                            ?
                        </>
                    ) : (
                        ''
                    )
                }
                action={
                    unassignTarget
                        ? `/hardware/tags/${unassignTarget.id}/unassign`
                        : undefined
                }
                method="post"
                confirmLabel="Unassign"
            />
        </>
    );
}

TagsIndex.layout = {
    breadcrumbs: [{ title: 'Hardware', href: '/hardware/assets' }, { title: 'RFID Tags', href: '/hardware/tags' }],
};
