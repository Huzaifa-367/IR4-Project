import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PpeSnapshotStill } from '@/components/ir4/ppe-snapshot-still';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useLivePartialReload } from '@/hooks/use-live-partial-reload';
import { visitFilters } from '@/lib/visit-filters';
import roiViolations from '@/routes/roi-violations';
import { RoiViolationTypeLabels } from '@/types/enums';
import type { PaginatedMeta } from '@/types/hardware';
import type { RoiViolation } from '@/types/roi-violation';

type Props = {
    violations: { data: RoiViolation[]; meta: PaginatedMeta };
    filters: {
        event_type: string;
        camera_id: string;
        review_status: string;
        from: string;
        to: string;
        search: string;
    };
    cameras: Array<{ id: number; name: string; reference: string }>;
    eventTypes: Array<{ value: string; label: string }>;
    reviewStatuses: Array<{ value: string; label: string }>;
    canReview: boolean;
};

const ALL = 'all';

const REVIEW_TONE: Record<string, StatusPillTone> = {
    unreviewed: 'warn',
    confirmed: 'crit',
    false_positive: 'neutral',
};

export default function RoiViolationsIndex({
    violations,
    filters,
    cameras,
    eventTypes,
    reviewStatuses,
    canReview,
}: Props) {
    useLivePartialReload({
        channel: 'roi',
        events: ['.RoiViolationDetected'],
        only: ['violations'],
        throttleMs: 2000,
    });

    const [selected, setSelected] = useState<number[]>([]);
    const [eventType, setEventType] = useState(filters.event_type || ALL);
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
        event_type: eventType === ALL ? undefined : eventType,
        camera_id: cameraId === ALL ? undefined : cameraId,
        review_status: reviewStatus === ALL ? undefined : reviewStatus,
        from: from || undefined,
        to: to || undefined,
    };

    function applyFilters(
        overrides?: Partial<{
            event_type: string;
            camera_id: string;
            review_status: string;
        }>,
    ): void {
        const nextEventType = overrides?.event_type ?? eventType;
        const nextCameraId = overrides?.camera_id ?? cameraId;
        const nextReviewStatus = overrides?.review_status ?? reviewStatus;

        visitFilters(roiViolations.index.url(), {
            event_type: nextEventType === ALL ? undefined : nextEventType,
            camera_id: nextCameraId === ALL ? undefined : nextCameraId,
            review_status:
                nextReviewStatus === ALL ? undefined : nextReviewStatus,
            from: from || undefined,
            to: to || undefined,
        });
    }

    const columns: SettingsColumn<RoiViolation>[] = [
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
                      cell: (row: RoiViolation) => (
                          <Checkbox
                              checked={selected.includes(row.id)}
                              onCheckedChange={() => toggle(row.id)}
                              aria-label={`Select violation ${row.id}`}
                          />
                      ),
                  } satisfies SettingsColumn<RoiViolation>,
              ]
            : []),
        {
            key: 'number',
            header: 'Number',
            className: 'w-28',
            cell: (row) => (
                <Link
                    href={roiViolations.show.url(row.uuid)}
                    className="font-mono text-xs hover:underline"
                >
                    ROI #{row.id}
                </Link>
            ),
        },
        {
            key: 'snapshot',
            header: 'Snapshot',
            cell: (row) => (
                <PpeSnapshotStill
                    url={row.snapshot_url}
                    className="h-12 w-16"
                />
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (row) => (
                <Link
                    href={roiViolations.show.url(row.uuid)}
                    className="font-medium text-text hover:underline"
                >
                    {RoiViolationTypeLabels[row.event_type] ?? row.event_type}
                </Link>
            ),
        },
        {
            key: 'roi',
            header: 'ROI',
            cell: (row) => row.roi_name ?? row.roi_reference,
        },
        {
            key: 'camera',
            header: 'Camera',
            cell: (row) => row.camera_ref,
        },
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
                    <Link href={roiViolations.show.url(row.uuid)}>Open</Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="ROI Violations" />
            <SettingsPageShell
                eyebrow="Safety"
                title="ROI Violations"
                description={`${violations.meta.total} records`}
                filters={
                    <>
                        <SearchableSelect
                            value={eventType}
                            onValueChange={(value) => {
                                setEventType(value);
                                applyFilters({ event_type: value });
                            }}
                            placeholder="Type"
                            triggerClassName="w-40"
                            options={[
                                { value: ALL, label: 'All types' },
                                ...eventTypes.map((type) => ({
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
                            triggerClassName="w-44"
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
                            onBlur={() => applyFilters()}
                            className="w-36"
                        />
                        <Input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            onBlur={() => applyFilters()}
                            className="w-36"
                        />
                    </>
                }
            >
                {canReview && selected.length > 0 && (
                    <div className="mb-3 flex flex-wrap gap-2">
                        <Form
                            action={roiViolations.bulkReview.url()}
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
                            action={roiViolations.bulkReview.url()}
                            method="post"
                            className="flex gap-2"
                        >
                            {selected.map((id) => (
                                <input
                                    key={`fp-${id}`}
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
                <SettingsDataTable<RoiViolation>
                    columns={columns}
                    rows={violations.data}
                    rowKey={(row) => row.id}
                    meta={violations.meta}
                    pageUrl={roiViolations.index.url()}
                    queryParams={queryParams}
                    emptyTitle="No ROI violations"
                    emptyDescription="No violations match these filters."
                />
            </SettingsPageShell>
        </>
    );
}

RoiViolationsIndex.layout = {
    breadcrumbs: [{ title: 'ROI Violations', href: '/roi-violations' }],
};
