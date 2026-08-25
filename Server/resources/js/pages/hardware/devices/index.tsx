import { Head, Link } from '@inertiajs/react';
import { MoreHorizontal, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
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
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Switch } from '@/components/ui/switch';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import {
    hardwarePresenceLabel,
    hardwareStatusTone,
} from '@/lib/hardware-presence';
import { FILTER_SEARCH_DEBOUNCE_MS, visitFilters } from '@/lib/visit-filters';
import settings from '@/routes/settings';
import { DeviceType } from '@/types/enums';
import type {
    CameraUnitRow,
    FieldDeviceRow,
    HardwareOption,
    Paginated,
    PlainDeviceToken,
    RegistryRow,
} from '@/types/hardware';

type Props = {
    rows: Paginated<RegistryRow>;
    assets: Array<{ id: number; name: string }>;
    registryTypes: HardwareOption[];
    cameraTypes: HardwareOption[];
    statuses: HardwareOption[];
    plainToken: PlainDeviceToken | null;
    filters: { q: string; device_type: string; status: string };
};

type FormState = { mode: 'create' } | { mode: 'edit'; row: RegistryRow };

function isCameraRow(row: RegistryRow): row is CameraUnitRow {
    return row.kind === 'camera';
}

function isDeviceRow(row: RegistryRow): row is FieldDeviceRow {
    return row.kind === 'device';
}

export default function DevicesIndex({
    rows,
    assets,
    registryTypes,
    cameraTypes,
    statuses,
    plainToken: initialToken,
    filters,
}: Props) {
    const [form, setForm] = useState<FormState | null>(null);
    const [tokenTarget, setTokenTarget] = useState<RegistryRow | null>(null);
    const [retireTarget, setRetireTarget] = useState<RegistryRow | null>(null);
    const [statusTarget, setStatusTarget] = useState<RegistryRow | null>(null);
    const [aiTarget, setAiTarget] = useState<CameraUnitRow | null>(null);
    const [plainToken, setPlainToken] = usePropSyncedState(initialToken);
    const [q, setQ] = useState(filters.q);
    const [deviceType, setDeviceType] = useState(filters.device_type || 'all');
    const [status, setStatus] = useState(filters.status || 'all');
    const [assetId, setAssetId] = useState('');
    const [typeValue, setTypeValue] = useState('rfid_reader');
    const [cameraModule, setCameraModule] = useState('fixed');
    const [aiEnabled, setAiEnabled] = useState(true);
    const [nextStatus, setNextStatus] = useState('maintenance');

    const isCameraForm = typeValue === 'camera';
    const isQrPrinterForm = typeValue === DeviceType.QrPrinter;

    const queryParams = {
        q: q || undefined,
        device_type: deviceType === 'all' ? undefined : deviceType,
        status: status === 'all' ? undefined : status,
    };

    const applyFilters = (
        patch: Partial<{ q: string; device_type: string; status: string }> = {},
    ): void => {
        const nextQ = patch.q ?? q;
        const nextDeviceType = patch.device_type ?? deviceType;
        const nextStatus = patch.status ?? status;

        visitFilters(settings.devices.index.url(), {
            q: nextQ || undefined,
            device_type: nextDeviceType === 'all' ? undefined : nextDeviceType,
            status: nextStatus === 'all' ? undefined : nextStatus,
        });
    };

    const [debouncedApplySearch, cancelDebounce] = useDebouncedCallback(
        (value: string) => applyFilters({ q: value }),
        FILTER_SEARCH_DEBOUNCE_MS,
    );

    const openCreate = (): void => {
        setAssetId('');
        setTypeValue('rfid_reader');
        setCameraModule('fixed');
        setAiEnabled(true);
        setForm({ mode: 'create' });
    };

    const openEdit = (row: RegistryRow): void => {
        setAssetId(String(row.asset?.id ?? ''));

        if (isCameraRow(row)) {
            setTypeValue('camera');
            setCameraModule(String(row.camera_type));
            setAiEnabled(row.ai_enabled);
        } else {
            setTypeValue(String(row.device_type));
        }

        setForm({ mode: 'edit', row });
    };

    const columns: SettingsColumn<RegistryRow>[] = [
        {
            key: 'name',
            header: 'Hardware',
            cell: (row) => (
                <div>
                    <div className="font-medium">{row.name}</div>
                    <div className="font-mono text-xs text-text-faint">
                        {row.reference}
                    </div>
                    {isCameraRow(row) ? (
                        <div className="text-xs text-text-dim">
                            {row.camera_type_label}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (row) =>
                isCameraRow(row) ? 'Camera' : row.device_type_label,
        },
        {
            key: 'asset',
            header: 'Asset',
            cell: (row) => row.asset?.name ?? '—',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => {
                const label = hardwarePresenceLabel(row.status, row.is_online);

                return (
                    <StatusPill
                        label={label}
                        tone={hardwareStatusTone(label)}
                    />
                );
            },
        },
        {
            key: 'connectivity',
            header: 'Token / link',
            cell: (row) => {
                if (isCameraRow(row)) {
                    return (
                        <StatusPill
                            label={row.has_token ? 'Token issued' : 'No token'}
                            tone={row.has_token ? 'ok' : 'neutral'}
                        />
                    );
                }

                if (row.device_type === DeviceType.QrPrinter) {
                    return (
                        <span className="font-mono text-xs">
                            {row.printer_host}:{row.printer_port}
                        </span>
                    );
                }

                return (
                    <StatusPill
                        label={row.has_token ? 'Token issued' : 'No token'}
                        tone={row.has_token ? 'ok' : 'neutral'}
                    />
                );
            },
        },
        {
            key: 'seen',
            header: 'Last seen',
            cell: (row) =>
                row.last_seen_at
                    ? new Date(row.last_seen_at).toLocaleString()
                    : '—',
        },
        {
            key: 'frame',
            header: 'Last frame',
            cell: (row) =>
                isCameraRow(row) && row.last_frame_at
                    ? new Date(row.last_frame_at).toLocaleString()
                    : '—',
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12 text-right',
            cell: (row) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            size="icon"
                            variant="ghost"
                            aria-label="Actions"
                        >
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => openEdit(row)}>
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={async () => {
                                try {
                                    await navigator.clipboard.writeText(
                                        row.uuid,
                                    );
                                    toast.success('UUID copied');
                                } catch {
                                    toast.error('Could not copy UUID');
                                }
                            }}
                        >
                            Copy UUID
                        </DropdownMenuItem>
                        {isCameraRow(row) ? (
                            <DropdownMenuItem
                                disabled={row.status === 'retired'}
                                onClick={() => setAiTarget(row)}
                            >
                                {row.ai_enabled ? 'Disable AI' : 'Enable AI'}
                            </DropdownMenuItem>
                        ) : null}
                        {(isCameraRow(row) ||
                            row.device_type !== DeviceType.QrPrinter) && (
                            <DropdownMenuItem
                                disabled={row.status === 'retired'}
                                onClick={() => setTokenTarget(row)}
                            >
                                {row.has_token ? 'Rotate token' : 'Issue token'}
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                            onClick={() => {
                                setNextStatus(
                                    row.status === 'retired'
                                        ? 'online'
                                        : 'maintenance',
                                );
                                setStatusTarget(row);
                            }}
                        >
                            Set status
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            className="text-destructive"
                            disabled={row.status === 'retired'}
                            onClick={() => setRetireTarget(row)}
                        >
                            Retire
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const formAction =
        form?.mode === 'edit'
            ? settings.devices.update.url(form.row.uuid)
            : settings.devices.store.url();

    const editingCamera =
        form?.mode === 'edit' && isCameraRow(form.row) ? form.row : null;
    const editingDevice =
        form?.mode === 'edit' && isDeviceRow(form.row) ? form.row : null;

    return (
        <>
            <Head title="Devices" />
            <SettingsPageShell
                eyebrow="Hardware"
                title="Devices"
                description="Readers, sensors, cameras, and printers on one registry."
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={settings.assets.index()}>Assets</Link>
                        </Button>
                        <Button type="button" onClick={openCreate}>
                            <Plus data-icon="inline-start" />
                            Register hardware
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
                        <SearchableSelect
                            value={deviceType}
                            onValueChange={(value) => {
                                setDeviceType(value);
                                cancelDebounce();
                                applyFilters({ device_type: value });
                            }}
                            placeholder="Type"
                            triggerClassName="w-44"
                            options={[
                                { value: 'all', label: 'All types' },
                                ...registryTypes.map((type) => ({
                                    value: type.value,
                                    label: type.label,
                                })),
                            ]}
                        />
                        <SearchableSelect
                            value={status}
                            onValueChange={(value) => {
                                setStatus(value);
                                cancelDebounce();
                                applyFilters({ status: value });
                            }}
                            placeholder="Status"
                            triggerClassName="w-40"
                            options={[
                                { value: 'all', label: 'All statuses' },
                                ...statuses.map((item) => ({
                                    value: item.value,
                                    label: item.label,
                                })),
                            ]}
                        />
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={rows.data}
                    rowKey={(row) => `${row.kind}-${row.id}`}
                    meta={rows.meta}
                    pageUrl={settings.devices.index.url()}
                    queryParams={queryParams}
                    emptyTitle="No hardware"
                    emptyDescription="Register a device or camera on an asset to begin commissioning."
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
                    form?.mode === 'edit'
                        ? 'Edit hardware'
                        : 'Register hardware'
                }
                action={formAction}
                method={form?.mode === 'edit' ? 'put' : 'post'}
                submitLabel={form?.mode === 'edit' ? 'Save' : 'Create'}
                transform={(data) => {
                    if (isCameraForm) {
                        return {
                            ...data,
                            asset_id: assetId,
                            device_type: 'camera',
                            camera_type: cameraModule,
                            ai_enabled: aiEnabled,
                        };
                    }

                    return {
                        ...data,
                        asset_id: assetId,
                        device_type: typeValue,
                    };
                }}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label>Asset</Label>
                            <SearchableSelect
                                value={assetId}
                                onValueChange={setAssetId}
                                placeholder="Select asset"
                                options={assets.map((asset) => ({
                                    value: String(asset.id),
                                    label: asset.name,
                                }))}
                            />
                            {errors.asset_id ? (
                                <p className="text-sm text-destructive">
                                    {errors.asset_id}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="hw-name">Name</Label>
                            <Input
                                id="hw-name"
                                name="name"
                                required
                                defaultValue={
                                    form?.mode === 'edit' ? form.row.name : ''
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Type</Label>
                            <SearchableSelect
                                value={typeValue}
                                onValueChange={setTypeValue}
                                options={registryTypes.map((type) => ({
                                    value: type.value,
                                    label: type.label,
                                }))}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="hw-reference">Reference</Label>
                            <Input
                                id="hw-reference"
                                name="reference"
                                required
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? form.row.reference
                                        : ''
                                }
                            />
                        </div>
                        {isCameraForm ? (
                            <>
                                <div className="flex flex-col gap-2">
                                    <Label>Camera module</Label>
                                    <SearchableSelect
                                        value={cameraModule}
                                        onValueChange={setCameraModule}
                                        options={cameraTypes.map((type) => ({
                                            value: type.value,
                                            label: type.label,
                                        }))}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="stream-url">
                                        Stream URL
                                    </Label>
                                    <Input
                                        id="stream-url"
                                        name="stream_url"
                                        required
                                        defaultValue={
                                            editingCamera?.stream_url ?? ''
                                        }
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        id="ai-enabled"
                                        checked={aiEnabled}
                                        onCheckedChange={setAiEnabled}
                                    />
                                    <Label htmlFor="ai-enabled">
                                        AI enabled
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="ai_enabled"
                                        value={aiEnabled ? '1' : '0'}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="api-url">API URL</Label>
                                    <Input
                                        id="api-url"
                                        name="api_url"
                                        required
                                        placeholder="http://172.16.3.2:8600/rois"
                                        defaultValue={
                                            editingCamera?.api_url ?? ''
                                        }
                                    />
                                </div>
                            </>
                        ) : null}
                        {!isCameraForm ? (
                            <>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="device-serial">
                                        Serial number
                                    </Label>
                                    <Input
                                        id="device-serial"
                                        name="serial_number"
                                        defaultValue={
                                            editingDevice?.serial_number ?? ''
                                        }
                                    />
                                </div>
                                {isQrPrinterForm ? (
                                    <>
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="printer-host">
                                                Printer IP
                                            </Label>
                                            <Input
                                                id="printer-host"
                                                name="printer_host"
                                                required
                                                defaultValue={
                                                    editingDevice?.printer_host ??
                                                    ''
                                                }
                                            />
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="printer-port">
                                                Printer port
                                            </Label>
                                            <Input
                                                id="printer-port"
                                                name="printer_port"
                                                type="number"
                                                required
                                                defaultValue={
                                                    editingDevice?.printer_port ??
                                                    9100
                                                }
                                            />
                                        </div>
                                    </>
                                ) : null}
                            </>
                        ) : null}
                    </>
                )}
            </CrudFormDialog>

            <ConfirmActionDialog
                open={tokenTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setTokenTarget(null);
                    }
                }}
                title="Issue device token"
                description="A plaintext token will be shown once for field configuration."
                action={
                    tokenTarget
                        ? settings.devices.token.url(tokenTarget.uuid)
                        : undefined
                }
                method="post"
                confirmLabel="Issue token"
            />

            <ConfirmActionDialog
                open={retireTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRetireTarget(null);
                    }
                }}
                title="Retire hardware"
                description="Retiring hides the unit from live surfaces and blocks ingestion. Historical data is retained."
                action={
                    retireTarget
                        ? settings.devices.status.url(retireTarget.uuid)
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
                title="Set status"
                action={
                    statusTarget
                        ? settings.devices.status.url(statusTarget.uuid)
                        : settings.devices.index.url()
                }
                method="patch"
                submitLabel="Update status"
                transform={() => ({ status: nextStatus })}
            >
                {() => (
                    <div className="flex flex-col gap-2">
                        <Label>Status</Label>
                        <SearchableSelect
                            value={nextStatus}
                            onValueChange={setNextStatus}
                            options={statuses
                                .filter((item) => item.value !== 'retired')
                                .map((item) => ({
                                    value: item.value,
                                    label: item.label,
                                }))}
                        />
                    </div>
                )}
            </CrudFormDialog>

            <ConfirmActionDialog
                open={aiTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAiTarget(null);
                    }
                }}
                title={aiTarget?.ai_enabled ? 'Disable AI' : 'Enable AI'}
                description="Toggles AI processing for this camera stream."
                action={
                    aiTarget
                        ? settings.devices.toggleAi.url(aiTarget.uuid)
                        : undefined
                }
                method="patch"
                confirmLabel={aiTarget?.ai_enabled ? 'Disable' : 'Enable'}
            />

            <TokenRevealDialog
                token={plainToken}
                onDismiss={() => setPlainToken(null)}
            />
        </>
    );
}

DevicesIndex.layout = {
    breadcrumbs: [
        { title: 'Hardware', href: settings.assets.index() },
        { title: 'Devices', href: settings.devices.index() },
    ],
};
