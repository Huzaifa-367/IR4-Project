import { Head, Link } from '@inertiajs/react';
import { MoreHorizontal, Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import {
    SettingsDataTable,
} from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { TokenRevealDialog } from '@/components/ir4/settings/token-reveal-dialog';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import {
    FILTER_SEARCH_DEBOUNCE_MS,
    visitFilters,
} from '@/lib/visit-filters';
import type {
    DeviceRow,
    HardwareOption,
    Paginated,
    PlainDeviceToken,
} from '@/types/hardware';

type Props = {
    devices: Paginated<DeviceRow>;
    assets: Array<{ id: number; name: string }>;
    deviceTypes: HardwareOption[];
    statuses: HardwareOption[];
    plainToken: PlainDeviceToken | null;
    filters: { q: string; device_type: string; status: string };
};

type FormState =
    | { mode: 'create' }
    | { mode: 'edit'; device: DeviceRow };

function hardwareTone(status: string): 'ok' | 'warn' | 'crit' | 'neutral' {
    if (status === 'online') {
        return 'ok';
    }

    if (status === 'maintenance' || status === 'degraded') {
        return 'warn';
    }

    if (status === 'retired' || status === 'fault' || status === 'offline') {
        return 'crit';
    }

    return 'neutral';
}

export default function DevicesIndex({
    devices,
    assets,
    deviceTypes,
    statuses,
    plainToken: initialToken,
    filters,
}: Props) {
    const [form, setForm] = useState<FormState | null>(null);
    const [tokenConfirm, setTokenConfirm] = useState<DeviceRow | null>(null);
    const [retireTarget, setRetireTarget] = useState<DeviceRow | null>(null);
    const [statusTarget, setStatusTarget] = useState<DeviceRow | null>(null);
    const [plainToken, setPlainToken] = usePropSyncedState(initialToken);
    const [q, setQ] = useState(filters.q);
    const [deviceType, setDeviceType] = useState(filters.device_type || 'all');
    const [status, setStatus] = useState(filters.status || 'all');
    const [assetId, setAssetId] = useState('');
    const [typeValue, setTypeValue] = useState('rfid_reader');
    const [nextStatus, setNextStatus] = useState('maintenance');

    const queryParams = {
        q: q || undefined,
        device_type: deviceType === 'all' ? undefined : deviceType,
        status: status === 'all' ? undefined : status,
    };

    const applyFilters = (
        patch: Partial<{
            q: string;
            device_type: string;
            status: string;
        }> = {},
    ): void => {
        const nextQ = patch.q ?? q;
        const nextDeviceType = patch.device_type ?? deviceType;
        const nextStatus = patch.status ?? status;

        visitFilters('/hardware/devices', {
            q: nextQ || undefined,
            device_type:
                nextDeviceType === 'all' ? undefined : nextDeviceType,
            status: nextStatus === 'all' ? undefined : nextStatus,
        });
    };

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ q: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const columns: SettingsColumn<DeviceRow>[] = [
        {
            key: 'device',
            header: 'Device',
            cell: (device) => (
                <div>
                    <div className="font-medium">{device.name}</div>
                    <div className="font-mono text-xs text-text-faint">
                        {device.reference}
                    </div>
                </div>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (device) => device.device_type_label,
        },
        {
            key: 'asset',
            header: 'Asset',
            cell: (device) => device.asset?.name ?? '—',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (device) => (
                <StatusPill
                    label={device.status}
                    tone={hardwareTone(device.status)}
                />
            ),
        },
        {
            key: 'token',
            header: 'Token',
            cell: (device) => (
                <StatusPill
                    label={device.has_token ? 'Issued' : 'None'}
                    tone={device.has_token ? 'ok' : 'neutral'}
                />
            ),
        },
        {
            key: 'seen',
            header: 'Last seen',
            cell: (device) =>
                device.last_seen_at
                    ? new Date(device.last_seen_at).toLocaleString()
                    : '—',
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12 text-right',
            cell: (device) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="icon" variant="ghost" aria-label="Actions">
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onClick={() => {
                                setAssetId(String(device.asset?.id ?? ''));
                                setTypeValue(device.device_type);
                                setForm({ mode: 'edit', device });
                            }}
                        >
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={device.status === 'retired'}
                            onClick={() => setTokenConfirm(device)}
                        >
                            {device.has_token ? 'Rotate token' : 'Issue token'}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={device.status === 'retired'}
                            onClick={() => {
                                setNextStatus('maintenance');
                                setStatusTarget(device);
                            }}
                        >
                            Set status
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            className="text-destructive"
                            disabled={device.status === 'retired'}
                            onClick={() => setRetireTarget(device)}
                        >
                            Retire
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Devices" />
            <SettingsPageShell
                eyebrow="Hardware"
                title="Devices"
                description="Readers, sensors, edge units. Tokens shown once at issuance."
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href="/hardware/assets">Assets</Link>
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                setAssetId('');
                                setTypeValue('rfid_reader');
                                setForm({ mode: 'create' });
                            }}
                        >
                            <Plus data-icon="inline-start" />
                            Register device
                        </Button>
                    </>
                }
                filters={
                    <>
                        <Input
                            value={q}
                            onChange={(event) => {
                                const value = event.target.value;
                                setQ(value);
                                debouncedApplySearch(value);
                            }}
                            placeholder="Search…"
                            className="w-full sm:w-56"
                        />
                        <Select
                            value={deviceType}
                            onValueChange={(value) => {
                                setDeviceType(value);
                                cancelDebounce();
                                applyFilters({ device_type: value });
                            }}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">All types</SelectItem>
                                    {deviceTypes.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select
                            value={status}
                            onValueChange={(value) => {
                                setStatus(value);
                                cancelDebounce();
                                applyFilters({ status: value });
                            }}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    {statuses.map((item) => (
                                        <SelectItem
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={devices.data}
                    rowKey={(device) => device.id}
                    meta={devices.meta}
                    pageUrl="/hardware/devices"
                    queryParams={queryParams}
                    emptyTitle="No devices"
                    emptyDescription="Register a device on an asset to begin commissioning."
                />
            </SettingsPageShell>

            <CrudFormDialog
                open={form !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setForm(null);
                    }
                }}
                title={
                    form?.mode === 'edit' ? 'Edit device' : 'Register device'
                }
                action={
                    form?.mode === 'edit'
                        ? `/hardware/devices/${form.device.id}`
                        : '/hardware/devices'
                }
                method={form?.mode === 'edit' ? 'put' : 'post'}
                submitLabel={
                    form?.mode === 'edit' ? 'Save device' : 'Create device'
                }
                transform={(data) => ({
                    ...data,
                    asset_id: assetId,
                    device_type: typeValue,
                })}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label>Asset</Label>
                            <Select value={assetId} onValueChange={setAssetId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select asset" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {assets.map((asset) => (
                                            <SelectItem
                                                key={asset.id}
                                                value={String(asset.id)}
                                            >
                                                {asset.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            {errors.asset_id ? (
                                <p className="text-destructive text-sm">
                                    {errors.asset_id}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="device-name">Name</Label>
                            <Input
                                id="device-name"
                                name="name"
                                required
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? form.device.name
                                        : ''
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="device-reference">Reference</Label>
                            <Input
                                id="device-reference"
                                name="reference"
                                required
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? form.device.reference
                                        : ''
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="device-serial">Serial number</Label>
                            <Input
                                id="device-serial"
                                name="serial_number"
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? (form.device.serial_number ?? '')
                                        : ''
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Type</Label>
                            <Select
                                value={typeValue}
                                onValueChange={setTypeValue}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {deviceTypes.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </>
                )}
            </CrudFormDialog>

            <ConfirmActionDialog
                open={tokenConfirm !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setTokenConfirm(null);
                    }
                }}
                title={
                    tokenConfirm?.has_token
                        ? 'Rotate device token'
                        : 'Issue device token'
                }
                description={
                    tokenConfirm?.has_token
                        ? 'Rotating immediately invalidates the currently configured edge credential.'
                        : 'A plaintext token will be shown once for field configuration.'
                }
                action={
                    tokenConfirm
                        ? `/hardware/devices/${tokenConfirm.id}/token`
                        : undefined
                }
                method="post"
                confirmLabel={
                    tokenConfirm?.has_token ? 'Rotate token' : 'Issue token'
                }
                destructive={tokenConfirm?.has_token === true}
            />

            <ConfirmActionDialog
                open={retireTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRetireTarget(null);
                    }
                }}
                title="Retire device"
                description="Retiring invalidates the API token and blocks ingestion. Historical telemetry is retained."
                action={
                    retireTarget
                        ? `/hardware/devices/${retireTarget.id}/status`
                        : undefined
                }
                method="patch"
                data={{ status: 'retired' }}
                confirmLabel="Retire"
                destructive
            />

            <CrudFormDialog
                open={statusTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setStatusTarget(null);
                    }
                }}
                title="Set device status"
                description="Maintenance skips offline health alerts until restored."
                action={
                    statusTarget
                        ? `/hardware/devices/${statusTarget.id}/status`
                        : '/hardware/devices'
                }
                method="patch"
                submitLabel="Update status"
                transform={() => ({ status: nextStatus })}
            >
                {() => (
                    <div className="flex flex-col gap-2">
                        <Label>Status</Label>
                        <Select
                            value={nextStatus}
                            onValueChange={setNextStatus}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {statuses
                                        .filter(
                                            (item) => item.value !== 'retired',
                                        )
                                        .map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                )}
            </CrudFormDialog>

            <TokenRevealDialog
                token={plainToken}
                onDismiss={() => setPlainToken(null)}
            />
        </>
    );
}

DevicesIndex.layout = {
    breadcrumbs: [{ title: 'Hardware', href: '/hardware/assets' }, { title: 'Devices', href: '/hardware/devices' }],
};
