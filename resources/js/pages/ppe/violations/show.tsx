import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { ViolationTypeLabels } from '@/types/enums';
import type { PpeViolation } from '@/types/ppe';

type Props = {
    violation: PpeViolation;
    canReview: boolean;
};

export default function PpeViolationShow({ violation, canReview }: Props) {
    return (
        <>
            <Head title={`PPE #${violation.id}`} />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={
                            ViolationTypeLabels[
                                violation.violation_type as keyof typeof ViolationTypeLabels
                            ] ?? violation.violation_type
                        }
                        description={`Camera ${violation.camera_ref ?? '—'} · ${new Date(violation.detected_at).toLocaleString()}`}
                    />
                    <Button asChild variant="secondary" size="sm">
                        <Link href="/ppe/violations">Back</Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <img
                        src={violation.snapshot_url}
                        alt="Violation snapshot"
                        className="w-full rounded-lg border border-border object-contain"
                    />
                    <dl className="space-y-3 text-sm">
                        <div>
                            <dt className="text-muted-foreground">
                                Review status
                            </dt>
                            <dd className="font-medium">
                                {violation.review_status}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Confidence
                            </dt>
                            <dd>{violation.confidence ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                People in frame
                            </dt>
                            <dd>{violation.worker_count}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Location</dt>
                            <dd>{violation.location_label ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Alert</dt>
                            <dd>{violation.alert_id ?? '—'}</dd>
                        </div>
                        {violation.review_note && (
                            <div>
                                <dt className="text-muted-foreground">
                                    Review note
                                </dt>
                                <dd>{violation.review_note}</dd>
                            </div>
                        )}
                    </dl>
                </div>

                {canReview && violation.review_status === 'unreviewed' && (
                    <div className="flex flex-wrap gap-3">
                        <Form
                            action={`/ppe/violations/${violation.id}/review`}
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
                            <Button type="submit">Confirm</Button>
                        </Form>
                        <Form
                            action={`/ppe/violations/${violation.id}/review`}
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
                            <Button type="submit" variant="secondary">
                                False positive
                            </Button>
                        </Form>
                    </div>
                )}
            </div>
        </>
    );
}
