import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Worker } from '@/types/worker';

type Props = {
    zone: {
        id: number;
        name: string;
        zone_type: string;
        zone_type_label: string;
        requires_authorization: boolean;
        occupancy_limit: number | null;
        is_active: boolean;
        map_x: string | null;
        map_y: string | null;
        map_radius: string | null;
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
};

export default function ZoneShow({ zone }: Props) {
    return (
        <>
            <Head title={zone.name} />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={zone.name}
                        description={`${zone.zone_type_label}${zone.requires_authorization ? ' · auth required' : ''}`}
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href="/settings/zones">Back</Link>
                        </Button>
                        {zone.is_active && (
                            <Form
                                action={`/settings/zones/${zone.id}/deactivate`}
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

                <section className="space-y-2">
                    <h2 className="font-medium">Currently bound readers</h2>
                    <ul className="space-y-1 text-sm">
                        {zone.current_readers.map((reader) => (
                            <li key={reader.binding_id}>
                                {reader.name} ({reader.reference}) · since{' '}
                                {reader.bound_from}
                            </li>
                        ))}
                        {zone.current_readers.length === 0 && (
                            <li className="text-muted-foreground">
                                None — use Repositioning to bind.
                            </li>
                        )}
                    </ul>
                </section>

                <section className="max-w-xl space-y-3 rounded-lg border border-border p-4">
                    <h2 className="font-medium">Map position</h2>
                    <Form
                        action={`/settings/zones/${zone.id}/map-position`}
                        method="patch"
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        {({ processing }) => (
                            <>
                                <div className="grid gap-1">
                                    <Label htmlFor="map_x">X</Label>
                                    <Input
                                        id="map_x"
                                        name="map_x"
                                        defaultValue={zone.map_x ?? ''}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="map_y">Y</Label>
                                    <Input
                                        id="map_y"
                                        name="map_y"
                                        defaultValue={zone.map_y ?? ''}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="map_radius">Radius</Label>
                                    <Input
                                        id="map_radius"
                                        name="map_radius"
                                        defaultValue={zone.map_radius ?? ''}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="color">Color</Label>
                                    <Input
                                        id="color"
                                        name="color"
                                        defaultValue={zone.color ?? ''}
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <Button type="submit" disabled={processing}>
                                        Save map position
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                <section className="max-w-xl space-y-3 rounded-lg border border-border p-4">
                    <h2 className="font-medium">Access list</h2>
                    <p className="text-sm text-muted-foreground">
                        Comma-separated worker ids (full picker lands with
                        DOC-14). Current:{' '}
                        {zone.access_list.map((w) => w.name).join(', ') ||
                            'none'}
                    </p>
                    <Form
                        action={`/settings/zones/${zone.id}/access-list`}
                        method="put"
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Input
                                    name="worker_ids_csv"
                                    id="worker_ids_csv"
                                    placeholder="1, 2, 3"
                                    defaultValue={zone.access_list
                                        .map((w) => w.id)
                                        .join(', ')}
                                    onChange={(event) => {
                                        const form = event.currentTarget.form;

                                        if (!form) {
                                            return;
                                        }

                                        form.querySelectorAll(
                                            'input[name="worker_ids[]"]',
                                        ).forEach((el) => el.remove());
                                        event.currentTarget.value
                                            .split(',')
                                            .map((v) => v.trim())
                                            .filter(Boolean)
                                            .forEach((id) => {
                                                const hidden =
                                                    document.createElement(
                                                        'input',
                                                    );
                                                hidden.type = 'hidden';
                                                hidden.name = 'worker_ids[]';
                                                hidden.value = id;
                                                form.appendChild(hidden);
                                            });
                                    }}
                                />
                                {zone.access_list.map((worker) => (
                                    <input
                                        key={worker.id}
                                        type="hidden"
                                        name="worker_ids[]"
                                        value={worker.id}
                                    />
                                ))}
                                {errors.worker_ids && (
                                    <p className="text-sm text-destructive">
                                        {errors.worker_ids}
                                    </p>
                                )}
                                <Button type="submit" disabled={processing}>
                                    Update access list
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}
