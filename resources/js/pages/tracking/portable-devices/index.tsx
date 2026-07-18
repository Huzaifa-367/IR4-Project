import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type DeviceRow = {
    id: number;
    worker_id: number;
    worker_name: string | null;
    device_type: string;
    make_model: string | null;
    serial_number: string | null;
    status: string;
};

type Props = {
    devices: {
        data: DeviceRow[];
        meta: { current_page: number; last_page: number; total: number };
    };
    workers: Array<{ id: number; name: string }>;
};

export default function PortableDevicesIndex({ devices, workers }: Props) {
    return (
        <>
            <Head title="Portable devices" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Portable devices"
                    description="Approved on-site devices register"
                />

                <Form
                    action="/tracking/portable-devices"
                    method="post"
                    className="grid gap-2 rounded-lg border border-border p-4 md:grid-cols-3"
                >
                    {({ processing }) => (
                        <>
                            <select
                                name="worker_id"
                                required
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Worker</option>
                                {workers.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                            <input
                                name="device_type"
                                required
                                placeholder="Device type"
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <input
                                name="serial_number"
                                placeholder="Serial"
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <Button type="submit" disabled={processing}>
                                Register
                            </Button>
                        </>
                    )}
                </Form>

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Worker</th>
                                <th className="px-3 py-2">Type</th>
                                <th className="px-3 py-2">Serial</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {devices.data.map((device) => (
                                <tr
                                    key={device.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">
                                        {device.worker_name}
                                    </td>
                                    <td className="px-3 py-2">
                                        {device.device_type}
                                    </td>
                                    <td className="px-3 py-2">
                                        {device.serial_number ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {device.status}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {device.status === 'approved' && (
                                            <Form
                                                action={`/tracking/portable-devices/${device.id}/revoke`}
                                                method="post"
                                                className="inline-flex gap-1"
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            name="revoke_reason"
                                                            required
                                                            minLength={10}
                                                            placeholder="Reason ≥10"
                                                            className="h-8 rounded-md border border-input px-2 text-xs"
                                                        />
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Revoke
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
