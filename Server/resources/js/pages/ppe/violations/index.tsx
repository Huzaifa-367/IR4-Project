import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { visitFilters } from '@/lib/visit-filters';
import { ViolationTypeLabels } from '@/types/enums';
import type { PaginatedMeta } from '@/types/hardware';
import type { PpeViolation } from '@/types/ppe';

type Props = {
    violations: { data: PpeViolation[]; meta: PaginatedMeta };
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

const ALL = 'all';

const REVIEW_TONE: Record<string, StatusPillTone> = {
    unreviewed: 'warn',
    confirmed: 'crit',
    false_positive: 'neutral',
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
    const [violationType, setViolationType] = useState(
        filters.violation_type || ALL,
    );
    const [cameraId, setCameraId] = useState(filters.camera_id || ALL);
    const [reviewStatus, setReviewStatus] = useState(
        filters.review_status || ALL,
    );
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const toggle = (id: number): void => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const queryParams = {
        violation_type: violationType === ALL ? undefined : violationType,
        camera_id: cameraId === ALL ? undefined : cameraId,
        review_status: reviewStatus === ALL ? undefined : reviewStatus,
        from: from || undefined,
        to: to || undefined,
    };

    function applyFilters(
        overrides?: Partial<{
            violation_type: string;
            camera_id: string;
            review_status: string;
        }>,
    ): void {
        const nextViolationType = overrides?.violation_type ?? violationType;
        const nextCameraId = overrides?.camera_id ?? cameraId;
        const nextReviewStatus = overrides?.review_status ?? reviewStatus;

        visitFilters('/ppe/violations', {
            violation_type:
                nextViolationType === ALL ? undefined : nextViolationType,
            camera_id: nextCameraId === ALL ? undefined : nextCameraId,
            review_status:
                nextReviewStatus === ALL ? undefined : nextReviewStatus,
            from: from || undefined,
            to: to || undefined,
        });
    }

    function exportCsv(): void {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/ppe/violations/export';
        const token = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');
        form.innerHTML = `
            <input name="_token" value="${token ?? ''}" />
            <input name="format" value="csv" />
            <input name="from" value="${from || new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10)}" />
            <input name="to" value="${to || new Date().toISOString().slice(0, 10)}" />
        `;
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    const columns: SettingsColumn<PpeViolation>[] = [
        ...(canReview
            ? [
                  {
                      key: 'select',
                      header: (
                          <Checkbox
                              checked={
                                  violations.data.length > 0 &&
                                  selected.length === violations.data.length
                              }
                              onCheckedChange={() =>
                                  setSelected(
                                      selected.length === violations.data.length
                                          ? []
                                          : violations.data.map((v) => v.id),
                                  )
                              }
                              aria-label="Select all"
                          />
                      ),
                      className: 'w-8',
                      cell: (row: PpeViolation) => (
                          <Checkbox
                              checked={selected.includes(row.id)}
                              onCheckedChange={() => toggle(row.id)}
                              aria-label={`Select violation ${row.id}`}
                          />
                      ),
                  } satisfies SettingsColumn<PpeViolation>,
              ]
            : []),
        {
            key: 'snapshot',
            header: 'Snapshot',
            cell: (row) => (
                <img
                    src={row.snapshot_url}
                    alt=""
                    className="h-12 w-16 rounded-[var(--radius-sm)] object-cover"
                />
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (row) =>
                ViolationTypeLabels[
                    row.violation_type as keyof typeof ViolationTypeLabels
                ] ?? row.violation_type,
        },
        { key: 'camera', header: 'Camera', cell: (row) => row.camera_ref },
        {
            key: 'detected',
            header: 'Detected',
            cell: (row) => new Date(row.detected_at).toLocaleString(),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <StatusPill
                    label={row.review_status.replace('_', ' ')}
                    tone={REVIEW_TONE[row.review_status] ?? 'neutral'}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-20 text-right',
            cell: (row) => (
                <Button asChild size="sm" variant="ghost">
                    <Link href={`/ppe/violations/${row.uuid}`}>Open</Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="PPE Violations" />
            <SettingsPageShell
                title="PPE Violations"
                description={`${violations.meta.total} records`}
                actions={
                    <>
                        <Button asChild variant="secondary" size="sm">
                            <Link href="/ppe">Trends</Link>
                        </Button>
                        {canExport && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={exportCsv}
                            >
                                Export CSV
                            </Button>
                        )}
                    </>
                }
                filters={
                    <>
                        <SearchableSelect
                            value={violationType}
                            onValueChange={(value) => {
                                setViolationType(value);
                                applyFilters({ violation_type: value });
                            }}
                            placeholder="Type"
                            triggerClassName="w-40"
                            options={[
                                { value: ALL, label: 'All types' },
                                ...violationTypes.map((type) => ({
                                    value: type.value,
                                    label: type.label,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={cameraId}
                            onValueChange={(value) => {
                                setCameraId(value);
                                applyFilters({ camera_id: value });
                            }}
                            placeholder="Camera"
                            triggerClassName="w-40"
                            options={[
                                { value: ALL, label: 'All cameras' },
                                ...cameras.map((camera) => ({
                                    value: String(camera.id),
                                    label: camera.name,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={reviewStatus}
                            onValueChange={(value) => {
                                setReviewStatus(value);
                                applyFilters({ review_status: value });
                            }}
                            placeholder="Status"
                            triggerClassName="w-40"
                            options={[
                                { value: ALL, label: 'All statuses' },
                                ...reviewStatuses.map((option) => ({
                                    value: option.value,
                                    label: option.label,
                                })),
                            ]}
                        />
                        <Input
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                            className="w-36"
                            aria-label="From date"
                        />
                        <Input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            className="w-36"
                            aria-label="To date"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => applyFilters()}
                        >
                            Apply
                        </Button>
                    </>
                }
            >
                {canReview && selected.length > 0 && (
                    <div className="mb-3 flex flex-wrap gap-2">
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
                <SettingsDataTable
                    columns={columns}
                    rows={violations.data}
                    rowKey={(row) => row.id}
                    meta={violations.meta}
                    pageUrl="/ppe/violations"
                    queryParams={queryParams}
                    emptyTitle="No violations"
                    emptyDescription="No violations match these filters."
                />
            </SettingsPageShell>
        </>
    );
}
