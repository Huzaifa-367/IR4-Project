import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/ir4/settings/confirm-action-dialog';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
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
import type { PaginatedMeta } from '@/types/hardware';

type DeviceRow = {
    id: number;
    worker_id: number;
    worker_name: string | null;
    device_type: string;
    make_model: string | null;
    serial_number: string | null;
    status: string;
};

type Props = {
    devices: { data: DeviceRow[]; meta: PaginatedMeta };
    workers: Array<{ id: number; name: string }>;
};

export default function PortableDevicesIndex({ devices, workers }: Props) {
    const [addOpen, setAddOpen] = useState(false);
    const [addWorker, setAddWorker] = useState('');
    const [revokeTarget, setRevokeTarget] = useState<DeviceRow | null>(null);
    const [revokeReason, setRevokeReason] = useState('');

    const columns: SettingsColumn<DeviceRow>[] = [
        {
            key: 'worker',
            header: 'Worker',
            cell: (device) =>
                device.worker_name ?? `Worker #${device.worker_id}`,
        },
        {
            key: 'type',
            header: 'Type',
            cell: (device) => device.device_type,
        },
        {
            key: 'model',
            header: 'Make / model',
            cell: (device) => device.make_model ?? '—',
        },
        {
            key: 'serial',
            header: 'Serial',
            cell: (device) => device.serial_number ?? '—',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (device) => (
                <StatusPill
                    label={
                        device.status === 'approved' ? 'Approved' : 'Revoked'
                    }
                    tone={device.status === 'approved' ? 'ok' : 'crit'}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-28 text-right',
            cell: (device) =>
                device.status === 'approved' ? (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            setRevokeReason('');
                            setRevokeTarget(device);
                        }}
                    >
                        Revoke
                    </Button>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Portable devices" />
            <SettingsPageShell
                eyebrow="Workforce"
                title="Portable Devices"
                description="Approved on-site devices register (SA restriction of portable devices)."
                actions={
                    <Button
                        type="button"
                        onClick={() => {
                            setAddWorker('');
                            setAddOpen(true);
                        }}
                    >
                        <Plus data-icon="inline-start" />
                        Register device
                    </Button>
                }
            >
                <SettingsDataTable
                    columns={columns}
                    rows={devices.data}
                    rowKey={(device) => device.id}
                    meta={devices.meta}
                    pageUrl="/workforce/portable-devices"
                    emptyTitle="No portable devices"
                    emptyDescription="Register a worker's phone, tablet, or camera to approve it on site."
                />
            </SettingsPageShell>

            <CrudFormDialog
                open={addOpen}
                onOpenChange={setAddOpen}
                title="Register device"
                action="/workforce/portable-devices"
                method="post"
                submitLabel="Register"
                disableSubmit={!addWorker}
                transform={(data) => ({ ...data, worker_id: addWorker })}
            >
                {({ errors }) => (
                    <>
                        <div className="flex flex-col gap-2">
                            <Label>Worker</Label>
                            <Select
                                value={addWorker}
                                onValueChange={setAddWorker}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Choose a worker…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {workers.map((w) => (
                                            <SelectItem
                                                key={w.id}
                                                value={String(w.id)}
                                            >
                                                {w.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="device-type">Device type</Label>
                            <Input
                                id="device-type"
                                name="device_type"
                                required
                                maxLength={150}
                                placeholder="Phone, tablet, camera…"
                            />
                            {errors.device_type ? (
                                <p className="text-sm text-destructive">
                                    {errors.device_type}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="device-model">Make / model</Label>
                            <Input
                                id="device-model"
                                name="make_model"
                                maxLength={150}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="device-serial">Serial number</Label>
                            <Input
                                id="device-serial"
                                name="serial_number"
                                maxLength={150}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="device-approval">
                                Approval reference
                            </Label>
                            <Input
                                id="device-approval"
                                name="approval_reference"
                                maxLength={150}
                            />
                        </div>
                    </>
                )}
            </CrudFormDialog>

            <ConfirmActionDialog
                open={revokeTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRevokeTarget(null);
                    }
                }}
                title="Revoke device"
                description={
                    revokeTarget ? (
                        <div className="flex flex-col gap-3">
                            <p>
                                Revoke {revokeTarget.device_type}
                                {revokeTarget.worker_name
                                    ? ` (${revokeTarget.worker_name})`
                                    : ''}
                                ?
                            </p>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="revoke-reason">
                                    Reason (min 10 characters)
                                </Label>
                                <Input
                                    id="revoke-reason"
                                    value={revokeReason}
                                    onChange={(event) =>
                                        setRevokeReason(event.target.value)
                                    }
                                    minLength={10}
                                />
                            </div>
                        </div>
                    ) : (
                        ''
                    )
                }
                action={
                    revokeTarget
                        ? `/workforce/portable-devices/${revokeTarget.id}/revoke`
                        : undefined
                }
                method="post"
                data={{ revoke_reason: revokeReason }}
                confirmLabel="Revoke"
                destructive
                disabled={revokeReason.trim().length < 10}
            />
        </>
    );
}

PortableDevicesIndex.layout = {
    breadcrumbs: [
        { title: 'Workforce', href: '/workforce/workers' },
        { title: 'Portable Devices', href: '/workforce/portable-devices' },
    ],
};
