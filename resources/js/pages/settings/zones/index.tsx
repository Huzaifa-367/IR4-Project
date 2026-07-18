import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ZoneRow = {
    id: number;
    name: string;
    zone_type: string;
    zone_type_label: string;
    requires_authorization: boolean;
    occupancy_limit: number | null;
    is_active: boolean;
    current_readers: number;
    access_list_count: number;
};

type Props = {
    zones: {
        data: ZoneRow[];
        meta: { current_page: number; last_page: number; total: number };
    };
    zoneTypes: Array<{ value: string; label: string }>;
};

export default function ZonesIndex({ zones, zoneTypes }: Props) {
    return (
        <>
            <Head title="Zones" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Zones"
                        description="Logical areas. Readers bind via time-aware intervals."
                    />
                    <Button asChild variant="outline">
                        <Link href="/settings/repositioning">
                            Repositioning
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Auth</th>
                                <th className="px-3 py-2 font-medium">
                                    Readers
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Access list
                                </th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {zones.data.map((zone) => (
                                <tr
                                    key={zone.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2 font-medium">
                                        {zone.name}
                                    </td>
                                    <td className="px-3 py-2">
                                        {zone.zone_type_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {zone.requires_authorization
                                            ? 'required'
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {zone.current_readers}
                                    </td>
                                    <td className="px-3 py-2">
                                        {zone.access_list_count}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link
                                                href={`/settings/zones/${zone.id}`}
                                            >
                                                Open
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {zones.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No zones yet — create the first one
                                        below.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="max-w-xl space-y-4 rounded-lg border border-border p-4">
                    <h2 className="font-medium">Create zone</h2>
                    <Form
                        action="/settings/zones"
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
                                    <Label htmlFor="zone_type">Type</Label>
                                    <select
                                        id="zone_type"
                                        name="zone_type"
                                        required
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        defaultValue="work"
                                    >
                                        {zoneTypes.map((type) => (
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
                                        name="requires_authorization"
                                        value="1"
                                    />
                                    Requires authorization
                                </label>
                                <div className="grid gap-2">
                                    <Label htmlFor="occupancy_limit">
                                        Occupancy limit
                                    </Label>
                                    <Input
                                        id="occupancy_limit"
                                        name="occupancy_limit"
                                        type="number"
                                        min={1}
                                    />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Create zone
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
