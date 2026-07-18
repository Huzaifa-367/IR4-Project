import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { WorkerForm } from '@/components/ir4/worker-form';
import { Button } from '@/components/ui/button';

type Props = {
    workerTypes: Array<{ value: string; label: string }>;
};

export default function WorkersCreate({ workerTypes }: Props) {
    return (
        <>
            <Head title="Add worker" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Add worker"
                        description="Create a tracked personnel record."
                    />
                    <Button asChild variant="outline">
                        <Link href="/tracking/workers">Back</Link>
                    </Button>
                </div>
                <WorkerForm
                    action="/tracking/workers"
                    method="post"
                    workerTypes={workerTypes}
                    submitLabel="Create worker"
                />
            </div>
        </>
    );
}
