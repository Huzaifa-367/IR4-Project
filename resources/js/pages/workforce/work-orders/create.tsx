import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    zones: Array<{ id: number; name: string }>;
};

export default function WorkOrderCreate({ zones }: Props) {
    const [zoneId, setZoneId] = useState('');

    return (
        <>
            <Head title="New work order" />
            <div className="mx-auto max-w-xl space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="New work order"
                        description="Job package reference. Add permits under it next (hot work, CSE, …)."
                    />
                    <Button asChild variant="outline">
                        <Link href="/workforce/work-orders">Back</Link>
                    </Button>
                </div>

                <Form
                    action="/workforce/work-orders"
                    method="post"
                    className="space-y-4 rounded-lg border border-border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="reference">Reference</Label>
                                <Input
                                    id="reference"
                                    name="reference"
                                    required
                                    maxLength={64}
                                    placeholder="WO-2026-0042"
                                />
                                {errors.reference && (
                                    <p className="text-sm text-destructive">
                                        {errors.reference}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    placeholder="Brief scope of work…"
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="zone_id">Zone (optional)</Label>
                                <select
                                    id="zone_id"
                                    name="zone_id"
                                    value={zoneId}
                                    onChange={(event) =>
                                        setZoneId(event.target.value)
                                    }
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option value="">No zone</option>
                                    {zones.map((zone) => (
                                        <option key={zone.id} value={zone.id}>
                                            {zone.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.zone_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.zone_id}
                                    </p>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    Create work order
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
