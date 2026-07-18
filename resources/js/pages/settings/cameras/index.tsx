import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal, Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import {
    SettingsDataTable,
    type SettingsColumn,
} from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
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
import { Switch } from '@/components/ui/switch';
import type {
    CameraRow,
    HardwareOption,
    Paginated,
} from '@/types/hardware';

type Props = {
    cameras: Paginated<CameraRow>;
    assets: Array<{ id: number; name: string }>;
    cameraTypes: HardwareOption[];
    statuses: HardwareOption[];
    filters: { q: string; status: string };
};

type FormState =
    | { mode: 'create' }
    | { mode: 'edit'; camera: CameraRow };

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

export default function CamerasIndex({
    cameras,
    assets,
    cameraTypes,
    filters,
}: Props) {
    const [form, setForm] = useState<FormState | null>(null);
    const [retireTarget, setRetireTarget] = useState<CameraRow | null>(null);
    const [aiTarget, setAiTarget] = useState<CameraRow | null>(null);
    const [q, setQ] = useState(filters.q);
    const [status, setStatus] = useState(filters.status || 'all');
    const [assetId, setAssetId] = useState('');
    const [typeValue, setTypeValue] = useState('fixed');
    const [aiEnabled, setAiEnabled] = useState(true);

    const applyFilters = (): void => {
        router.get(
            '/settings/cameras',
            {
                q: q || undefined,
                status: status === 'all' ? undefined : status,
            },
            { preserveState: true, replace: true },
        );
    };

    const columns: SettingsColumn<CameraRow>[] = [
        {
            key: 'camera',
            header: 'Camera',
            cell: (camera) => (
                <div>
                    <div className="font-medium">{camera.name}</div>
                    <div className="font-mono text-xs text-text-faint">
                        {camera.reference}
                    </div>
                </div>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (camera) => camera.camera_type_label ?? camera.camera_type,
        },
        {
            key: 'asset',
            header: 'Asset',
            cell: (camera) => camera.asset?.name ?? '—',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (camera) => (
                <StatusPill
                    label={camera.status}
                    tone={hardwareTone(camera.status)}
                />
            ),
        },
        {
            key: 'ai',
            header: 'AI',
            cell: (camera) => (
                <StatusPill
                    label={camera.ai_enabled ? 'On' : 'Off'}
                    tone={camera.ai_enabled ? 'ok' : 'neutral'}
                />
            ),
        },
        {
            key: 'frame',
            header: 'Last frame',
            cell: (camera) =>
                camera.last_frame_at
                    ? new Date(camera.last_frame_at).toLocaleString()
                    : '—',
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12 text-right',
            cell: (camera) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="icon" variant="ghost" aria-label="Actions">
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onClick={() => {
                                setAssetId(String(camera.asset?.id ?? ''));
                                setTypeValue(camera.camera_type);
                                setAiEnabled(camera.ai_enabled);
                                setForm({ mode: 'edit', camera });
                            }}
                        >
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => setAiTarget(camera)}>
                            {camera.ai_enabled ? 'Disable AI' : 'Enable AI'}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            className="text-destructive"
                            disabled={camera.status === 'retired'}
                            onClick={() => setRetireTarget(camera)}
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
            <Head title="Cameras" />
            <SettingsPageShell
                title="Cameras"
                description="LAN stream endpoints for the live wall."
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href="/settings/assets">Assets</Link>
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                setAssetId('');
                                setTypeValue('fixed');
                                setAiEnabled(true);
                                setForm({ mode: 'create' });
                            }}
                        >
                            <Plus data-icon="inline-start" />
                            Register camera
                        </Button>
                    </>
                }
                filters={
                    <>
                        <Input
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Search…"
                            className="w-full sm:w-56"
                        />
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="online">Online</SelectItem>
                                    <SelectItem value="offline">
                                        Offline
                                    </SelectItem>
                                    <SelectItem value="maintenance">
                                        Maintenance
                                    </SelectItem>
                                    <SelectItem value="retired">
                                        Retired
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Button type="button" variant="outline" onClick={applyFilters}>
                            Apply
                        </Button>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={cameras.data}
                    rowKey={(camera) => camera.id}
                    meta={cameras.meta}
                    pageUrl="/settings/cameras"
                    queryParams={{
                        q: q || undefined,
                        status: status === 'all' ? undefined : status,
                    }}
                    emptyTitle="No cameras"
                    emptyDescription="Register a camera on an asset for the live wall."
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
                    form?.mode === 'edit' ? 'Edit camera' : 'Register camera'
                }
                action={
                    form?.mode === 'edit'
                        ? `/settings/cameras/${form.camera.id}`
                        : '/settings/cameras'
                }
                method={form?.mode === 'edit' ? 'put' : 'post'}
                submitLabel={
                    form?.mode === 'edit' ? 'Save camera' : 'Create camera'
                }
                transform={(data) => ({
                    ...data,
                    asset_id: assetId,
                    camera_type: typeValue,
                    ai_enabled: aiEnabled ? '1' : '0',
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
                            <Label htmlFor="camera-name">Name</Label>
                            <Input
                                id="camera-name"
                                name="name"
                                required
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? form.camera.name
                                        : ''
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="camera-reference">Reference</Label>
                            <Input
                                id="camera-reference"
                                name="reference"
                                required
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? form.camera.reference
                                        : ''
                                }
                            />
                            {errors.reference ? (
                                <p className="text-destructive text-sm">
                                    {errors.reference}
                                </p>
                            ) : null}
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
                                        {cameraTypes.map((type) => (
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
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="camera-stream">Stream URL</Label>
                            <Input
                                id="camera-stream"
                                name="stream_url"
                                required
                                placeholder="rtsp://10.0.0.10/stream1"
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? (form.camera.stream_url ?? '')
                                        : ''
                                }
                            />
                        </div>
                        <div className="flex items-center justify-between gap-3 rounded-[var(--radius-sm)] border border-border px-3 py-2">
                            <div>
                                <p className="text-sm font-medium">AI enabled</p>
                                <p className="text-xs text-text-dim">
                                    PPE / fall inference on this camera
                                </p>
                            </div>
                            <Switch
                                checked={aiEnabled}
                                onCheckedChange={setAiEnabled}
                            />
                        </div>
                    </>
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
                description={
                    aiTarget?.ai_enabled
                        ? 'This camera will stop generating PPE and fall detections.'
                        : 'This camera will resume PPE and fall detections.'
                }
                action={
                    aiTarget
                        ? `/settings/cameras/${aiTarget.id}/ai`
                        : undefined
                }
                method="patch"
                confirmLabel={aiTarget?.ai_enabled ? 'Disable AI' : 'Enable AI'}
            />

            <ConfirmActionDialog
                open={retireTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRetireTarget(null);
                    }
                }}
                title="Retire camera"
                description="Retiring keeps the camera row for historical PPE and incident links."
                action={
                    retireTarget
                        ? `/settings/cameras/${retireTarget.id}/status`
                        : undefined
                }
                method="patch"
                data={{ status: 'retired' }}
                confirmLabel="Retire"
                destructive
            />
        </>
    );
}

CamerasIndex.layout = {
    breadcrumbs: [{ title: 'Cameras', href: '/settings/cameras' }],
};
