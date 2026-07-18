import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Props = {
    asset: {
        id: number;
        name: string;
        identifier: string;
        asset_type_label: string;
        status: string;
        is_mobile: boolean;
        current_location_label: string | null;
        last_heartbeat_at: string | null;
        cameras: Array<{
            id: number;
            name: string;
            reference: string;
            status: string;
            ai_enabled: boolean;
        }>;
        devices: Array<{
            id: number;
            name: string;
            reference: string;
            device_type_label: string;
            status: string;
            has_token: boolean;
            last_seen_at: string | null;
        }>;
    };
};

export default function AssetShow({ asset }: Props) {
    return (
        <>
            <Head title={asset.name} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={asset.name}
                        description={`${asset.asset_type_label} · ${asset.identifier}`}
                    />
                    <Button asChild variant="outline">
                        <Link href="/settings/assets">Back</Link>
                    </Button>
                </div>

                <dl className="grid max-w-xl gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">Status</dt>
                        <dd>{asset.status}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Mobile</dt>
                        <dd>{asset.is_mobile ? 'yes' : 'no'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Location</dt>
                        <dd>{asset.current_location_label ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Last heartbeat
                        </dt>
                        <dd>{asset.last_heartbeat_at ?? '—'}</dd>
                    </div>
                </dl>

                <section className="space-y-2">
                    <h2 className="font-medium">
                        Cameras ({asset.cameras.length})
                    </h2>
                    <ul className="space-y-1 text-sm">
                        {asset.cameras.map((camera) => (
                            <li key={camera.id}>
                                {camera.name} · {camera.reference} ·{' '}
                                {camera.status}
                                {camera.ai_enabled ? ' · AI on' : ' · AI off'}
                            </li>
                        ))}
                        {asset.cameras.length === 0 && (
                            <li className="text-muted-foreground">
                                None yet — add from Cameras.
                            </li>
                        )}
                    </ul>
                </section>

                <section className="space-y-2">
                    <h2 className="font-medium">
                        Devices ({asset.devices.length})
                    </h2>
                    <ul className="space-y-1 text-sm">
                        {asset.devices.map((device) => (
                            <li key={device.id}>
                                {device.name} · {device.device_type_label} ·{' '}
                                {device.status}
                                {device.has_token
                                    ? ' · token issued'
                                    : ' · no token'}
                            </li>
                        ))}
                        {asset.devices.length === 0 && (
                            <li className="text-muted-foreground">
                                None yet — add from Devices.
                            </li>
                        )}
                    </ul>
                </section>
            </div>
        </>
    );
}
