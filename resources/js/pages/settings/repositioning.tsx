import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ReaderCard = {
    id: number;
    name: string;
    reference: string;
    asset: {
        id: number;
        name: string;
        current_location_label: string | null;
        is_mobile: boolean;
    } | null;
    current_zone: {
        id: number;
        name: string;
        zone_type: string;
        is_gate: boolean;
    } | null;
    bound_from: string | null;
};

type Props = {
    readers: ReaderCard[];
    zones: Array<{
        id: number;
        name: string;
        zone_type: string;
        zone_type_label: string;
    }>;
    zoneTypes: Array<{ value: string; label: string }>;
    flash: { gate_warning?: boolean; success?: string };
};

export default function RepositioningPage({ readers, zones, flash }: Props) {
    return (
        <>
            <Head title="Repositioning" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Repositioning"
                        description="Rebind RFID readers when poles move. History stays time-correct."
                    />
                    <Button asChild variant="outline">
                        <Link href="/settings/zones">Zones</Link>
                    </Button>
                </div>

                {flash.success && (
                    <p className="text-sm text-muted-foreground">
                        {flash.success}
                    </p>
                )}
                {flash.gate_warning && (
                    <p className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm">
                        Warning: that reader was bound to a gate zone.
                        Entry/exit logging for the previous gate coverage has
                        ended.
                    </p>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {readers.map((reader) => (
                        <div
                            key={reader.id}
                            className="space-y-3 rounded-lg border border-border p-4 text-sm"
                        >
                            <div>
                                <div className="font-medium">{reader.name}</div>
                                <div className="font-mono text-xs text-muted-foreground">
                                    {reader.reference}
                                </div>
                            </div>
                            <p>
                                Current zone:{' '}
                                {reader.current_zone ? (
                                    <span>
                                        {reader.current_zone.name}
                                        {reader.current_zone.is_gate
                                            ? ' (gate)'
                                            : ''}
                                    </span>
                                ) : (
                                    <span className="text-muted-foreground">
                                        unbound
                                    </span>
                                )}
                            </p>
                            <p>
                                Asset: {reader.asset?.name ?? '—'}
                                {reader.asset?.current_location_label
                                    ? ` · ${reader.asset.current_location_label}`
                                    : ''}
                            </p>
                            <Form
                                action={`/settings/readers/${reader.id}/rebind`}
                                method="post"
                                className="space-y-2 border-t border-border pt-3"
                            >
                                {({ processing }) => (
                                    <>
                                        {reader.current_zone?.is_gate && (
                                            <p className="text-xs text-muted-foreground">
                                                This is the gate reader —
                                                rebinding stops entry/exit here.
                                            </p>
                                        )}
                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor={`zone-${reader.id}`}
                                            >
                                                New zone
                                            </Label>
                                            <select
                                                id={`zone-${reader.id}`}
                                                name="zone_id"
                                                required
                                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="">
                                                    Select zone
                                                </option>
                                                {zones.map((zone) => (
                                                    <option
                                                        key={zone.id}
                                                        value={zone.id}
                                                    >
                                                        {zone.name} (
                                                        {zone.zone_type_label})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor={`note-${reader.id}`}
                                            >
                                                Note
                                            </Label>
                                            <Input
                                                id={`note-${reader.id}`}
                                                name="note"
                                                maxLength={255}
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor={`loc-${reader.id}`}>
                                                Asset location label
                                            </Label>
                                            <Input
                                                id={`loc-${reader.id}`}
                                                name="asset_location_label"
                                                defaultValue={
                                                    reader.asset
                                                        ?.current_location_label ??
                                                    ''
                                                }
                                            />
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    zones.length === 0
                                                }
                                            >
                                                Rebind
                                            </Button>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={`/settings/readers/${reader.id}/bindings`}
                                                >
                                                    History
                                                </Link>
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </div>
                    ))}
                    {readers.length === 0 && (
                        <p className="text-muted-foreground">
                            No RFID readers registered yet.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
