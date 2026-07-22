import { Form, Head, Link } from '@inertiajs/react';
import { DetailField, FactTile } from '@/components/ir4/fact-tile';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { LsrViolation } from '@/types/hse';

type Props = {
    violation: LsrViolation;
    canClose: boolean;
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function LsrShow({ violation, canClose }: Props) {
    const isOpen = violation.status === 'open';
    const heroTone = isOpen
        ? 'bg-[color:var(--warn)]'
        : 'bg-[color:var(--ok)]';

    return (
        <>
            <Head title={`LSR #${violation.id}`} />
            <div className="mx-auto flex max-w-5xl flex-col gap-4 p-4 md:p-5">
                <header className="overflow-hidden rounded-[var(--radius)] border border-border bg-surface shadow-[var(--shadow-card)]">
                    <div className={cn('h-1.5 w-full', heroTone)} aria-hidden />
                    <div className="flex flex-wrap items-start justify-between gap-4 p-4 md:p-5">
                        <div className="min-w-0 space-y-2">
                            <span className="inline-flex items-center rounded-pill bg-[color:var(--warn-bg)] px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase text-[color:var(--warn)]">
                                {violation.category_label}
                            </span>
                            <h1 className="font-display text-2xl font-semibold tracking-tight text-text md:text-3xl">
                                LSR #{violation.id}
                            </h1>
                            <StatusPill
                                label={violation.status_label}
                                tone={isOpen ? 'warn' : 'ok'}
                            />
                        </div>
                        <Button asChild variant="outline">
                            <Link href="/lsr-violations">All LSR</Link>
                        </Button>
                    </div>
                </header>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FactTile
                        label="Occurred"
                        value={formatDate(violation.occurred_at)}
                        tone="accent"
                    />
                    <FactTile
                        label="Worker"
                        value={
                            violation.worker_id ? (
                                <Link
                                    href={`/workforce/workers/${violation.worker_id}`}
                                    className="text-[color:var(--accent)] hover:underline"
                                >
                                    {violation.worker_label ?? '—'}
                                </Link>
                            ) : (
                                (violation.worker_label ?? '—')
                            )
                        }
                        tone="neutral"
                    />
                    <FactTile
                        label="Zone"
                        value={violation.zone_name ?? '—'}
                        tone="neutral"
                    />
                    <FactTile
                        label="Status"
                        value={violation.status_label}
                        tone={isOpen ? 'warn' : 'ok'}
                    />
                </div>

                <Panel title="Details" subtitle="Record and linkages">
                    <dl className="grid gap-2 text-sm sm:grid-cols-2">
                        {violation.alert_id ? (
                            <DetailField
                                label="Source alert"
                                value={
                                    <Link
                                        href="/alerts"
                                        className="text-[color:var(--accent)] hover:underline"
                                    >
                                        Alert #{violation.alert_id}
                                    </Link>
                                }
                            />
                        ) : null}
                        {violation.ppe_violation_id ? (
                            <DetailField
                                label="Linked PPE"
                                value={
                                    <Link
                                        href={`/ppe/violations/${violation.ppe_violation_id}`}
                                        className="text-[color:var(--accent)] hover:underline"
                                    >
                                        PPE #{violation.ppe_violation_id}
                                    </Link>
                                }
                            />
                        ) : null}
                        <div className="sm:col-span-2">
                            <DetailField
                                label="Description"
                                value={violation.description}
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <DetailField
                                label="Action taken"
                                value={violation.action_taken}
                            />
                        </div>
                        <DetailField
                            label="Logged by"
                            value={violation.logged_by_name}
                        />
                        {violation.closed_at ? (
                            <DetailField
                                label="Closed"
                                value={`${formatDate(violation.closed_at)}${violation.closed_by_name ? ` · ${violation.closed_by_name}` : ''}`}
                            />
                        ) : null}
                    </dl>
                </Panel>

                {canClose && isOpen ? (
                    <section className="rounded-[var(--radius)] border-l-4 border-[color:var(--warn)] bg-[color:var(--warn-bg)] p-4 shadow-[var(--shadow-card)] md:p-5">
                        <p className="eyebrow text-[color:var(--warn)]">
                            Close LSR
                        </p>
                        <h2 className="mt-1 font-display text-lg font-semibold text-text">
                            Record action taken
                        </h2>
                        <Form
                            action={`/lsr-violations/${violation.uuid}/close`}
                            method="post"
                            className="mt-3 flex flex-col gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="action_taken">
                                            Action taken (required)
                                        </Label>
                                        <Input
                                            id="action_taken"
                                            name="action_taken"
                                            required
                                            minLength={10}
                                            className="bg-surface"
                                        />
                                        {errors.action_taken ? (
                                            <p className="text-sm text-destructive">
                                                {errors.action_taken}
                                            </p>
                                        ) : null}
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
                    </section>
                ) : null}
            </div>
        </>
    );
}

LsrShow.layout = {
    breadcrumbs: [{ title: 'LSR', href: '/lsr-violations' }],
};
