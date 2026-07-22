import { Head, Link } from '@inertiajs/react';
import { Siren } from 'lucide-react';
import { useState } from 'react';
import { Panel } from '@/components/ir4/panel';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';

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
    entries: Entry[];
};

type Props = {
    openReport: Report | null;
    history: Array<{
        id: number;
        uuid: string;
        status: string;
        triggered_at: string;
    }>;
    canTrigger: boolean;
    canManage: boolean;
};

export default function EvacuationIndex({
    openReport,
    history,
    canTrigger,
}: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    return (
        <>
            <Head title="Evacuation" />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">Emergency roster</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            Evacuation
                        </h1>
                        <p className="mt-1 text-sm text-text-dim">
                            Muster accounting for the whole site
                        </p>
                    </div>
                    {canTrigger && !openReport && (
                        <Button
                            type="button"
                            className="bg-[color:var(--crit)] text-white hover:bg-[color:var(--crit)]/90"
                            onClick={() => setConfirmOpen(true)}
                        >
                            <Siren className="size-4" />
                            Trigger evacuation
                        </Button>
                    )}
                </div>

                {openReport && (
                    <Panel
                        title={`Open report #${openReport.id}`}
                        subtitle={`${openReport.accounted}/${openReport.total} accounted`}
                        className="border-[color:var(--crit)]/40"
                        action={<StatusPill label="Open" tone="crit" />}
                    >
                        <Button asChild size="sm">
                            <Link
                                href={`/tracking/evacuation/${openReport.uuid}`}
                            >
                                Open board
                            </Link>
                        </Button>
                    </Panel>
                )}

                <Panel title="Recent reports">
                    <ul className="flex flex-col gap-2 text-sm">
                        {history.map((report) => (
                            <li
                                key={report.id}
                                className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                            >
                                <Link
                                    href={`/tracking/evacuation/${report.uuid}`}
                                    className="text-[color:var(--accent)] hover:underline"
                                >
                                    Evacuation #{report.id}
                                </Link>
                                <span className="flex items-center gap-2 text-xs text-text-faint">
                                    {new Date(
                                        report.triggered_at,
                                    ).toLocaleString()}
                                    <StatusPill
                                        label={
                                            report.status === 'closed'
                                                ? 'Closed'
                                                : 'Open'
                                        }
                                        tone={
                                            report.status === 'closed'
                                                ? 'neutral'
                                                : 'crit'
                                        }
                                    />
                                </span>
                            </li>
                        ))}
                        {history.length === 0 && (
                            <li className="text-text-faint">
                                No evacuation reports yet.
                            </li>
                        )}
                    </ul>
                </Panel>
            </div>

            <ConfirmActionDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title="Trigger site evacuation"
                description="This freezes every on-site worker into a live muster report and hard-navigates every operator screen to the evacuation board. This cannot be undone."
                action="/tracking/evacuation"
                method="post"
                confirmLabel="Trigger evacuation"
                destructive
            />
        </>
    );
}

EvacuationIndex.layout = {
    breadcrumbs: [{ title: 'Evacuation', href: '/tracking/evacuation' }],
};
