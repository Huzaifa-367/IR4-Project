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
import { Checkbox } from '@/components/ui/checkbox';
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
import type { AssetRow, HardwareOption, Paginated } from '@/types/hardware';

type Props = {
    assets: Paginated<AssetRow>;
    assetTypes: HardwareOption[];
    statuses: HardwareOption[];
    filters: { q: string; asset_type: string; status: string };
};

type FormState =
    | { mode: 'create' }
    | { mode: 'edit'; asset: AssetRow };

function statusTone(status: string): 'ok' | 'warn' | 'crit' | 'neutral' {
    if (status === 'active') {
        return 'ok';
    }
    if (status === 'maintenance') {
        return 'warn';
    }
    return 'crit';
}

export default function AssetsIndex({
    assets,
    assetTypes,
    statuses,
    filters,
}: Props) {
    const [form, setForm] = useState<FormState | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<AssetRow | null>(null);
    const [q, setQ] = useState(filters.q);
    const [assetType, setAssetType] = useState(filters.asset_type || 'all');
    const [status, setStatus] = useState(filters.status || 'all');
    const [isMobile, setIsMobile] = useState(true);
    const [editType, setEditType] = useState('pole');
    const [editStatus, setEditStatus] = useState('active');

    const applyFilters = (): void => {
        router.get(
            '/settings/assets',
            {
                q: q || undefined,
                asset_type: assetType === 'all' ? undefined : assetType,
                status: status === 'all' ? undefined : status,
            },
            { preserveState: true, replace: true },
        );
    };

    const columns: SettingsColumn<AssetRow>[] = [
        {
            key: 'name',
            header: 'Asset',
            cell: (asset) => (
                <div>
                    <Link
                        href={`/settings/assets/${asset.id}`}
                        className="font-medium text-text hover:underline"
                    >
                        {asset.name}
                    </Link>
                    <div className="font-mono text-xs text-text-faint">
                        {asset.identifier}
                    </div>
                </div>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (asset) => asset.asset_type_label,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (asset) => (
                <StatusPill
                    label={asset.status}
                    tone={statusTone(asset.status)}
                />
            ),
        },
        {
            key: 'cameras',
            header: 'Cameras',
            className: 'text-right font-mono tabular-nums',
            cell: (asset) => asset.cameras_count,
        },
        {
            key: 'devices',
            header: 'Devices',
            className: 'text-right font-mono tabular-nums',
            cell: (asset) => asset.devices_count,
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12 text-right',
            cell: (asset) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="icon" variant="ghost" aria-label="Actions">
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={`/settings/assets/${asset.id}`}>
                                Open
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => {
                                setEditType(asset.asset_type);
                                setEditStatus(asset.status);
                                setIsMobile(asset.is_mobile);
                                setForm({ mode: 'edit', asset });
                            }}
                        >
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            className="text-destructive"
                            onClick={() => setDeleteTarget(asset)}
                        >
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Assets" />
            <SettingsPageShell
                title="Assets"
                description="Poles, gate, SCC units — fully dynamic, nothing seeded."
                actions={
                    <Button
                        type="button"
                        onClick={() => {
                            setEditType('pole');
                            setEditStatus('active');
                            setIsMobile(true);
                            setForm({ mode: 'create' });
                        }}
                    >
                        <Plus data-icon="inline-start" />
                        Register asset
                    </Button>
                }
                filters={
                    <>
                        <Input
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Search…"
                            className="w-full sm:w-56"
                            aria-label="Search assets"
                        />
                        <Select value={assetType} onValueChange={setAssetType}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">All types</SelectItem>
                                    {assetTypes.map((type) => (
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
                        <Select value={status} onValueChange={setStatus}>
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
                        <Button type="button" variant="outline" onClick={applyFilters}>
                            Apply
                        </Button>
                    </>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={assets.data}
                    rowKey={(asset) => asset.id}
                    meta={assets.meta}
                    pageUrl="/settings/assets"
                    queryParams={{
                        q: q || undefined,
                        asset_type: assetType === 'all' ? undefined : assetType,
                        status: status === 'all' ? undefined : status,
                    }}
                    emptyTitle="No assets"
                    emptyDescription="Register a pole, gate, or SCC unit to begin commissioning."
                />
            </SettingsPageShell>

            <CrudFormDialog
                open={form !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setForm(null);
                    }
                }}
                title={form?.mode === 'edit' ? 'Edit asset' : 'Register asset'}
                action={
                    form?.mode === 'edit'
                        ? `/settings/assets/${form.asset.id}`
                        : '/settings/assets'
                }
                method={form?.mode === 'edit' ? 'put' : 'post'}
                submitLabel={form?.mode === 'edit' ? 'Save asset' : 'Create asset'}
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
                            <Label htmlFor="asset-name">Name</Label>
                            <Input
                                id="asset-name"
                                name="name"
                                required
                                maxLength={150}
                                defaultValue={
                                    form?.mode === 'edit' ? form.asset.name : ''
                                }
                            />
                            {errors.name ? (
                                <p className="text-destructive text-sm">
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="asset-identifier">Identifier</Label>
                            <Input
                                id="asset-identifier"
                                name="identifier"
                                required
                                maxLength={150}
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? form.asset.identifier
                                        : ''
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Type</Label>
                            <Select value={editType} onValueChange={setEditType}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {assetTypes.map((type) => (
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
                        {form?.mode === 'edit' ? (
                            <div className="flex flex-col gap-2">
                                <Label>Status</Label>
                                <Select
                                    value={editStatus}
                                    onValueChange={setEditStatus}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
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
                            </div>
                        ) : null}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="asset-location">
                                Current location label
                            </Label>
                            <Input
                                id="asset-location"
                                name="current_location_label"
                                defaultValue={
                                    form?.mode === 'edit'
                                        ? (form.asset.current_location_label ??
                                          '')
                                        : ''
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

            <ConfirmActionDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                title="Delete asset"
                description={
                    deleteTarget ? (
                        <>
                            Delete <strong>{deleteTarget.name}</strong>?{' '}
                            {deleteTarget.cameras_count +
                                deleteTarget.devices_count >
                            0
                                ? 'Remove or reassign cameras and devices first.'
                                : 'This permanently removes the asset.'}
                        </>
                    ) : (
                        ''
                    )
                }
                action={
                    deleteTarget
                        ? `/settings/assets/${deleteTarget.id}`
                        : undefined
                }
                method="delete"
                confirmLabel="Delete"
                destructive
                disabled={
                    deleteTarget !== null &&
                    deleteTarget.cameras_count + deleteTarget.devices_count > 0
                }
            />
        </>
    );
}

AssetsIndex.layout = {
    breadcrumbs: [{ title: 'Assets', href: '/settings/assets' }],
};
