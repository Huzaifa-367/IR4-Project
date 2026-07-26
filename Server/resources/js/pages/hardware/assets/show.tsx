import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import type { HardwareOption } from '@/types/hardware';

type AssetDetail = {
    id: number;
    uuid: string;
    name: string;
    identifier: string;
    asset_type: string;
    asset_type_label: string;
    status: string;
    is_mobile: boolean;
    current_location_label: string | null;
    last_heartbeat_at: string | null;
    cameras: Array<{
        id: number;
        name: string;
        reference: string;
        status: string;
        ai_enabled: boolean;
    }>;
    devices: Array<{
        id: number;
        name: string;
        reference: string;
        device_type_label: string;
        status: string;
        has_token: boolean;
        last_seen_at: string | null;
    }>;
};

type Props = {
    asset: AssetDetail;
    assetTypes: HardwareOption[];
    statuses: HardwareOption[];
};

export default function AssetShow({
    asset,
    assetTypes,
    statuses,
}: Props) {
    const [isEditing, setIsEditing] = useState(false);
    const [editType, setEditType] = useState(asset.asset_type);
    const [editStatus, setEditStatus] = useState(asset.status);
    const [isMobile, setIsMobile] = useState(asset.is_mobile);

    const openEdit = (): void => {
        setEditType(asset.asset_type);
        setEditStatus(asset.status);
        setIsMobile(asset.is_mobile);
        setIsEditing(true);
    };

    return (
        <>
            <Head title={asset.name} />
            <SettingsPageShell
                eyebrow="Hardware"
                title={asset.name}
                description={`${asset.asset_type_label} · ${asset.identifier}`}
                actions={
                    <>
                        <Button type="button" onClick={openEdit}>
                            Edit asset
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/hardware/cameras">Cameras</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/hardware/devices">Devices</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/hardware/assets">Back</Link>
                        </Button>
                    </>
                }
            >
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetaCard
                        label="Status"
                        value={
                            <StatusPill
                                label={asset.status}
                                tone={
                                    asset.status === 'active' ? 'ok' : 'warn'
                                }
                            />
                        }
                    />
                    <MetaCard
                        label="Mobile"
                        value={asset.is_mobile ? 'Yes' : 'No'}
                    />
                    <MetaCard
                        label="Location"
                        value={asset.current_location_label ?? '—'}
                    />
                    <MetaCard
                        label="Last heartbeat"
                        value={
                            asset.last_heartbeat_at
                                ? new Date(
                                      asset.last_heartbeat_at,
                                  ).toLocaleString()
                                : '—'
                        }
                    />
                </div>

                <section className="rounded-[var(--radius-sm)] border border-border bg-surface p-5 shadow-[var(--shadow-card)]">
                    <h2 className="mb-3 text-sm font-semibold">
                        Cameras ({asset.cameras.length})
                    </h2>
                    <ul className="flex flex-col gap-2 text-sm">
                        {asset.cameras.map((camera) => (
                            <li
                                key={camera.id}
                                className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                            >
                                <span>
                                    {camera.name}{' '}
                                    <span className="font-mono text-xs text-text-faint">
                                        {camera.reference}
                                    </span>
                                </span>
                                <StatusPill
                                    label={
                                        camera.ai_enabled
                                            ? `${camera.status} · AI`
                                            : camera.status
                                    }
                                    tone={
                                        camera.status === 'online'
                                            ? 'ok'
                                            : 'warn'
                                    }
                                />
                            </li>
                        ))}
                        {asset.cameras.length === 0 ? (
                            <li className="text-text-dim">
                                None yet — add from Cameras.
                            </li>
                        ) : null}
                    </ul>
                </section>

                <section className="rounded-[var(--radius-sm)] border border-border bg-surface p-5 shadow-[var(--shadow-card)]">
                    <h2 className="mb-3 text-sm font-semibold">
                        Devices ({asset.devices.length})
                    </h2>
                    <ul className="flex flex-col gap-2 text-sm">
                        {asset.devices.map((device) => (
                            <li
                                key={device.id}
                                className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                            >
                                <span>
                                    {device.name} · {device.device_type_label}
                                </span>
                                <StatusPill
                                    label={
                                        device.has_token
                                            ? `${device.status} · token`
                                            : device.status
                                    }
                                    tone={
                                        device.status === 'online'
                                            ? 'ok'
                                            : 'warn'
                                    }
                                />
                            </li>
                        ))}
                        {asset.devices.length === 0 ? (
                            <li className="text-text-dim">
                                None yet — add from Devices.
                            </li>
                        ) : null}
                    </ul>
                </section>
            </SettingsPageShell>

            <CrudFormDialog
                open={isEditing}
                onOpenChange={setIsEditing}
                title="Edit asset"
                action={`/hardware/assets/${asset.uuid}`}
                method="put"
                submitLabel="Save asset"
                transform={(data) => ({
                    ...data,
                    asset_type: editType,
                    status: editStatus,
                    is_mobile: isMobile ? '1' : '0',
                })}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="asset-show-name">Name</Label>
                            <Input
                                id="asset-show-name"
                                name="name"
                                required
                                maxLength={150}
                                defaultValue={asset.name}
                            />
                            {errors.name ? (
                                <p className="text-destructive text-sm">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="asset-show-identifier">
                                Identifier
                            </Label>
                            <Input
                                id="asset-show-identifier"
                                name="identifier"
                                required
                                maxLength={150}
                                defaultValue={asset.identifier}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Type</Label>
                            <SearchableSelect
                                value={editType}
                                onValueChange={setEditType}
                                options={assetTypes.map((type) => ({
                                    value: type.value,
                                    label: type.label,
                                }))}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Status</Label>
                            <SearchableSelect
                                value={editStatus}
                                onValueChange={setEditStatus}
                                options={statuses.map((item) => ({
                                    value: item.value,
                                    label: item.label,
                                }))}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="asset-show-location">
                                Current location label
                            </Label>
                            <Input
                                id="asset-show-location"
                                name="current_location_label"
                                defaultValue={
                                    asset.current_location_label ?? ''
                                }
                            />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={isMobile}
                                onCheckedChange={(checked) =>
                                    setIsMobile(checked === true)
                                }
                            />
                            Mobile (repositionable)
                        </label>
                    </>
                )}
            </CrudFormDialog>
        </>
    );
}

function MetaCard({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="rounded-[var(--radius-sm)] border border-border bg-surface p-4 shadow-[var(--shadow-card)]">
            <p className="eyebrow">{label}</p>
            <div className="mt-2 text-sm font-medium text-text">{value}</div>
        </div>
    );
}

AssetShow.layout = {
    breadcrumbs: [
        { title: 'Hardware', href: '/hardware/assets' },
        { title: 'Assets', href: '/hardware/assets' },
        { title: 'Detail', href: '#' },
    ],
};
