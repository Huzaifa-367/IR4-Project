import { Form, Head, Link } from '@inertiajs/react';
import { DetailField, FactTile } from '@/components/ir4/fact-tile';
import { Panel } from '@/components/ir4/panel';
import { PpeSnapshotStill } from '@/components/ir4/ppe-snapshot-still';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import hse from '@/routes/hse';
import ppe from '@/routes/ppe';
import { ViolationTypeLabels } from '@/types/enums';
import type { PpeViolation } from '@/types/ppe';

type Props = {
    violation: PpeViolation;
    canReview: boolean;
};

function reviewTone(status: string): StatusPillTone {
    if (status === 'confirmed') {
        return 'crit';
    }

    if (status === 'false_positive') {
        return 'ok';
    }

    return 'warn';
}

function reviewLabel(status: string): string {
    if (status === 'false_positive') {
        return 'False positive';
    }

    if (status === 'confirmed') {
        return 'Confirmed';
    }

    if (status === 'unreviewed') {
        return 'Unreviewed';
    }

    return status;
}

export default function PpeViolationShow({ violation, canReview }: Props) {
    const typeLabel =
        ViolationTypeLabels[
            violation.violation_type as keyof typeof ViolationTypeLabels
        ] ?? violation.violation_type;
    const tone = reviewTone(violation.review_status);
    const heroBar =
        tone === 'crit'
            ? 'bg-[color:var(--crit)]'
            : tone === 'ok'
              ? 'bg-[color:var(--ok)]'
              : 'bg-[color:var(--warn)]';

    return (
        <>
            <Head title={`PPE #${violation.id}`} />
            <div className="mx-auto flex max-w-5xl flex-col gap-4 p-4 md:p-5">
                <header className="overflow-hidden rounded-[var(--radius)] border border-border bg-surface shadow-[var(--shadow-card)]">
                    <div className={cn('h-1.5 w-full', heroBar)} aria-hidden />
                    <div className="flex flex-wrap items-start justify-between gap-4 p-4 md:p-5">
                        <div className="flex min-w-0 flex-1 items-start gap-4">
                            <PpeSnapshotStill
                                url={violation.snapshot_url}
                                alt=""
                                className="size-16 shrink-0 md:size-20"
                            />
                            <div className="min-w-0 space-y-2">
                                <p className="eyebrow">PPE #{violation.id}</p>
                                <h1 className="font-display text-2xl font-semibold tracking-tight text-text md:text-3xl">
                                    {typeLabel}
                                </h1>
                                <div className="flex flex-wrap gap-1.5">
                                    <StatusPill
                                        label={reviewLabel(
                                            violation.review_status,
                                        )}
                                        tone={tone}
                                    />
                                    {violation.camera_ref ? (
                                        <StatusPill
                                            label={violation.camera_ref}
                                            tone="accent"
                                            showDot={false}
                                        />
                                    ) : null}
                                </div>
                                <p className="text-xs text-text-faint tabular-nums">
                                    {new Date(
                                        violation.detected_at,
                                    ).toLocaleString()}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {violation.alert_id ? (
                                violation.violation_type === 'fall' ? (
                                    <Button asChild>
                                        <Link
                                            href={hse.incidents.index.url({
                                                query: {
                                                    alert_id: String(
                                                        violation.alert_id,
                                                    ),
                                                },
                                            })}
                                        >
                                            Create incident
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button asChild variant="secondary">
                                        <Link
                                            href={hse.lsr.index.url({
                                                query: {
                                                    alert_id: String(
                                                        violation.alert_id,
                                                    ),
                                                },
                                            })}
                                        >
                                            Log LSR
                                        </Link>
                                    </Button>
                                )
                            ) : null}
                            <Button asChild variant="outline">
                                <Link href={ppe.violations.index()}>
                                    All violations
                                </Link>
                            </Button>
                        </div>
                    </div>
                </header>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FactTile
                        label="Review"
                        value={reviewLabel(violation.review_status)}
                        tone={tone}
                    />
                    <FactTile
                        label="Confidence"
                        value={
                            violation.confidence !== null
                                ? String(violation.confidence)
                                : '—'
                        }
                        tone="accent"
                    />
                    <FactTile
                        label="People in frame"
                        value={String(violation.worker_count)}
                        tone="neutral"
                    />
                    <FactTile
                        label="Location"
                        value={violation.location_label ?? '—'}
                        tone="neutral"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Snapshot"
                        subtitle="Camera frame at detection"
                    >
                        <PpeSnapshotStill
                            url={violation.snapshot_url}
                            alt="Violation snapshot"
                            className="aspect-video w-full object-contain"
                        />
                    </Panel>

                    <Panel title="Details" subtitle="Detection metadata">
                        <dl className="grid gap-2 text-sm">
                            <DetailField
                                label="Camera"
                                value={
                                    violation.camera_name ??
                                    violation.camera_ref ??
                                    '—'
                                }
                            />
                            <DetailField
                                label="Detected"
                                value={new Date(
                                    violation.detected_at,
                                ).toLocaleString()}
                            />
                            <DetailField
                                label="Alert"
                                value={
                                    violation.alert_id
                                        ? `#${violation.alert_id}`
                                        : '—'
                                }
                            />
                            {violation.reviewed_at ? (
                                <DetailField
                                    label="Reviewed"
                                    value={`${new Date(violation.reviewed_at).toLocaleString()}${violation.reviewed_by_name ? ` · ${violation.reviewed_by_name}` : ''}`}
                                />
                            ) : null}
                            {violation.review_note ? (
                                <DetailField
                                    label="Review note"
                                    value={violation.review_note}
                                />
                            ) : null}
                        </dl>
                    </Panel>
                </div>

                {canReview && violation.review_status === 'unreviewed' ? (
                    <section className="rounded-[var(--radius)] border-l-4 border-[color:var(--warn)] bg-[color:var(--warn-bg)] p-4 shadow-[var(--shadow-card)] md:p-5">
                        <p className="eyebrow text-[color:var(--warn)]">
                            Review required
                        </p>
                        <h2 className="mt-1 font-display text-lg font-semibold text-text">
                            Confirm or dismiss this detection
                        </h2>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Form
                                action={ppe.violations.review.url(
                                    violation.uuid,
                                )}
                                method="post"
                            >
                                <input
                                    type="hidden"
                                    name="status"
                                    value="confirmed"
                                />
                                <input
                                    type="hidden"
                                    name="note"
                                    value="Confirmed as genuine PPE violation"
                                />
                                <Button type="submit" variant="destructive">
                                    Confirm violation
                                </Button>
                            </Form>
                            <Form
                                action={ppe.violations.review.url(
                                    violation.uuid,
                                )}
                                method="post"
                            >
                                <input
                                    type="hidden"
                                    name="status"
                                    value="false_positive"
                                />
                                <input
                                    type="hidden"
                                    name="note"
                                    value="Marked false positive after review"
                                />
                                <Button type="submit" variant="outline">
                                    False positive
                                </Button>
                            </Form>
                        </div>
                    </section>
                ) : null}
            </div>
        </>
    );
}

PpeViolationShow.layout = {
    breadcrumbs: [{ title: 'PPE Violations', href: ppe.violations.index() }],
};
