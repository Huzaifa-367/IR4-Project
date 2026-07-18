import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { RequirePermission } from '@/components/ir4/require-permission';
import { WorkerIdentityCell } from '@/components/ir4/worker-identity-cell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Worker, WorkerListFilters } from '@/types/worker';

type Props = {
    workers: {
        data: Worker[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
    filters: WorkerListFilters;
    workerTypes: Array<{ value: string; label: string }>;
    canManage: boolean;
    canSeeIdentity: boolean;
};

export default function WorkersIndex({
    workers,
    filters,
    workerTypes,
    canManage,
    canSeeIdentity,
}: Props) {
    function applyFilters(patch: Partial<WorkerListFilters>): void {
        router.get(
            '/tracking/workers',
            {
                search: patch.search ?? filters.search,
                contractor: patch.contractor ?? filters.contractor,
                worker_type: patch.worker_type ?? filters.worker_type,
                is_active:
                    patch.is_active === undefined
                        ? filters.is_active
                            ? '1'
                            : '0'
                        : patch.is_active
                          ? '1'
                          : '0',
                present:
                    patch.present === undefined
                        ? filters.present === null
                            ? undefined
                            : filters.present
                              ? '1'
                              : '0'
                        : patch.present === null
                          ? undefined
                          : patch.present
                            ? '1'
                            : '0',
                sort: patch.sort ?? filters.sort,
                direction: patch.direction ?? filters.direction,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Workers" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Workers"
                        description="Site personnel registry. Identity fields require view-worker-identity."
                    />
                    {canManage && (
                        <div className="flex gap-2">
                            <Button asChild variant="outline">
                                <Link href="/tracking/workers/import">
                                    Import
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href="/tracking/workers/create">
                                    Add worker
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        applyFilters({
                            search: String(form.get('search') ?? ''),
                            contractor: String(form.get('contractor') ?? ''),
                            worker_type: String(form.get('worker_type') ?? ''),
                        });
                    }}
                >
                    {canSeeIdentity && (
                        <div className="grid gap-1">
                            <label
                                className="text-xs text-muted-foreground"
                                htmlFor="search"
                            >
                                Search
                            </label>
                            <Input
                                id="search"
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Name, badge…"
                                className="w-48"
                            />
                        </div>
                    )}
                    {!canSeeIdentity && (
                        <div className="grid gap-1">
                            <label
                                className="text-xs text-muted-foreground"
                                htmlFor="search"
                            >
                                Search (contractor / role)
                            </label>
                            <Input
                                id="search"
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Contractor or role"
                                className="w-48"
                            />
                        </div>
                    )}
                    <div className="grid gap-1">
                        <label
                            className="text-xs text-muted-foreground"
                            htmlFor="contractor"
                        >
                            Contractor
                        </label>
                        <Input
                            id="contractor"
                            name="contractor"
                            defaultValue={filters.contractor}
                            className="w-40"
                        />
                    </div>
                    <div className="grid gap-1">
                        <label
                            className="text-xs text-muted-foreground"
                            htmlFor="worker_type"
                        >
                            Type
                        </label>
                        <select
                            id="worker_type"
                            name="worker_type"
                            defaultValue={filters.worker_type}
                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">All</option>
                            {workerTypes.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">
                                    Contractor
                                </th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Role</th>
                                <th className="px-3 py-2 font-medium">
                                    Present
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Active
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {workers.data.map((worker) => (
                                <tr
                                    key={worker.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">
                                        <WorkerIdentityCell
                                            name={worker.name}
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        {worker.contractor}
                                    </td>
                                    <td className="px-3 py-2">
                                        {worker.worker_type_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {worker.role_title ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {worker.present
                                            ? 'on site'
                                            : 'off site'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {worker.is_active ? 'yes' : 'no'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link
                                                href={`/tracking/workers/${worker.id}`}
                                            >
                                                View
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {workers.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No workers match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-sm text-muted-foreground">
                    Page {workers.meta.current_page} of {workers.meta.last_page}{' '}
                    · {workers.meta.total} total
                </p>

                <RequirePermission permission="manage-workers">
                    <span className="sr-only">manage-workers available</span>
                </RequirePermission>
            </div>
        </>
    );
}
