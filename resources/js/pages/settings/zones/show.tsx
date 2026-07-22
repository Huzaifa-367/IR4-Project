import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { GeoZonePicker } from '@/components/ir4/geo-zone-map';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import { WorkerPicker } from '@/components/ir4/worker-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Worker } from '@/types/worker';

const DEFAULT_RADIUS_METERS = 60;

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
        latitude: string | null;
        longitude: string | null;
        radius_meters: string | null;
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
    const [latitude, setLatitude] = useState<number | null>(
        zone.latitude ? Number(zone.latitude) : null,
    );
    const [longitude, setLongitude] = useState<number | null>(
        zone.longitude ? Number(zone.longitude) : null,
    );
    const [radiusMeters, setRadiusMeters] = useState(
        zone.radius_meters ? Number(zone.radius_meters) : DEFAULT_RADIUS_METERS,
    );
    const [savingLocation, setSavingLocation] = useState(false);

    function saveAccessList(): void {
        setSavingAccessList(true);
        router.put(
            `/settings/zones/${zone.uuid}/access-list`,
            { worker_ids: accessListIds },
            {
                preserveScroll: true,
                onFinish: () => setSavingAccessList(false),
            },
        );
    }

    function saveLocation(): void {
        setSavingLocation(true);
        router.patch(
            `/settings/zones/${zone.uuid}/map-position`,
            { latitude, longitude, radius_meters: radiusMeters },
            { preserveScroll: true, onFinish: () => setSavingLocation(false) },
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
                            <Link href="/settings/zones">Back</Link>
                        </Button>
                        {zone.is_active && (
                            <Form
                                action={`/settings/zones/${zone.uuid}/deactivate`}
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

                <div className="grid gap-4 xl:grid-cols-12">
                    <Panel
                        title="Currently bound readers"
                        className="xl:col-span-6"
                        action={
                            <Link
                                href="/settings/repositioning"
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
                                    None — use Repositioning to bind.
                                </li>
                            )}
                        </ul>
                    </Panel>

                    <Panel
                        title="Map position"
                        subtitle="Offline Gulf-region basemap — click to reposition"
                        className="xl:col-span-6"
                    >
                        <div className="flex flex-col gap-3">
                            <GeoZonePicker
                                latitude={latitude}
                                longitude={longitude}
                                radiusMeters={radiusMeters}
                                color={zone.color ?? undefined}
                                onChange={(lat, lng) => {
                                    setLatitude(lat);
                                    setLongitude(lng);
                                }}
                            />
                            <div className="flex items-center gap-2">
                                <Label htmlFor="zone-radius" className="w-24">
                                    Radius (m)
                                </Label>
                                <Input
                                    id="zone-radius"
                                    type="number"
                                    min={1}
                                    value={radiusMeters}
                                    onChange={(event) =>
                                        setRadiusMeters(
                                            Number(event.target.value) ||
                                                DEFAULT_RADIUS_METERS,
                                        )
                                    }
                                    className="w-28"
                                />
                            </div>
                            <Button
                                type="button"
                                disabled={
                                    savingLocation ||
                                    latitude === null ||
                                    longitude === null
                                }
                                onClick={saveLocation}
                                className="self-start"
                            >
                                Save map position
                            </Button>
                        </div>
                    </Panel>
                </div>

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
    breadcrumbs: [{ title: 'Zones', href: '/settings/zones' }],
};
