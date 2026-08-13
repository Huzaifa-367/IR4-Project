import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import { WorkerPicker } from '@/components/ir4/worker-picker';
import { Button } from '@/components/ui/button';
import settings from '@/routes/settings';
import tracking from '@/routes/tracking';
import type { Worker } from '@/types/worker';

type Props = {
    zone: {
        id: number;
        uuid: string;
        name: string;
        zone_type: string;
        zone_type_label: string;
        requires_authorization: boolean;
        requires_permit: boolean;
        occupancy_limit: number | null;
        is_active: boolean;
        color: string | null;
        current_readers: Array<{
            binding_id: number;
            device_id: number;
            name: string | null;
            reference: string | null;
            bound_from: string | null;
        }>;
        access_list: Worker[];
    };
    zoneTypes: Array<{ value: string; label: string }>;
    workers: Array<{ id: number; name: string }>;
};

export default function ZoneShow({ zone, workers }: Props) {
    const [accessListIds, setAccessListIds] = useState<number[]>(
        zone.access_list.map((worker) => worker.id),
    );
    const [savingAccessList, setSavingAccessList] = useState(false);

    function saveAccessList(): void {
        setSavingAccessList(true);
        router.put(
            settings.zones.accessList.url(zone.uuid),
            { worker_ids: accessListIds },
            {
                preserveScroll: true,
                onFinish: () => setSavingAccessList(false),
            },
        );
    }

    return (
        <>
            <Head title={zone.name} />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">
                            {zone.zone_type_label}
                            {zone.requires_authorization
                                ? ' · auth required'
                                : ''}
                            {zone.requires_permit ? ' · PTW required' : ''}
                        </p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            {zone.name}
                        </h1>
                        <div className="mt-2">
                            <StatusPill
                                label={zone.is_active ? 'Active' : 'Inactive'}
                                tone={zone.is_active ? 'ok' : 'neutral'}
                            />
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={settings.zones.index()}>Back</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={tracking.index()}>Zone readings</Link>
                        </Button>
                        {zone.is_active && (
                            <Form
                                action={settings.zones.deactivate.url(
                                    zone.uuid,
                                )}
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Deactivate
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </div>

                <Panel
                    title="Bound RFID readers"
                    subtitle="Tag reads from these readers resolve to this zone while the binding is open."
                    action={
                        <Link
                            href={settings.repositioning()}
                            className="text-xs text-[color:var(--accent)] hover:underline"
                        >
                            Repositioning ›
                        </Link>
                    }
                >
                    <ul className="flex flex-col gap-2 text-sm">
                        {zone.current_readers.map((reader) => (
                            <li
                                key={reader.binding_id}
                                className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                            >
                                <span className="text-text">
                                    {reader.name}{' '}
                                    <span className="font-mono text-xs text-text-faint">
                                        {reader.reference}
                                    </span>
                                </span>
                                <span className="text-xs text-text-faint">
                                    since{' '}
                                    {reader.bound_from
                                        ? new Date(
                                              reader.bound_from,
                                          ).toLocaleDateString()
                                        : '—'}
                                </span>
                            </li>
                        ))}
                        {zone.current_readers.length === 0 && (
                            <li className="text-text-faint">
                                None — use Repositioning to bind a reader.
                            </li>
                        )}
                    </ul>
                </Panel>

                <Panel
                    title="Access list"
                    subtitle="Workers authorized to enter without triggering an unauthorized-zone alert."
                >
                    <WorkerPicker
                        workers={workers}
                        value={accessListIds}
                        onChange={setAccessListIds}
                    />
                    <Button
                        type="button"
                        className="mt-3"
                        disabled={savingAccessList}
                        onClick={saveAccessList}
                    >
                        Update access list
                    </Button>
                </Panel>
            </div>
        </>
    );
}

ZoneShow.layout = {
    breadcrumbs: [{ title: 'Zones', href: settings.zones.index() }],
};
