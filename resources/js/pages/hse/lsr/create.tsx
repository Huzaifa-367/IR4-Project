import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { HseOption, LsrPrefill } from '@/types/hse';

type Named = { id: number; name: string };

type Props = {
    prefill: LsrPrefill | null;
    categoryOptions: HseOption[];
    zones: Named[];
    workers: Named[];
};

export default function LsrCreate({
    prefill,
    categoryOptions,
    zones,
    workers,
}: Props) {
    const isPpeLinked = prefill?.ppe_violation_id != null;
    const defaultOccurred =
        prefill?.occurred_at?.slice(0, 16) ??
        new Date().toISOString().slice(0, 16);

    return (
        <>
            <Head title="Log LSR" />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Log LSR"
                        description={
                            prefill
                                ? `Prefill from alert #${prefill.alert_id} — submit to create.`
                                : 'Manual Life Saving Rule entry.'
                        }
                    />
                    <Button asChild variant="outline">
                        <Link href="/lsr-violations">Back</Link>
                    </Button>
                </div>

                {isPpeLinked && (
                    <div className="rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground">
                        PPE-linked LSR keeps worker identity null (camera never
                        identified anyone).
                    </div>
                )}

                <Form
                    action="/lsr-violations"
                    method="post"
                    className="space-y-4 rounded-lg border border-border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {prefill?.alert_id && (
                                <input
                                    type="hidden"
                                    name="alert_id"
                                    value={prefill.alert_id}
                                />
                            )}
                            {prefill?.ppe_violation_id && (
                                <input
                                    type="hidden"
                                    name="ppe_violation_id"
                                    value={prefill.ppe_violation_id}
                                />
                            )}
                            {prefill?.camera_id && (
                                <input
                                    type="hidden"
                                    name="camera_id"
                                    value={prefill.camera_id}
                                />
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="category">Category</Label>
                                <select
                                    id="category"
                                    name="category"
                                    required
                                    defaultValue={prefill?.category ?? ''}
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    {categoryOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="occurred_at">Occurred at</Label>
                                <Input
                                    id="occurred_at"
                                    name="occurred_at"
                                    type="datetime-local"
                                    required
                                    defaultValue={defaultOccurred}
                                />
                                {errors.occurred_at && (
                                    <p className="text-sm text-destructive">
                                        {errors.occurred_at}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="zone_id">Zone</Label>
                                <select
                                    id="zone_id"
                                    name="zone_id"
                                    defaultValue={prefill?.zone_id ?? ''}
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option value="">—</option>
                                    {zones.map((zone) => (
                                        <option key={zone.id} value={zone.id}>
                                            {zone.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            {!isPpeLinked && (
                                <div className="grid gap-2">
                                    <Label htmlFor="worker_id">Worker</Label>
                                    <select
                                        id="worker_id"
                                        name="worker_id"
                                        defaultValue={prefill?.worker_id ?? ''}
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    >
                                        <option value="">—</option>
                                        {workers.map((worker) => (
                                            <option
                                                key={worker.id}
                                                value={worker.id}
                                            >
                                                {worker.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={4}
                                    defaultValue={prefill?.description ?? ''}
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                Submit LSR
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
