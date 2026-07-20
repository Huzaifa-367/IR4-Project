import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import type { HseOption, LsrPrefill, LsrViolation } from '@/types/hse';

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
};

const ALL = 'all';

export default function LsrIndex({
    violations,
    filters,
    categoryOptions,
    canLog,
    canClose,
}: Props) {
    const [closeId, setCloseId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || ALL);
    const [category, setCategory] = useState(filters.category || ALL);

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
                    href={`/lsr-violations/${row.id}`}
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
                        <Link href={`/lsr-violations/${row.id}`}>Open</Link>
                    </Button>
                    {canClose && row.status === 'open' && (
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            onClick={() => setCloseId(row.id)}
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
                            <Button asChild>
                                <Link href="/lsr-violations/create">
                                    Log LSR
                                </Link>
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
                        <Select
                            value={category}
                            onValueChange={(value) => {
                                setCategory(value);
                                cancelDebounce();
                                applyFilters({ category: value });
                            }}
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={ALL}>
                                        All categories
                                    </SelectItem>
                                    {categoryOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
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
                open={closeId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCloseId(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Close LSR #{closeId}</DialogTitle>
                    </DialogHeader>
                    <Form
                        action={`/lsr-violations/${closeId}/close`}
                        method="post"
                        className="flex flex-col gap-4"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setCloseId(null)}
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
                                        onClick={() => setCloseId(null)}
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
                </DialogContent>
            </Dialog>
        </>
    );
}

LsrIndex.layout = {
    breadcrumbs: [{ title: 'LSR', href: '/lsr-violations' }],
};
