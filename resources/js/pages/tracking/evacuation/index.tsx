import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

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
    entries: Entry[];
};

type Props = {
    openReport: Report | null;
    history: Array<{ id: number; status: string; triggered_at: string }>;
    canTrigger: boolean;
    canManage: boolean;
};

export default function EvacuationIndex({
    openReport,
    history,
    canTrigger,
    canManage,
}: Props) {
    return (
        <>
            <Head title="Evacuation" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Evacuation"
                    description="Emergency roster and muster accounting"
                />

                {canTrigger && !openReport && (
                    <Form action="/tracking/evacuation" method="post">
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Trigger evacuation
                            </Button>
                        )}
                    </Form>
                )}

                {openReport && (
                    <div className="rounded-lg border border-red-600/40 bg-red-50 p-4">
                        <p className="font-medium">
                            Open report #{openReport.id}: {openReport.accounted}
                            /{openReport.total} accounted
                        </p>
                        <Button asChild className="mt-2" size="sm">
                            <Link
                                href={`/tracking/evacuation/${openReport.id}`}
                            >
                                Open board
                            </Link>
                        </Button>
                    </div>
                )}

                <div>
                    <h2 className="mb-2 text-sm font-medium">Recent reports</h2>
                    <ul className="space-y-1 text-sm">
                        {history.map((report) => (
                            <li key={report.id}>
                                <Link
                                    href={`/tracking/evacuation/${report.id}`}
                                    className="underline"
                                >
                                    #{report.id} · {report.status} ·{' '}
                                    {report.triggered_at}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>

                {canManage && null}
            </div>
        </>
    );
}
