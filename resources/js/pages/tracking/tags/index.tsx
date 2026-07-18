import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

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
    tags: {
        data: TagRow[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: { status: string; search: string };
    statuses: Array<{ value: string; label: string }>;
    workers: Array<{ id: number; name: string }>;
    spareCount: number;
    canManage: boolean;
};

export default function TagsIndex({
    tags,
    filters,
    statuses,
    workers,
    spareCount,
    canManage,
}: Props) {
    return (
        <>
            <Head title="RFID tags" />
            <div className="space-y-6 p-6">
                <Heading
                    title="RFID tags"
                    description={`${spareCount} in stock`}
                />

                {canManage && (
                    <Form
                        action="/tracking/tags"
                        method="post"
                        className="flex flex-wrap gap-2"
                    >
                        {({ processing }) => (
                            <>
                                <input
                                    name="tag_uid"
                                    placeholder="Tag UID"
                                    required
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                />
                                <Button type="submit" disabled={processing}>
                                    Add tag
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get(
                            '/tracking/tags',
                            {
                                status: String(form.get('status') ?? ''),
                                search: String(form.get('search') ?? ''),
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <select
                        name="status"
                        defaultValue={filters.status}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    <input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search UID"
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    />
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">UID</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Worker</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {tags.data.map((tag) => (
                                <tr
                                    key={tag.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {tag.tag_uid}
                                    </td>
                                    <td className="px-3 py-2">
                                        {tag.status_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {tag.worker_name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {canManage &&
                                            tag.status === 'in_stock' && (
                                                <Form
                                                    action={`/tracking/tags/${tag.id}/assign`}
                                                    method="post"
                                                    className="inline-flex gap-1"
                                                >
                                                    {({ processing }) => (
                                                        <>
                                                            <select
                                                                name="worker_id"
                                                                required
                                                                className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                                            >
                                                                <option value="">
                                                                    Worker…
                                                                </option>
                                                                {workers.map(
                                                                    (w) => (
                                                                        <option
                                                                            key={
                                                                                w.id
                                                                            }
                                                                            value={
                                                                                w.id
                                                                            }
                                                                        >
                                                                            {
                                                                                w.name
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Assign
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            )}
                                        {canManage &&
                                            tag.status === 'assigned' && (
                                                <Form
                                                    action={`/tracking/tags/${tag.id}/unassign`}
                                                    method="post"
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Unassign
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
