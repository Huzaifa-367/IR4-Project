import { Head, Link, router } from '@inertiajs/react';
import { useState  } from 'react';
import type {ReactNode} from 'react';
import { CrudFormDialog } from '@/components/ir4/settings/crud-form-dialog';
import { SettingsDataTable } from '@/components/ir4/settings/settings-data-table';
import type { SettingsColumn } from '@/components/ir4/settings/settings-data-table';
import { SettingsPageShell } from '@/components/ir4/settings/settings-page-shell';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';

type Option = { id: number; code: string; name: string };

type CrewRole = {
    id: number;
    uuid: string;
    role_code: string;
    label: string;
    min_count: number;
    is_mandatory: boolean;
    sort_order: number;
};

type ChecklistItem = {
    id: number;
    uuid: string;
    code: string;
    label: string;
    is_mandatory: boolean;
    is_active: boolean;
    sort_order: number;
};

type GasChannel = {
    id: number;
    uuid: string;
    channel_code: string;
    label: string;
    unit: string | null;
    warn_below: number | null;
    warn_above: number | null;
    alarm_below: number | null;
    alarm_above: number | null;
    sort_order: number;
};

type Conflict = {
    id: number;
    uuid: string;
    conflicts_with_type_id: number;
    conflicts_with: Option | null;
    scope: string;
    severity: string;
    note: string | null;
};

type DocRequirement = {
    id: number;
    uuid: string;
    worker_document_type_id: number;
    role_code: string | null;
    is_mandatory: boolean;
    must_be_verified: boolean;
    worker_document_type: Option | null;
};

type PermitTypeDetail = {
    id: number;
    uuid: string;
    code: string;
    name: string;
    description: string | null;
    colour_token: string | null;
    sa_form_code: string | null;
    requires_gas_test: boolean;
    requires_approver: boolean;
    requires_joint_inspection: boolean;
    default_validity_minutes: number;
    max_renewals: number;
    max_total_minutes: number;
    allows_extended: boolean;
    retest_interval_minutes: number | null;
    sort_order: number;
    is_active: boolean;
    roles: CrewRole[];
    checklist_items: ChecklistItem[];
    gas_channels: GasChannel[];
    conflicts: Conflict[];
    document_requirements: DocRequirement[];
};

type Props = {
    permitType: PermitTypeDetail;
    otherTypes: Option[];
    documentTypes: Option[];
};

type DialogState =
    | { kind: 'edit-type' }
    | { kind: 'add-role' }
    | { kind: 'edit-role'; role: CrewRole }
    | { kind: 'add-checklist' }
    | { kind: 'edit-checklist'; item: ChecklistItem }
    | { kind: 'add-gas' }
    | { kind: 'edit-gas'; channel: GasChannel }
    | { kind: 'add-conflict' }
    | { kind: 'edit-conflict'; conflict: Conflict }
    | { kind: 'add-doc-req' }
    | { kind: 'edit-doc-req'; requirement: DocRequirement }
    | null;

export default function PermitTypeShow({
    permitType,
    otherTypes,
    documentTypes,
}: Props) {
    const [dialog, setDialog] = useState<DialogState>(null);
    const [conflictsWithTypeId, setConflictsWithTypeId] = useState('');
    const [conflictScope, setConflictScope] = useState('same_zone');
    const [conflictSeverity, setConflictSeverity] = useState('warn');
    const [docTypeId, setDocTypeId] = useState('');
    const [docRoleCode, setDocRoleCode] = useState('');
    const base = `/workforce/permit-types/${permitType.uuid}`;

    const roleColumns: SettingsColumn<CrewRole>[] = [
        {
            key: 'code',
            header: 'Code',
            cell: (row) => (
                <span className="font-mono text-xs">{row.role_code}</span>
            ),
        },
        { key: 'label', header: 'Label', cell: (row) => row.label },
        { key: 'min', header: 'Min', cell: (row) => row.min_count },
        {
            key: 'mandatory',
            header: 'Mandatory',
            cell: (row) => (
                <StatusPill
                    label={row.is_mandatory ? 'Required' : 'Optional'}
                    tone={row.is_mandatory ? 'warn' : 'neutral'}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-40 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            setDialog({ kind: 'edit-role', role: row })
                        }
                    >
                        Edit
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            if (confirm(`Remove “${row.label}”?`)) {
                                router.delete(
                                    `/workforce/crew-roles/${row.uuid}`,
                                );
                            }
                        }}
                    >
                        Remove
                    </Button>
                </div>
            ),
        },
    ];

    const checklistColumns: SettingsColumn<ChecklistItem>[] = [
        {
            key: 'code',
            header: 'Code',
            cell: (row) => (
                <span className="font-mono text-xs">{row.code}</span>
            ),
        },
        { key: 'label', header: 'Label', cell: (row) => row.label },
        {
            key: 'flags',
            header: 'Flags',
            cell: (row) => (
                <div className="flex flex-wrap gap-1">
                    <StatusPill
                        label={row.is_mandatory ? 'Required' : 'Optional'}
                        tone={row.is_mandatory ? 'warn' : 'neutral'}
                    />
                    <StatusPill
                        label={row.is_active ? 'Active' : 'Off'}
                        tone={row.is_active ? 'ok' : 'neutral'}
                    />
                </div>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-40 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            setDialog({ kind: 'edit-checklist', item: row })
                        }
                    >
                        Edit
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            if (confirm(`Remove “${row.label}”?`)) {
                                router.delete(
                                    `${base}/checklist-items/${row.uuid}`,
                                );
                            }
                        }}
                    >
                        Remove
                    </Button>
                </div>
            ),
        },
    ];

    const gasColumns: SettingsColumn<GasChannel>[] = [
        {
            key: 'code',
            header: 'Channel',
            cell: (row) => (
                <span className="font-mono text-xs">{row.channel_code}</span>
            ),
        },
        {
            key: 'label',
            header: 'Label',
            cell: (row) =>
                `${row.label}${row.unit ? ` (${row.unit})` : ''}`,
        },
        {
            key: 'alarms',
            header: 'Alarms',
            cell: (row) =>
                [
                    row.alarm_below != null ? `≤${row.alarm_below}` : null,
                    row.alarm_above != null ? `≥${row.alarm_above}` : null,
                ]
                    .filter(Boolean)
                    .join(' · ') || '—',
        },
        {
            key: 'actions',
            header: '',
            className: 'w-40 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            setDialog({ kind: 'edit-gas', channel: row })
                        }
                    >
                        Edit
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            if (confirm(`Remove “${row.label}”?`)) {
                                router.delete(`${base}/gas-channels/${row.uuid}`);
                            }
                        }}
                    >
                        Remove
                    </Button>
                </div>
            ),
        },
    ];

    const conflictColumns: SettingsColumn<Conflict>[] = [
        {
            key: 'with',
            header: 'Conflicts with',
            cell: (row) => row.conflicts_with?.name ?? '—',
        },
        { key: 'scope', header: 'Scope', cell: (row) => row.scope },
        {
            key: 'severity',
            header: 'Severity',
            cell: (row) => (
                <StatusPill
                    label={row.severity}
                    tone={row.severity === 'block' ? 'crit' : 'warn'}
                />
            ),
        },
        {
            key: 'note',
            header: 'Note',
            cell: (row) => row.note ?? '—',
        },
        {
            key: 'actions',
            header: '',
            className: 'w-40 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            setConflictsWithTypeId(
                                String(row.conflicts_with_type_id),
                            );
                            setConflictScope(row.scope);
                            setConflictSeverity(row.severity);
                            setDialog({
                                kind: 'edit-conflict',
                                conflict: row,
                            });
                        }}
                    >
                        Edit
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            if (confirm('Remove this SIMOPS conflict?')) {
                                router.delete(`${base}/conflicts/${row.uuid}`);
                            }
                        }}
                    >
                        Remove
                    </Button>
                </div>
            ),
        },
    ];

    const docReqColumns: SettingsColumn<DocRequirement>[] = [
        {
            key: 'doc',
            header: 'Document',
            cell: (row) => row.worker_document_type?.name ?? '—',
        },
        {
            key: 'role',
            header: 'Role',
            cell: (row) => row.role_code ?? 'Any role',
        },
        {
            key: 'flags',
            header: 'Flags',
            cell: (row) => (
                <div className="flex flex-wrap gap-1">
                    {row.is_mandatory ? (
                        <StatusPill label="Mandatory" tone="warn" />
                    ) : null}
                    {row.must_be_verified ? (
                        <StatusPill label="Verified" tone="ok" />
                    ) : null}
                </div>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-40 text-right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            setDocTypeId(
                                String(row.worker_document_type_id),
                            );
                            setDocRoleCode(row.role_code ?? '');
                            setDialog({
                                kind: 'edit-doc-req',
                                requirement: row,
                            });
                        }}
                    >
                        Edit
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            if (confirm('Remove this document requirement?')) {
                                router.delete(
                                    `${base}/document-requirements/${row.uuid}`,
                                );
                            }
                        }}
                    >
                        Remove
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={permitType.name} />
            <SettingsPageShell
                eyebrow="Workforce"
                title={permitType.name}
                description={
                    permitType.description ??
                    `${permitType.code}${permitType.sa_form_code ? ` · ${permitType.sa_form_code}` : ''}`
                }
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/workforce/permit-types">Back</Link>
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setDialog({ kind: 'edit-type' })}
                        >
                            Edit type
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/workforce/crew-roles">All crew roles</Link>
                        </Button>
                    </div>
                }
            >
                <div className="mb-6 flex flex-wrap gap-2">
                    <StatusPill
                        label={permitType.is_active ? 'Active' : 'Inactive'}
                        tone={permitType.is_active ? 'ok' : 'neutral'}
                    />
                    {permitType.requires_gas_test ? (
                        <StatusPill label="Gas test" tone="warn" />
                    ) : null}
                    {permitType.requires_joint_inspection ? (
                        <StatusPill label="Joint inspection" tone="warn" />
                    ) : null}
                    {permitType.requires_approver ? (
                        <StatusPill label="Approver" tone="warn" />
                    ) : null}
                    {permitType.allows_extended ? (
                        <StatusPill label="Extended" tone="info" />
                    ) : null}
                    <StatusPill
                        label={`Validity ${permitType.default_validity_minutes} min`}
                        tone="neutral"
                    />
                    <StatusPill
                        label={`Max renewals ${permitType.max_renewals}`}
                        tone="neutral"
                    />
                </div>

                <Section
                    title="Crew roles"
                    description="Workers on this permit type must use one of these role codes."
                    actionLabel="Add role"
                    onAction={() => setDialog({ kind: 'add-role' })}
                >
                    <SettingsDataTable
                        columns={roleColumns}
                        rows={permitType.roles}
                        rowKey={(row) => row.id}
                        emptyTitle="No crew roles"
                        emptyDescription="Add the personnel roles this permit type requires."
                    />
                </Section>

                <Section
                    title="Checklist"
                    description="JSA / precaution items completed before issue."
                    actionLabel="Add item"
                    onAction={() => setDialog({ kind: 'add-checklist' })}
                >
                    <SettingsDataTable
                        columns={checklistColumns}
                        rows={permitType.checklist_items}
                        rowKey={(row) => row.id}
                        emptyTitle="No checklist items"
                        emptyDescription="Add hazard / precaution checklist items."
                    />
                </Section>

                <Section
                    title="Gas channels"
                    description="Atmospheric pack evaluated on gas tests for this type."
                    actionLabel="Add channel"
                    onAction={() => setDialog({ kind: 'add-gas' })}
                >
                    <SettingsDataTable
                        columns={gasColumns}
                        rows={permitType.gas_channels}
                        rowKey={(row) => row.id}
                        emptyTitle="No gas channels"
                        emptyDescription="Add channels when this type requires gas testing."
                    />
                </Section>

                <Section
                    title="SIMOPS conflicts"
                    description="Types that conflict in the same or adjacent zone."
                    actionLabel="Add conflict"
                    onAction={() => {
                        setConflictsWithTypeId('');
                        setConflictScope('same_zone');
                        setConflictSeverity('warn');
                        setDialog({ kind: 'add-conflict' });
                    }}
                >
                    <SettingsDataTable
                        columns={conflictColumns}
                        rows={permitType.conflicts}
                        rowKey={(row) => row.id}
                        emptyTitle="No conflicts"
                        emptyDescription="Define SIMOPS block/warn rules against other types."
                    />
                </Section>

                <Section
                    title="Document requirements"
                    description="Worker competence documents required before crew can be listed."
                    actionLabel="Add requirement"
                    onAction={() => {
                        setDocTypeId('');
                        setDocRoleCode('');
                        setDialog({ kind: 'add-doc-req' });
                    }}
                >
                    <SettingsDataTable
                        columns={docReqColumns}
                        rows={permitType.document_requirements}
                        rowKey={(row) => row.id}
                        emptyTitle="No document requirements"
                        emptyDescription="Link worker document types to roles on this permit."
                    />
                </Section>
            </SettingsPageShell>

            <CrudFormDialog
                open={dialog?.kind === 'edit-type'}
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                    }
                }}
                title="Edit permit type"
                action={base}
                method="put"
                submitLabel="Save type"
            >
                {() => (
                    <>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Name" htmlFor="edit_name">
                            <Input
                                id="edit_name"
                                name="name"
                                defaultValue={permitType.name}
                                required
                            />
                        </Field>
                        <Field label="SA form code" htmlFor="edit_sa">
                            <Input
                                id="edit_sa"
                                name="sa_form_code"
                                defaultValue={permitType.sa_form_code ?? ''}
                            />
                        </Field>
                        <Field
                            label="Description"
                            htmlFor="edit_description"
                            className="sm:col-span-2"
                        >
                            <Input
                                id="edit_description"
                                name="description"
                                defaultValue={permitType.description ?? ''}
                            />
                        </Field>
                        <Field label="Colour token" htmlFor="edit_colour">
                            <Input
                                id="edit_colour"
                                name="colour_token"
                                defaultValue={permitType.colour_token ?? ''}
                            />
                        </Field>
                        <Field label="Validity (minutes)" htmlFor="edit_validity">
                            <Input
                                id="edit_validity"
                                name="default_validity_minutes"
                                type="number"
                                min={1}
                                defaultValue={permitType.default_validity_minutes}
                            />
                        </Field>
                        <Field label="Max renewals" htmlFor="edit_renewals">
                            <Input
                                id="edit_renewals"
                                name="max_renewals"
                                type="number"
                                min={0}
                                defaultValue={permitType.max_renewals}
                            />
                        </Field>
                        <Field label="Max total minutes" htmlFor="edit_total">
                            <Input
                                id="edit_total"
                                name="max_total_minutes"
                                type="number"
                                min={1}
                                defaultValue={permitType.max_total_minutes}
                            />
                        </Field>
                        <Field
                            label="Retest interval (minutes)"
                            htmlFor="edit_retest"
                        >
                            <Input
                                id="edit_retest"
                                name="retest_interval_minutes"
                                type="number"
                                min={1}
                                defaultValue={
                                    permitType.retest_interval_minutes ?? ''
                                }
                            />
                        </Field>
                        <Field label="Sort order" htmlFor="edit_sort">
                            <Input
                                id="edit_sort"
                                name="sort_order"
                                type="number"
                                min={0}
                                defaultValue={permitType.sort_order}
                            />
                        </Field>
                        <FlagCheckbox
                            name="requires_gas_test"
                            label="Requires gas test"
                            defaultChecked={permitType.requires_gas_test}
                        />
                        <FlagCheckbox
                            name="requires_joint_inspection"
                            label="Requires joint inspection"
                            defaultChecked={permitType.requires_joint_inspection}
                        />
                        <FlagCheckbox
                            name="requires_approver"
                            label="Requires approver"
                            defaultChecked={permitType.requires_approver}
                        />
                        <FlagCheckbox
                            name="allows_extended"
                            label="Allows extended"
                            defaultChecked={permitType.allows_extended}
                        />
                        <FlagCheckbox
                            name="is_active"
                            label="Active"
                            defaultChecked={permitType.is_active}
                        />
                    </div>
                    </>
                )}
            </CrudFormDialog>

            <CrudFormDialog
                open={
                    dialog?.kind === 'add-role' || dialog?.kind === 'edit-role'
                }
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                    }
                }}
                title={
                    dialog?.kind === 'edit-role'
                        ? 'Edit crew role'
                        : 'Add crew role'
                }
                action={
                    dialog?.kind === 'edit-role'
                        ? `/workforce/crew-roles/${dialog.role.uuid}`
                        : '/workforce/crew-roles'
                }
                method={dialog?.kind === 'edit-role' ? 'put' : 'post'}
                submitLabel="Save role"
            >
                {() => (
                    <>
                    {dialog?.kind !== 'edit-role' ? (
                        <input
                            type="hidden"
                            name="permit_type_id"
                            value={permitType.id}
                        />
                    ) : null}
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Code" htmlFor="role_code">
                            <Input
                                id="role_code"
                                name="role_code"
                                required
                                placeholder="fire_watch"
                                pattern="[a-z][a-z0-9_]*"
                                defaultValue={
                                    dialog?.kind === 'edit-role'
                                        ? dialog.role.role_code
                                        : ''
                                }
                            />
                        </Field>
                        <Field label="Label" htmlFor="role_label">
                            <Input
                                id="role_label"
                                name="label"
                                required
                                defaultValue={
                                    dialog?.kind === 'edit-role'
                                        ? dialog.role.label
                                        : ''
                                }
                            />
                        </Field>
                        <Field label="Min count" htmlFor="role_min">
                            <Input
                                id="role_min"
                                name="min_count"
                                type="number"
                                min={0}
                                defaultValue={
                                    dialog?.kind === 'edit-role'
                                        ? dialog.role.min_count
                                        : 1
                                }
                                required
                            />
                        </Field>
                        <Field label="Sort order" htmlFor="role_sort">
                            <Input
                                id="role_sort"
                                name="sort_order"
                                type="number"
                                min={0}
                                defaultValue={
                                    dialog?.kind === 'edit-role'
                                        ? dialog.role.sort_order
                                        : 0
                                }
                            />
                        </Field>
                        <FlagCheckbox
                            name="is_mandatory"
                            label="Mandatory"
                            defaultChecked={
                                dialog?.kind === 'edit-role'
                                    ? dialog.role.is_mandatory
                                    : true
                            }
                        />
                    </div>
                    </>
                )}
            </CrudFormDialog>

            <CrudFormDialog
                open={
                    dialog?.kind === 'add-checklist' ||
                    dialog?.kind === 'edit-checklist'
                }
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                    }
                }}
                title={
                    dialog?.kind === 'edit-checklist'
                        ? 'Edit checklist item'
                        : 'Add checklist item'
                }
                action={
                    dialog?.kind === 'edit-checklist'
                        ? `${base}/checklist-items/${dialog.item.uuid}`
                        : `${base}/checklist-items`
                }
                method={dialog?.kind === 'edit-checklist' ? 'put' : 'post'}
                submitLabel="Save"
            >
                {() => (
                    <>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Code" htmlFor="cl_code">
                            <Input
                                id="cl_code"
                                name="code"
                                required
                                pattern="[a-z][a-z0-9_]*"
                                defaultValue={
                                    dialog?.kind === 'edit-checklist'
                                        ? dialog.item.code
                                        : ''
                                }
                            />
                        </Field>
                        <Field label="Label" htmlFor="cl_label">
                            <Input
                                id="cl_label"
                                name="label"
                                required
                                defaultValue={
                                    dialog?.kind === 'edit-checklist'
                                        ? dialog.item.label
                                        : ''
                                }
                            />
                        </Field>
                        <FlagCheckbox
                            name="is_mandatory"
                            label="Mandatory"
                            defaultChecked={
                                dialog?.kind === 'edit-checklist'
                                    ? dialog.item.is_mandatory
                                    : true
                            }
                        />
                        <FlagCheckbox
                            name="is_active"
                            label="Active"
                            defaultChecked={
                                dialog?.kind === 'edit-checklist'
                                    ? dialog.item.is_active
                                    : true
                            }
                        />
                    </div>
                    </>
                )}
            </CrudFormDialog>

            <CrudFormDialog
                open={dialog?.kind === 'add-gas' || dialog?.kind === 'edit-gas'}
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                    }
                }}
                title={
                    dialog?.kind === 'edit-gas'
                        ? 'Edit gas channel'
                        : 'Add gas channel'
                }
                action={
                    dialog?.kind === 'edit-gas'
                        ? `${base}/gas-channels/${dialog.channel.uuid}`
                        : `${base}/gas-channels`
                }
                method={dialog?.kind === 'edit-gas' ? 'put' : 'post'}
                submitLabel="Save"
            >
                {() => (
                    <>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Channel code" htmlFor="gas_code">
                            <Input
                                id="gas_code"
                                name="channel_code"
                                required
                                pattern="[a-z][a-z0-9_]*"
                                placeholder="o2_pct"
                                defaultValue={
                                    dialog?.kind === 'edit-gas'
                                        ? dialog.channel.channel_code
                                        : ''
                                }
                            />
                        </Field>
                        <Field label="Label" htmlFor="gas_label">
                            <Input
                                id="gas_label"
                                name="label"
                                required
                                defaultValue={
                                    dialog?.kind === 'edit-gas'
                                        ? dialog.channel.label
                                        : ''
                                }
                            />
                        </Field>
                        <Field label="Unit" htmlFor="gas_unit">
                            <Input
                                id="gas_unit"
                                name="unit"
                                defaultValue={
                                    dialog?.kind === 'edit-gas'
                                        ? (dialog.channel.unit ?? '')
                                        : ''
                                }
                            />
                        </Field>
                        <Field label="Alarm below" htmlFor="gas_ab">
                            <Input
                                id="gas_ab"
                                name="alarm_below"
                                type="number"
                                step="any"
                                defaultValue={
                                    dialog?.kind === 'edit-gas'
                                        ? (dialog.channel.alarm_below ?? '')
                                        : ''
                                }
                            />
                        </Field>
                        <Field label="Alarm above" htmlFor="gas_aa">
                            <Input
                                id="gas_aa"
                                name="alarm_above"
                                type="number"
                                step="any"
                                defaultValue={
                                    dialog?.kind === 'edit-gas'
                                        ? (dialog.channel.alarm_above ?? '')
                                        : ''
                                }
                            />
                        </Field>
                        <Field label="Warn below" htmlFor="gas_wb">
                            <Input
                                id="gas_wb"
                                name="warn_below"
                                type="number"
                                step="any"
                                defaultValue={
                                    dialog?.kind === 'edit-gas'
                                        ? (dialog.channel.warn_below ?? '')
                                        : ''
                                }
                            />
                        </Field>
                        <Field label="Warn above" htmlFor="gas_wa">
                            <Input
                                id="gas_wa"
                                name="warn_above"
                                type="number"
                                step="any"
                                defaultValue={
                                    dialog?.kind === 'edit-gas'
                                        ? (dialog.channel.warn_above ?? '')
                                        : ''
                                }
                            />
                        </Field>
                    </div>
                    </>
                )}
            </CrudFormDialog>

            <CrudFormDialog
                open={
                    dialog?.kind === 'add-conflict' ||
                    dialog?.kind === 'edit-conflict'
                }
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                    }
                }}
                title={
                    dialog?.kind === 'edit-conflict'
                        ? 'Edit SIMOPS conflict'
                        : 'Add SIMOPS conflict'
                }
                action={
                    dialog?.kind === 'edit-conflict'
                        ? `${base}/conflicts/${dialog.conflict.uuid}`
                        : `${base}/conflicts`
                }
                method={dialog?.kind === 'edit-conflict' ? 'put' : 'post'}
                submitLabel="Save conflict"
                transform={(data) => ({
                    ...data,
                    conflicts_with_type_id: conflictsWithTypeId || null,
                    scope: conflictScope,
                    severity: conflictSeverity,
                })}
            >
                {({ errors }) => (
                    <div className="grid gap-3">
                        <Field label="Conflicts with" htmlFor="conflict_type">
                            <SearchableSelect
                                id="conflict_type"
                                required
                                value={conflictsWithTypeId}
                                onValueChange={setConflictsWithTypeId}
                                allowClear
                                clearLabel="Select type…"
                                placeholder="Select type…"
                                options={otherTypes.map((type) => ({
                                    value: String(type.id),
                                    label: `${type.name} (${type.code})`,
                                }))}
                            />
                            {errors.conflicts_with_type_id ? (
                                <p className="text-sm text-destructive">
                                    {errors.conflicts_with_type_id}
                                </p>
                            ) : null}
                        </Field>
                        <Field label="Scope" htmlFor="conflict_scope">
                            <SearchableSelect
                                id="conflict_scope"
                                required
                                value={conflictScope}
                                onValueChange={setConflictScope}
                                options={[
                                    {
                                        value: 'same_zone',
                                        label: 'Same zone',
                                    },
                                    {
                                        value: 'adjacent_zone',
                                        label: 'Adjacent zone',
                                    },
                                ]}
                            />
                            {errors.scope ? (
                                <p className="text-sm text-destructive">
                                    {errors.scope}
                                </p>
                            ) : null}
                        </Field>
                        <Field label="Severity" htmlFor="conflict_severity">
                            <SearchableSelect
                                id="conflict_severity"
                                required
                                value={conflictSeverity}
                                onValueChange={setConflictSeverity}
                                options={[
                                    { value: 'warn', label: 'Warn' },
                                    { value: 'block', label: 'Block' },
                                ]}
                            />
                            {errors.severity ? (
                                <p className="text-sm text-destructive">
                                    {errors.severity}
                                </p>
                            ) : null}
                        </Field>
                        <Field label="Note" htmlFor="conflict_note">
                            <Input
                                id="conflict_note"
                                name="note"
                                defaultValue={
                                    dialog?.kind === 'edit-conflict'
                                        ? (dialog.conflict.note ?? '')
                                        : ''
                                }
                            />
                            {errors.note ? (
                                <p className="text-sm text-destructive">
                                    {errors.note}
                                </p>
                            ) : null}
                        </Field>
                    </div>
                )}
            </CrudFormDialog>

            <CrudFormDialog
                open={
                    dialog?.kind === 'add-doc-req' ||
                    dialog?.kind === 'edit-doc-req'
                }
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                    }
                }}
                title={
                    dialog?.kind === 'edit-doc-req'
                        ? 'Edit document requirement'
                        : 'Add document requirement'
                }
                action={
                    dialog?.kind === 'edit-doc-req'
                        ? `${base}/document-requirements/${dialog.requirement.uuid}`
                        : `${base}/document-requirements`
                }
                method={dialog?.kind === 'edit-doc-req' ? 'put' : 'post'}
                submitLabel="Save requirement"
                transform={(data) => ({
                    ...data,
                    worker_document_type_id: docTypeId || null,
                    role_code: docRoleCode || null,
                })}
            >
                {({ errors }) => (
                    <div className="grid gap-3">
                        <Field label="Document type" htmlFor="doc_type">
                            <SearchableSelect
                                id="doc_type"
                                required
                                value={docTypeId}
                                onValueChange={setDocTypeId}
                                allowClear
                                clearLabel="Select document…"
                                placeholder="Select document…"
                                options={documentTypes.map((type) => ({
                                    value: String(type.id),
                                    label: `${type.name} (${type.code})`,
                                }))}
                            />
                            {errors.worker_document_type_id ? (
                                <p className="text-sm text-destructive">
                                    {errors.worker_document_type_id}
                                </p>
                            ) : null}
                        </Field>
                        <Field label="Role code (optional)" htmlFor="doc_role">
                            <SearchableSelect
                                id="doc_role"
                                value={docRoleCode}
                                onValueChange={setDocRoleCode}
                                allowClear
                                clearLabel="Any role"
                                placeholder="Any role"
                                options={permitType.roles.map((role) => ({
                                    value: role.role_code,
                                    label: `${role.label} (${role.role_code})`,
                                }))}
                            />
                            {errors.role_code ? (
                                <p className="text-sm text-destructive">
                                    {errors.role_code}
                                </p>
                            ) : null}
                        </Field>
                        <FlagCheckbox
                            name="is_mandatory"
                            label="Mandatory"
                            defaultChecked={
                                dialog?.kind === 'edit-doc-req'
                                    ? dialog.requirement.is_mandatory
                                    : true
                            }
                        />
                        <FlagCheckbox
                            name="must_be_verified"
                            label="Must be verified"
                            defaultChecked={
                                dialog?.kind === 'edit-doc-req'
                                    ? dialog.requirement.must_be_verified
                                    : true
                            }
                        />
                    </div>
                )}
            </CrudFormDialog>
        </>
    );
}

function Section({
    title,
    description,
    actionLabel,
    onAction,
    children,
}: {
    title: string;
    description: string;
    actionLabel: string;
    onAction: () => void;
    children: ReactNode;
}) {
    return (
        <div className="mb-8">
            <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 className="text-sm font-semibold tracking-tight text-text">
                        {title}
                    </h2>
                    <p className="text-xs text-text-dim">{description}</p>
                </div>
                <Button type="button" size="sm" onClick={onAction}>
                    {actionLabel}
                </Button>
            </div>
            {children}
        </div>
    );
}

function Field({
    label,
    htmlFor,
    className,
    children,
}: {
    label: string;
    htmlFor: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`grid gap-1 ${className ?? ''}`}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
        </div>
    );
}

function FlagCheckbox({
    name,
    label,
    defaultChecked = false,
}: {
    name: string;
    label: string;
    defaultChecked?: boolean;
}) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                name={name}
                value="1"
                defaultChecked={defaultChecked}
                className="size-4 rounded border border-input"
            />
            {label}
        </label>
    );
}

PermitTypeShow.layout = {
    breadcrumbs: [
        { title: 'Catalogue', href: '/workforce/permit-types' },
        { title: 'Permit types', href: '/workforce/permit-types' },
    ],
};
