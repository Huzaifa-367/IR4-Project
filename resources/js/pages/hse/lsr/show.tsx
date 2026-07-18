import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { LsrViolation } from '@/types/hse';

type Props = {
    violation: LsrViolation;
    canClose: boolean;
};

export default function LsrShow({ violation, canClose }: Props) {
    return (
        <>
            <Head title={`LSR #${violation.id}`} />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={`LSR #${violation.id}`}
                        description={`${violation.category_label} · ${violation.status_label}`}
                    />
                    <Button asChild variant="outline">
                        <Link href="/lsr-violations">Back</Link>
                    </Button>
                </div>

                <section className="space-y-2 rounded-lg border border-border p-4 text-sm">
                    <div>Occurred: {violation.occurred_at}</div>
                    <div>Worker: {violation.worker_label ?? '—'}</div>
                    <div>Zone: {violation.zone_name ?? '—'}</div>
                    <div>Description: {violation.description ?? '—'}</div>
                    <div>
                        Action taken: {violation.action_taken ?? '— (open)'}
                    </div>
                    {violation.ppe_violation_id && (
                        <div>
                            Linked PPE violation #{violation.ppe_violation_id}
                        </div>
                    )}
                    {violation.alert_id && (
                        <div>Linked alert #{violation.alert_id}</div>
                    )}
                </section>

                {canClose && violation.status === 'open' && (
                    <Form
                        action={`/lsr-violations/${violation.id}/close`}
                        method="post"
                        className="space-y-3 rounded-lg border border-border p-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Label htmlFor="action_taken">
                                    Action taken (required to close)
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
                                <Button type="submit" disabled={processing}>
                                    Close LSR
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
