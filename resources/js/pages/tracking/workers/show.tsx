import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { Worker } from '@/types/worker';

type Props = {
    worker: Worker;
    canManage: boolean;
};

export default function WorkersShow({ worker, canManage }: Props) {
    return (
        <>
            <Head title={worker.name} />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={worker.name}
                        description={`${worker.contractor} · ${worker.worker_type_label}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/tracking/workers">Back</Link>
                        </Button>
                        {canManage && (
                            <Button asChild>
                                <Link
                                    href={`/tracking/workers/${worker.id}/edit`}
                                >
                                    Edit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <dl className="grid max-w-2xl gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">Badge</dt>
                        <dd>{worker.badge_number ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Employee code</dt>
                        <dd>{worker.employee_code ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Phone</dt>
                        <dd>{worker.phone ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Role</dt>
                        <dd>{worker.role_title ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Presence</dt>
                        <dd>{worker.present ? 'On site' : 'Off site'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Workforce</dt>
                        <dd>{worker.is_active ? 'Active' : 'Inactive'}</dd>
                    </div>
                    <div className="sm:col-span-2">
                        <dt className="text-muted-foreground">Notes</dt>
                        <dd>{worker.notes ?? '—'}</dd>
                    </div>
                </dl>

                {worker.photo_url && (
                    <img
                        src={worker.photo_url}
                        alt=""
                        className="h-40 w-40 rounded-md object-cover"
                    />
                )}

                <p className="text-sm text-muted-foreground">
                    Tag history, portable devices, entry/exit, and incidents
                    will appear here when those modules are online.
                </p>

                {canManage && (
                    <div className="flex flex-wrap gap-2 border-t border-border pt-4">
                        {worker.is_active ? (
                            <>
                                <Form
                                    action={`/tracking/workers/${worker.id}/deactivate`}
                                    method="post"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            disabled={
                                                processing || worker.present
                                            }
                                        >
                                            Deactivate
                                        </Button>
                                    )}
                                </Form>
                                <Form
                                    action={`/tracking/workers/${worker.id}/offboard`}
                                    method="post"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={
                                                processing || worker.present
                                            }
                                        >
                                            Offboard
                                        </Button>
                                    )}
                                </Form>
                            </>
                        ) : (
                            <Form
                                action={`/tracking/workers/${worker.id}/reactivate`}
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Reactivate
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
