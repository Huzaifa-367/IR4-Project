import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DeviceRow = {
    id: number;
    name: string;
    reference: string;
    device_type: string;
    device_type_label: string;
    status: string;
    has_token: boolean;
    last_seen_at: string | null;
    asset: { id: number; name: string } | null;
};

type Props = {
    devices: {
        data: DeviceRow[];
        meta: { current_page: number; last_page: number; total: number };
    };
    assets: Array<{ id: number; name: string }>;
    deviceTypes: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    plainToken: {
        device_id: number;
        device_name: string;
        token: string;
    } | null;
};

export default function DevicesIndex({
    devices,
    assets,
    deviceTypes,
    plainToken,
}: Props) {
    return (
        <>
            <Head title="Devices" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Devices"
                        description="Readers, sensors, edge units. Tokens shown once at issuance."
                    />
                    <Button asChild variant="outline">
                        <Link href="/settings/assets">Assets</Link>
                    </Button>
                </div>

                {plainToken && (
                    <div className="max-w-2xl rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm">
                        <p className="font-medium">
                            Copy token for {plainToken.device_name} — it will
                            not be shown again.
                        </p>
                        <code className="mt-2 block font-mono text-xs break-all">
                            {plainToken.token}
                        </code>
                    </div>
                )}

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Device
                                </th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Asset</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">Token</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {devices.data.map((device) => (
                                <tr
                                    key={device.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">
                                        <div className="font-medium">
                                            {device.name}
                                        </div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {device.reference}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {device.device_type_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {device.asset?.name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {device.status}
                                    </td>
                                    <td className="px-3 py-2">
                                        {device.has_token ? 'issued' : 'none'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Form
                                            action={`/settings/devices/${device.id}/token`}
                                            method="post"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="secondary"
                                                    disabled={
                                                        processing ||
                                                        device.status ===
                                                            'retired'
                                                    }
                                                >
                                                    Issue token
                                                </Button>
                                            )}
                                        </Form>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="max-w-xl space-y-4 rounded-lg border border-border p-4">
                    <h2 className="font-medium">Register device</h2>
                    <Form
                        action="/settings/devices"
                        method="post"
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="asset_id">Asset</Label>
                                    <select
                                        id="asset_id"
                                        name="asset_id"
                                        required
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">Select asset</option>
                                        {assets.map((asset) => (
                                            <option
                                                key={asset.id}
                                                value={asset.id}
                                            >
                                                {asset.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.asset_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.asset_id}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input id="name" name="name" required />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="reference">Reference</Label>
                                    <Input
                                        id="reference"
                                        name="reference"
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="device_type">Type</Label>
                                    <select
                                        id="device_type"
                                        name="device_type"
                                        required
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        defaultValue="rfid_reader"
                                    >
                                        {deviceTypes.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing || assets.length === 0}
                                >
                                    Create device
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
