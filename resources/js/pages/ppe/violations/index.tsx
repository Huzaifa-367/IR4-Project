import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/ir4/pagination';
import { Button } from '@/components/ui/button';
import { ViolationTypeLabels } from '@/types/enums';
import type { PpeViolation } from '@/types/ppe';

type Props = {
    violations: {
        data: PpeViolation[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: {
        violation_type: string;
        camera_id: string;
        review_status: string;
        from: string;
        to: string;
        is_backfill: string;
        search: string;
    };
    cameras: Array<{ id: number; name: string; reference: string }>;
    violationTypes: Array<{ value: string; label: string }>;
    reviewStatuses: Array<{ value: string; label: string }>;
    canReview: boolean;
    canExport: boolean;
};

export default function PpeViolationsIndex({
    violations,
    filters,
    cameras,
    violationTypes,
    reviewStatuses,
    canReview,
    canExport,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);

    const toggle = (id: number): void => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    return (
        <>
            <Head title="PPE Violations" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="PPE violations"
                        description={`${violations.meta.total} records`}
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="secondary" size="sm">
                            <Link href="/ppe/trends">Trends</Link>
                        </Button>
                        {canExport && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    const form = document.createElement('form');
                                    form.method = 'POST';
                                    form.action = '/ppe/violations/export';
                                    const token = document
                                        .querySelector(
                                            'meta[name="csrf-token"]',
                                        )
                                        ?.getAttribute('content');
                                    form.innerHTML = `
                                        <input name="_token" value="${token ?? ''}" />
                                        <input name="format" value="csv" />
                                        <input name="from" value="${filters.from || new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10)}" />
                                        <input name="to" value="${filters.to || new Date().toISOString().slice(0, 10)}" />
                                    `;
                                    document.body.appendChild(form);
                                    form.submit();
                                    form.remove();
                                }}
                            >
                                Export CSV
                            </Button>
                        )}
                    </div>
                </div>

                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const form = new FormData(event.currentTarget);
                        router.get(
                            '/ppe/violations',
                            {
                                violation_type: String(
                                    form.get('violation_type') ?? '',
                                ),
                                camera_id: String(form.get('camera_id') ?? ''),
                                review_status: String(
                                    form.get('review_status') ?? '',
                                ),
                                from: String(form.get('from') ?? ''),
                                to: String(form.get('to') ?? ''),
                                search: String(form.get('search') ?? ''),
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <select
                        name="violation_type"
                        defaultValue={filters.violation_type}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All types</option>
                        {violationTypes.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    <select
                        name="camera_id"
                        defaultValue={filters.camera_id}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All cameras</option>
                        {cameras.map((camera) => (
                            <option key={camera.id} value={camera.id}>
                                {camera.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="review_status"
                        defaultValue={filters.review_status}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        {reviewStatuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                    <input
                        type="date"
                        name="from"
                        defaultValue={filters.from}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    />
                    <input
                        type="date"
                        name="to"
                        defaultValue={filters.to}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    />
                    <Button type="submit" size="sm">
                        Filter
                    </Button>
                </form>

                {canReview && selected.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        <Form
                            action="/ppe/violations/bulk-review"
                            method="post"
                            className="flex gap-2"
                        >
                            {selected.map((id) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name="ids[]"
                                    value={id}
                                />
                            ))}
                            <input
                                type="hidden"
                                name="status"
                                value="confirmed"
                            />
                            <input
                                type="hidden"
                                name="note"
                                value="Bulk confirmed during review"
                            />
                            <Button type="submit" size="sm">
                                Confirm selected ({selected.length})
                            </Button>
                        </Form>
                        <Form
                            action="/ppe/violations/bulk-review"
                            method="post"
                            className="flex gap-2"
                        >
                            {selected.map((id) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name="ids[]"
                                    value={id}
                                />
                            ))}
                            <input
                                type="hidden"
                                name="status"
                                value="false_positive"
                            />
                            <input
                                type="hidden"
                                name="note"
                                value="Bulk false positive during calibration"
                            />
                            <Button type="submit" size="sm" variant="secondary">
                                Mark FP ({selected.length})
                            </Button>
                        </Form>
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                {canReview && <th className="w-10 p-3" />}
                                <th className="p-3">Snapshot</th>
                                <th className="p-3">Type</th>
                                <th className="p-3">Camera</th>
                                <th className="p-3">Detected</th>
                                <th className="p-3">Status</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {violations.data.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-t border-border"
                                >
                                    {canReview && (
                                        <td className="p-3">
                                            <input
                                                type="checkbox"
                                                checked={selected.includes(
                                                    row.id,
                                                )}
                                                onChange={() => toggle(row.id)}
                                            />
                                        </td>
                                    )}
                                    <td className="p-3">
                                        <img
                                            src={row.snapshot_url}
                                            alt=""
                                            className="h-12 w-16 rounded object-cover"
                                        />
                                    </td>
                                    <td className="p-3">
                                        {ViolationTypeLabels[
                                            row.violation_type as keyof typeof ViolationTypeLabels
                                        ] ?? row.violation_type}
                                    </td>
                                    <td className="p-3">{row.camera_ref}</td>
                                    <td className="p-3">
                                        {new Date(
                                            row.detected_at,
                                        ).toLocaleString()}
                                    </td>
                                    <td className="p-3">{row.review_status}</td>
                                    <td className="p-3 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link
                                                href={`/ppe/violations/${row.id}`}
                                            >
                                                Open
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {violations.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canReview ? 7 : 6}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No violations
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                    <Pagination
                        meta={violations.meta}
                        pageUrl="/ppe/violations"
                        params={filters}
                    />
                </div>
            </div>
        </>
    );
}
