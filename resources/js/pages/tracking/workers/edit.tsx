import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { WorkerForm } from '@/components/ir4/worker-form';
import { Button } from '@/components/ui/button';

type Props = {
    worker: {
        id: number;
        name: string;
        contractor: string;
        worker_type: string;
        role_title: string | null;
        badge_number: string | null;
        employee_code: string | null;
        phone: string | null;
        notes: string | null;
    };
    workerTypes: Array<{ value: string; label: string }>;
};

export default function WorkersEdit({ worker, workerTypes }: Props) {
    return (
        <>
            <Head title={`Edit ${worker.name}`} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading title="Edit worker" description={worker.name} />
                    <Button asChild variant="outline">
                        <Link href={`/tracking/workers/${worker.id}`}>
                            Cancel
                        </Link>
                    </Button>
                </div>
                <WorkerForm
                    action={`/tracking/workers/${worker.id}`}
                    method="put"
                    workerTypes={workerTypes}
                    defaults={worker}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}
