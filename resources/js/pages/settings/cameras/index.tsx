import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CameraRow = {
    id: number;
    name: string;
    reference: string;
    camera_type: string;
    status: string;
    ai_enabled: boolean;
    last_frame_at: string | null;
    asset: { id: number; name: string } | null;
};

type Props = {
    cameras: {
        data: CameraRow[];
        meta: { current_page: number; last_page: number; total: number };
    };
    assets: Array<{ id: number; name: string }>;
    cameraTypes: Array<{ value: string; label: string }>;
};

export default function CamerasIndex({ cameras, assets, cameraTypes }: Props) {
    return (
        <>
            <Head title="Cameras" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Cameras"
                        description="LAN stream endpoints for the live wall."
                    />
                    <Button asChild variant="outline">
                        <Link href="/settings/assets">Assets</Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Camera
                                </th>
                                <th className="px-3 py-2 font-medium">Asset</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">AI</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {cameras.data.map((camera) => (
                                <tr
                                    key={camera.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">
                                        <div className="font-medium">
                                            {camera.name}
                                        </div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {camera.reference}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {camera.asset?.name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {camera.status}
                                    </td>
                                    <td className="px-3 py-2">
                                        {camera.ai_enabled ? 'on' : 'off'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Form
                                            action={`/settings/cameras/${camera.id}/ai`}
                                            method="patch"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="secondary"
                                                    disabled={processing}
                                                >
                                                    Toggle AI
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
                    <h2 className="font-medium">Register camera</h2>
                    <Form
                        action="/settings/cameras"
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
                                    {errors.reference && (
                                        <p className="text-sm text-destructive">
                                            {errors.reference}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="camera_type">Type</Label>
                                    <select
                                        id="camera_type"
                                        name="camera_type"
                                        required
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        defaultValue="fixed"
                                    >
                                        {cameraTypes.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="stream_url">
                                        Stream URL
                                    </Label>
                                    <Input
                                        id="stream_url"
                                        name="stream_url"
                                        required
                                        placeholder="rtsp://10.0.0.10/stream1"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing || assets.length === 0}
                                >
                                    Create camera
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
