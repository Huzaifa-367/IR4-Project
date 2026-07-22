import { Form, Head, Link, router } from '@inertiajs/react';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { useReverbChannel } from '@/hooks/use-reverb-channel';

type Entry = {
    id: number;
    uuid: string;
    worker_id: number;
    worker_name: string | null;
    last_zone: string | null;
    muster_status: string;
    accounted_source: string | null;
};

type Report = {
    id: number;
    uuid: string;
    status: string;
    triggered_at: string;
    accounted: number;
    total: number;
    force_closed: boolean;
    entries: Entry[];
};

type Props = {
    report: Report;
    canManage: boolean;
};

export default function EvacuationShow({ report, canManage }: Props) {
    const { status } = useReverbChannel({
        channel: 'tracking',
        events: ['.EvacuationEntryUpdated'],
        onEvent: () => {
            router.reload({ only: ['report'] });
        },
        pollIntervalMs: 15_000,
        snapshotUrl: undefined,
    });

    const unaccounted = report.entries.filter(
        (e) => e.muster_status === 'unaccounted',
    );
    const accounted = report.entries.filter(
        (e) => e.muster_status === 'accounted',
    );
    const pct =
        report.total === 0
            ? 100
            : Math.round((report.accounted / report.total) * 100);

    return (
        <>
            <Head title={`Evacuation #${report.id}`} />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">
                            Triggered{' '}
                            {new Date(report.triggered_at).toLocaleString()}
                        </p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            Evacuation #{report.id}
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            {report.accounted}/{report.total} accounted (
                            {pct}%)
                        </p>
                    </div>
                    <LiveStatusPill status={status} />
                </div>

                <div className="h-2 overflow-hidden rounded-pill bg-surface-3">
                    <div
                        className="h-full rounded-pill bg-[color:var(--ok)]"
                        style={{ width: `${pct}%` }}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Panel title={`Unaccounted (${unaccounted.length})`}>
                        <ul className="flex flex-col gap-2 text-sm">
                            {unaccounted.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                                >
                                    <Link
                                        href={`/workforce/workers/${entry.worker_id}`}
                                        className="text-text hover:text-[color:var(--accent)] hover:underline"
                                    >
                                        {entry.worker_name}
                                        <span className="ml-1.5 text-xs text-text-faint">
                                            {entry.last_zone ?? '—'}
                                        </span>
                                    </Link>
                                    {canManage && report.status === 'open' && (
                                        <Form
                                            action={`/tracking/evacuation/${report.uuid}/entries/${entry.uuid}`}
                                            method="post"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Account
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </li>
                            ))}
                            {unaccounted.length === 0 && (
                                <li className="text-text-faint">
                                    Everyone is accounted for.
                                </li>
                            )}
                        </ul>
                    </Panel>
                    <Panel title={`Accounted (${accounted.length})`}>
                        <ul className="flex flex-col gap-2 text-sm">
                            {accounted.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                                >
                                    <Link
                                        href={`/workforce/workers/${entry.worker_id}`}
                                        className="text-text hover:text-[color:var(--accent)] hover:underline"
                                    >
                                        {entry.worker_name}
                                    </Link>
                                    <StatusPill
                                        label={entry.accounted_source ?? '—'}
                                        tone="ok"
                                        showDot={false}
                                    />
                                </li>
                            ))}
                            {accounted.length === 0 && (
                                <li className="text-text-faint">
                                    No one accounted for yet.
                                </li>
                            )}
                        </ul>
                    </Panel>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="secondary" size="sm">
                        <a href={`/tracking/evacuation/${report.uuid}/download`}>
                            Download PDF
                        </a>
                    </Button>
                    {canManage && report.status === 'open' && (
                        <>
                            <Form
                                action={`/tracking/evacuation/${report.uuid}/close`}
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={
                                            processing ||
                                            unaccounted.length > 0
                                        }
                                        title={
                                            unaccounted.length > 0
                                                ? 'Account all workers first, or use Force close with a note'
                                                : undefined
                                        }
                                    >
                                        Close
                                    </Button>
                                )}
                            </Form>
                            <Form
                                action={`/tracking/evacuation/${report.uuid}/close`}
                                method="post"
                                className="flex flex-wrap items-center gap-2"
                            >
                                {({ processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="force"
                                            value="1"
                                        />
                                        <input
                                            name="note"
                                            required
                                            minLength={10}
                                            placeholder="Force-close note ≥10 chars"
                                            className="h-9 min-w-[14rem] flex-1 rounded-md border border-input px-2 text-sm"
                                            aria-label="Force-close note"
                                        />
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            Force close
                                        </Button>
                                    </>
                                )}
                            </Form>
                            {unaccounted.length > 0 ? (
                                <p className="basis-full text-sm text-[color:var(--warn)]">
                                    {unaccounted.length} worker
                                    {unaccounted.length === 1 ? '' : 's'} still
                                    unaccounted — use Force close with a note, or
                                    account them first.
                                </p>
                            ) : null}
                        </>
                    )}
                    <Button asChild variant="outline" size="sm">
                        <Link href="/tracking/evacuation">Back</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

EvacuationShow.layout = {
    breadcrumbs: [{ title: 'Evacuation', href: '/tracking/evacuation' }],
};
