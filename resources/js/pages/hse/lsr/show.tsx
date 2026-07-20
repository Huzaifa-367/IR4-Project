import { Form, Head, Link } from '@inertiajs/react';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { LsrViolation } from '@/types/hse';

type Props = {
    violation: LsrViolation;
    canClose: boolean;
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function LsrShow({ violation, canClose }: Props) {
    return (
        <>
            <Head title={`LSR #${violation.id}`} />
            <div className="mx-auto flex max-w-3xl flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">{violation.category_label}</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            LSR #{violation.id}
                        </h1>
                        <div className="mt-2">
                            <StatusPill
                                label={violation.status_label}
                                tone={
                                    violation.status === 'closed'
                                        ? 'ok'
                                        : 'warn'
                                }
                            />
                        </div>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/lsr-violations">Back</Link>
                    </Button>
                </div>

                <Panel title="Details">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <Field
                            label="Occurred"
                            value={formatDate(violation.occurred_at)}
                        />
                        {violation.worker_id ? (
                            <div>
                                <dt className="text-xs text-text-faint">
                                    Worker
                                </dt>
                                <dd className="mt-0.5">
                                    <Link
                                        href={`/workforce/workers/${violation.worker_id}`}
                                        className="text-[color:var(--accent)] hover:underline"
                                    >
                                        {violation.worker_label}
                                    </Link>
                                </dd>
                            </div>
                        ) : (
                            <Field label="Worker" value={violation.worker_label} />
                        )}
                        <Field label="Zone" value={violation.zone_name} />
                        {violation.alert_id ? (
                            <div>
                                <dt className="text-xs text-text-faint">
                                    Source alert
                                </dt>
                                <dd className="mt-0.5">
                                    <Link
                                        href="/alerts"
                                        className="text-[color:var(--accent)] hover:underline"
                                    >
                                        Alert #{violation.alert_id}
                                    </Link>
                                </dd>
                            </div>
                        ) : null}
                        {violation.ppe_violation_id ? (
                            <div>
                                <dt className="text-xs text-text-faint">
                                    Linked PPE violation
                                </dt>
                                <dd className="mt-0.5">
                                    <Link
                                        href={`/ppe/violations/${violation.ppe_violation_id}`}
                                        className="text-[color:var(--accent)] hover:underline"
                                    >
                                        PPE #{violation.ppe_violation_id}
                                    </Link>
                                </dd>
                            </div>
                        ) : null}
                        <div className="sm:col-span-2">
                            <Field
                                label="Description"
                                value={violation.description}
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <Field
                                label="Action taken"
                                value={violation.action_taken}
                            />
                        </div>
                        <Field
                            label="Logged by"
                            value={violation.logged_by_name}
                        />
                        {violation.closed_at ? (
                            <Field
                                label="Closed"
                                value={`${formatDate(violation.closed_at)}${violation.closed_by_name ? ` · ${violation.closed_by_name}` : ''}`}
                            />
                        ) : null}
                    </dl>
                </Panel>

                {canClose && violation.status === 'open' && (
                    <Panel title="Close this LSR">
                        <Form
                            action={`/lsr-violations/${violation.id}/close`}
                            method="post"
                            className="flex flex-col gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
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
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="self-start"
                                    >
                                        Close LSR
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Panel>
                )}
            </div>
        </>
    );
}

function Field({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div>
            <dt className="text-xs text-text-faint">{label}</dt>
            <dd className="mt-0.5 text-text">{value ?? '—'}</dd>
        </div>
    );
}

LsrShow.layout = {
    breadcrumbs: [{ title: 'LSR', href: '/lsr-violations' }],
};
