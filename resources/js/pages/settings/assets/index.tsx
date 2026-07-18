import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AssetRow = {
    id: number;
    name: string;
    identifier: string;
    asset_type: string;
    asset_type_label: string;
    status: string;
    is_mobile: boolean;
    cameras_count: number;
    devices_count: number;
};

type Props = {
    assets: {
        data: AssetRow[];
        meta: { current_page: number; last_page: number; total: number };
    };
    assetTypes: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
};

export default function AssetsIndex({ assets, assetTypes }: Props) {
    return (
        <>
            <Head title="Assets" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Assets"
                    description="Poles, gate, SCC units — fully dynamic, nothing seeded."
                />

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Cameras
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Devices
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {assets.data.map((asset) => (
                                <tr
                                    key={asset.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">
                                        <div className="font-medium">
                                            {asset.name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {asset.identifier}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {asset.asset_type_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {asset.status}
                                    </td>
                                    <td className="px-3 py-2">
                                        {asset.cameras_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        {asset.devices_count}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link
                                                href={`/settings/assets/${asset.id}`}
                                            >
                                                Open
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {assets.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No assets registered yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="max-w-xl space-y-4 rounded-lg border border-border p-4">
                    <h2 className="font-medium">Register asset</h2>
                    <Form
                        action="/settings/assets"
                        method="post"
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        maxLength={150}
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-destructive">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="identifier">
                                        Identifier
                                    </Label>
                                    <Input
                                        id="identifier"
                                        name="identifier"
                                        required
                                        maxLength={150}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="asset_type">Type</Label>
                                    <select
                                        id="asset_type"
                                        name="asset_type"
                                        required
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        defaultValue="pole"
                                    >
                                        {assetTypes.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="is_mobile"
                                        value="1"
                                        defaultChecked
                                    />
                                    Mobile (repositionable)
                                </label>
                                <Button type="submit" disabled={processing}>
                                    Create asset
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
