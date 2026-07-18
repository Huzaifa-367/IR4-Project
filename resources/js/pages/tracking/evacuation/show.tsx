import { Form, Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { Button } from '@/components/ui/button';
import { useReverbChannel } from '@/hooks/use-reverb-channel';

type Entry = {
    id: number;
    worker_id: number;
    worker_name: string | null;
    last_zone: string | null;
    muster_status: string;
    accounted_source: string | null;
};

type Report = {
    id: number;
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
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`Evacuation #${report.id}`}
                        description={`${report.accounted}/${report.total} accounted (${pct}%)`}
                    />
                    <LiveStatusPill status={status} />
                </div>

                <div className="h-2 overflow-hidden rounded bg-muted">
                    <div
                        className="h-full bg-emerald-600"
                        style={{ width: `${pct}%` }}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-border p-4">
                        <h2 className="mb-3 font-medium">
                            Unaccounted ({unaccounted.length})
                        </h2>
                        <ul className="space-y-2 text-sm">
                            {unaccounted.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex items-center justify-between gap-2"
                                >
                                    <span>
                                        {entry.worker_name} ·{' '}
                                        {entry.last_zone ?? '—'}
                                    </span>
                                    {canManage && report.status === 'open' && (
                                        <Form
                                            action={`/tracking/evacuation/${report.id}/entries/${entry.id}`}
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
                        </ul>
                    </div>
                    <div className="rounded-lg border border-border p-4">
                        <h2 className="mb-3 font-medium">
                            Accounted ({accounted.length})
                        </h2>
                        <ul className="space-y-2 text-sm">
                            {accounted.map((entry) => (
                                <li key={entry.id}>
                                    {entry.worker_name} ·{' '}
                                    {entry.accounted_source}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="secondary" size="sm">
                        <a href={`/tracking/evacuation/${report.id}/download`}>
                            Download PDF
                        </a>
                    </Button>
                    {canManage && report.status === 'open' && (
                        <>
                            <Form
                                action={`/tracking/evacuation/${report.id}/close`}
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        Close
                                    </Button>
                                )}
                            </Form>
                            <Form
                                action={`/tracking/evacuation/${report.id}/close`}
                                method="post"
                                className="flex gap-2"
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
                                            placeholder="Force-close note ≥10"
                                            className="h-9 rounded-md border border-input px-2 text-sm"
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
