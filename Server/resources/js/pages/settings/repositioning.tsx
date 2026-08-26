import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { cn } from '@/lib/utils';
import settings from '@/routes/settings';

type ReaderCard = {
    id: number;
    uuid: string;
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

type CameraCard = {
    id: number;
    uuid: string;
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
    } | null;
    bound_from: string | null;
};

type ZoneOption = {
    id: number;
    name: string;
    zone_type: string;
    zone_type_label: string;
};

type Props = {
    readers: ReaderCard[];
    cameras: CameraCard[];
    zones: ZoneOption[];
    zoneTypes: Array<{ value: string; label: string }>;
    flash: { gate_warning?: boolean; success?: string };
};

type TabId = 'rfid' | 'cameras';

export default function RepositioningPage({
    readers,
    cameras,
    zones,
    flash,
}: Props) {
    const [tab, setTab] = useState<TabId>('rfid');
    const [zoneByReader, setZoneByReader] = useState<Record<number, string>>(
        {},
    );
    const [zoneByCamera, setZoneByCamera] = useState<Record<number, string>>(
        {},
    );

    const tabs: Array<{ id: TabId; label: string; count: number }> = [
        { id: 'rfid', label: 'RFID readers', count: readers.length },
        { id: 'cameras', label: 'Cameras', count: cameras.length },
    ];

    return (
        <>
            <Head title="Repositioning" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Repositioning"
                        description="Rebind RFID readers and cameras when poles move. History stays time-correct; camera headcount stays separable by zone."
                    />
                    <Button asChild variant="outline">
                        <Link href={settings.zones.index()}>Zones</Link>
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

                <div
                    role="tablist"
                    aria-label="Repositioning targets"
                    className="flex flex-wrap gap-1 rounded-[var(--radius)] border border-border bg-surface p-1.5 shadow-[var(--shadow-card)]"
                >
                    {tabs.map((item) => (
                        <Button
                            key={item.id}
                            type="button"
                            role="tab"
                            size="sm"
                            aria-selected={tab === item.id}
                            variant={tab === item.id ? 'default' : 'ghost'}
                            onClick={() => setTab(item.id)}
                        >
                            {item.label}
                            <span
                                className={cn(
                                    'ml-1.5 rounded-pill px-1.5 py-0 text-[10px] font-semibold tabular-nums',
                                    tab === item.id
                                        ? 'bg-white/20'
                                        : 'bg-surface-3 text-text-dim',
                                )}
                            >
                                {item.count}
                            </span>
                        </Button>
                    ))}
                </div>

                {tab === 'rfid' ? (
                    <div
                        role="tabpanel"
                        className="grid gap-4 lg:grid-cols-2"
                    >
                        {readers.map((reader) => (
                            <div
                                key={reader.id}
                                className="space-y-3 rounded-lg border border-border p-4 text-sm"
                            >
                                <div>
                                    <div className="font-medium">
                                        {reader.name}
                                    </div>
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
                                    action={settings.readers.rebind.url(
                                        reader.uuid,
                                    )}
                                    method="post"
                                    className="space-y-2 border-t border-border pt-3"
                                    transform={(data) => ({
                                        ...data,
                                        zone_id:
                                            zoneByReader[reader.id] || null,
                                    })}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            {reader.current_zone?.is_gate && (
                                                <p className="text-xs text-muted-foreground">
                                                    This is the gate reader —
                                                    rebinding stops entry/exit
                                                    here.
                                                </p>
                                            )}
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor={`zone-${reader.id}`}
                                                >
                                                    New zone
                                                </Label>
                                                <SearchableSelect
                                                    id={`zone-${reader.id}`}
                                                    required
                                                    value={
                                                        zoneByReader[
                                                            reader.id
                                                        ] ?? ''
                                                    }
                                                    onValueChange={(value) =>
                                                        setZoneByReader(
                                                            (current) => ({
                                                                ...current,
                                                                [reader.id]:
                                                                    value,
                                                            }),
                                                        )
                                                    }
                                                    allowClear
                                                    clearLabel="Select zone"
                                                    placeholder="Select zone"
                                                    options={zones.map(
                                                        (zone) => ({
                                                            value: String(
                                                                zone.id,
                                                            ),
                                                            label: `${zone.name} (${zone.zone_type_label})`,
                                                        }),
                                                    )}
                                                />
                                                {errors.zone_id ? (
                                                    <p className="text-sm text-destructive">
                                                        {errors.zone_id}
                                                    </p>
                                                ) : null}
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
                                                <Label
                                                    htmlFor={`loc-${reader.id}`}
                                                >
                                                    Asset location label
                                                </Label>
                                                <Input
                                                    id={`loc-${reader.id}`}
                                                    name="asset_location_label"
                                                    maxLength={255}
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
                                                        zones.length === 0 ||
                                                        !(
                                                            zoneByReader[
                                                                reader.id
                                                            ] ?? ''
                                                        )
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
                                                        href={settings.readers.bindings(
                                                            reader.uuid,
                                                        )}
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
                ) : (
                    <div
                        role="tabpanel"
                        className="grid gap-4 lg:grid-cols-2"
                    >
                        {cameras.map((camera) => (
                            <div
                                key={camera.id}
                                className="space-y-3 rounded-lg border border-border p-4 text-sm"
                            >
                                <div>
                                    <div className="font-medium">
                                        {camera.name}
                                    </div>
                                    <div className="font-mono text-xs text-muted-foreground">
                                        {camera.reference}
                                    </div>
                                </div>
                                <p>
                                    Current zone:{' '}
                                    {camera.current_zone ? (
                                        <span>{camera.current_zone.name}</span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            unbound
                                        </span>
                                    )}
                                </p>
                                <p>
                                    Asset: {camera.asset?.name ?? '—'}
                                    {camera.asset?.current_location_label
                                        ? ` · ${camera.asset.current_location_label}`
                                        : ''}
                                </p>
                                <Form
                                    action={settings.cameras.rebind.url(
                                        camera.uuid,
                                    )}
                                    method="post"
                                    className="space-y-2 border-t border-border pt-3"
                                    transform={(data) => ({
                                        ...data,
                                        zone_id:
                                            zoneByCamera[camera.id] || null,
                                    })}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor={`cam-zone-${camera.id}`}
                                                >
                                                    New zone
                                                </Label>
                                                <SearchableSelect
                                                    id={`cam-zone-${camera.id}`}
                                                    required
                                                    value={
                                                        zoneByCamera[
                                                            camera.id
                                                        ] ?? ''
                                                    }
                                                    onValueChange={(value) =>
                                                        setZoneByCamera(
                                                            (current) => ({
                                                                ...current,
                                                                [camera.id]:
                                                                    value,
                                                            }),
                                                        )
                                                    }
                                                    allowClear
                                                    clearLabel="Select zone"
                                                    placeholder="Select zone"
                                                    options={zones.map(
                                                        (zone) => ({
                                                            value: String(
                                                                zone.id,
                                                            ),
                                                            label: `${zone.name} (${zone.zone_type_label})`,
                                                        }),
                                                    )}
                                                />
                                                {errors.zone_id ? (
                                                    <p className="text-sm text-destructive">
                                                        {errors.zone_id}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor={`cam-note-${camera.id}`}
                                                >
                                                    Note
                                                </Label>
                                                <Input
                                                    id={`cam-note-${camera.id}`}
                                                    name="note"
                                                    maxLength={255}
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor={`cam-loc-${camera.id}`}
                                                >
                                                    Asset location label
                                                </Label>
                                                <Input
                                                    id={`cam-loc-${camera.id}`}
                                                    name="asset_location_label"
                                                    maxLength={255}
                                                    defaultValue={
                                                        camera.asset
                                                            ?.current_location_label ??
                                                        ''
                                                    }
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    zones.length === 0 ||
                                                    !(
                                                        zoneByCamera[
                                                            camera.id
                                                        ] ?? ''
                                                    )
                                                }
                                            >
                                                Rebind
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </div>
                        ))}
                        {cameras.length === 0 && (
                            <p className="text-muted-foreground">
                                No cameras registered yet.
                            </p>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
