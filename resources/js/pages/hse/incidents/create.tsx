import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { IncidentPrefill } from '@/types/hse';

type ZoneOption = { id: number; name: string };

type Props = {
    prefill: IncidentPrefill | null;
    zones: ZoneOption[];
};

export default function IncidentCreate({ prefill, zones }: Props) {
    const defaultOccurred =
        prefill?.occurred_at?.slice(0, 16)?.replace('T', 'T') ??
        new Date().toISOString().slice(0, 16);

    return (
        <>
            <Head title="Log incident" />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Log incident"
                        description={
                            prefill
                                ? `Prefill from alert #${prefill.alert_id} — review and submit to create the record.`
                                : 'Manual HSE incident. Nothing is auto-created from alerts.'
                        }
                    />
                    <Button asChild variant="outline">
                        <Link href="/incidents">Back</Link>
                    </Button>
                </div>

                {prefill && (
                    <div className="rounded-lg border border-border bg-muted/30 p-4 text-sm">
                        <div className="font-medium">
                            From alert: {prefill.alert.title}
                        </div>
                        <div className="text-muted-foreground">
                            Type {prefill.alert.alert_type}. Evidence (snapshot /
                            RFID roster) will attach on submit.
                        </div>
                    </div>
                )}

                <Form
                    action="/incidents"
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
                            <div className="grid gap-2">
                                <Label htmlFor="nature_of_incident">
                                    Initial notes
                                </Label>
                                <textarea
                                    id="nature_of_incident"
                                    name="nature_of_incident"
                                    rows={4}
                                    defaultValue={
                                        prefill?.nature_of_incident ?? ''
                                    }
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                Submit incident
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
