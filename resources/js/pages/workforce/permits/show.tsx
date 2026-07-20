import type { RequestPayload } from '@inertiajs/core';
import { Form, Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    PermitDetail,
    PermitOption,
    PermitTypeChecklistItem,
    PermitTypeGasChannel,
    PermitTypeRole,
    WorkerOption,
    ZoneOption,
} from '@/types/permit';

type Props = {
    permit: PermitDetail;
    gasChannels: PermitTypeGasChannel[];
    checklistItems: PermitTypeChecklistItem[];
    zones: ZoneOption[];
    workers: WorkerOption[];
    typeRoles: PermitTypeRole[];
    allowsExtended: boolean;
    gasPhaseOptions: PermitOption[];
    canRequest: boolean;
    canUpdate: boolean;
    canIssue: boolean;
    canApprove: boolean;
    canGasTest: boolean;
};

type PersonnelRow = {
    worker_id: string;
    role_code: string;
};

const STATUS_TONE: Record<string, StatusPillTone> = {
    draft: 'neutral',
    pending_inspection: 'warn',
    pending_gas_test: 'warn',
    pending_approval: 'warn',
    pending_issue: 'accent',
    active: 'ok',
    suspended: 'crit',
    expired: 'neutral',
    closed: 'neutral',
    cancelled: 'neutral',
    rejected: 'crit',
};

function docTone(status: string): StatusPillTone {
    if (status === 'green') {
        return 'ok';
    }

    if (status === 'amber') {
        return 'warn';
    }

    return 'crit';
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

function checklistAnswered(
    checklist: Record<string, unknown> | null,
    code: string,
    id: number,
): boolean {
    const value = checklist?.[code] ?? checklist?.[String(id)];

    return (
        value === true ||
        value === 1 ||
        value === '1' ||
        value === 'true'
    );
}

export default function PermitShow({
    permit,
    gasChannels,
    checklistItems,
    zones,
    workers,
    typeRoles,
    allowsExtended,
    gasPhaseOptions,
    canRequest,
    canUpdate,
    canIssue,
    canApprove,
    canGasTest,
}: Props) {
    const [note, setNote] = useState('');
    const [gasReadings, setGasReadings] = useState<Record<string, string>>({});
    const [gasPhase, setGasPhase] = useState(
        gasPhaseOptions[0]?.value ?? 'pre_start',
    );
    const [personnel, setPersonnel] = useState<PersonnelRow[]>(() =>
        permit.personnel.length > 0
            ? permit.personnel.map((person) => ({
                  worker_id: String(person.worker_id),
                  role_code: person.role_code,
              }))
            : [{ worker_id: '', role_code: '' }],
    );
    const [checklist, setChecklist] = useState<Record<string, boolean>>(() => {
        const initial: Record<string, boolean> = {};

        for (const item of checklistItems) {
            initial[item.code] = checklistAnswered(
                permit.checklist,
                item.code,
                item.id,
            );
        }

        return initial;
    });

    const isEditable = canUpdate && ['draft', 'rejected'].includes(permit.status);

    const checklistComplete = useMemo(() => {
        const mandatory = checklistItems.filter((item) => item.is_mandatory);

        return mandatory.every((item) => checklist[item.code] === true);
    }, [checklist, checklistItems]);

    function postAction(url: string, data: RequestPayload = {}): void {
        router.post(url, data as RequestPayload, { preserveScroll: true });
    }

    function addPersonnelRow(): void {
        setPersonnel((rows) => [...rows, { worker_id: '', role_code: '' }]);
    }

    function removePersonnelRow(index: number): void {
        setPersonnel((rows) => rows.filter((_, i) => i !== index));
    }

    function updatePersonnelRow(
        index: number,
        field: keyof PersonnelRow,
        value: string,
    ): void {
        setPersonnel((rows) =>
            rows.map((row, i) =>
                i === index ? { ...row, [field]: value } : row,
            ),
        );
    }

    function toggleChecklistItem(code: string, checked: boolean): void {
        setChecklist((current) => ({ ...current, [code]: checked }));
    }

    async function loadGasSuggestion(): Promise<void> {
        const response = await fetch(
            `/workforce/permits/${permit.id}/gas-suggestion`,
        );
        const payload = (await response.json()) as {
            readings: Record<string, number>;
        };
        const next: Record<string, string> = {};

        for (const [key, value] of Object.entries(payload.readings ?? {})) {
            next[key] = String(value);
        }

        setGasReadings(next);
    }

    return (
        <>
            <Head title={permit.permit_number} />
            <div className="flex flex-col gap-4 p-4 md:p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow">{permit.type?.name ?? 'Permit'}</p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-text md:text-2xl">
                            {permit.permit_number}
                        </h1>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            <StatusPill
                                label={permit.status_label}
                                tone={STATUS_TONE[permit.status] ?? 'neutral'}
                            />
                            {permit.gas_test_required && (
                                <StatusPill label="Gas test required" tone="warn" />
                            )}
                        </div>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/workforce/permits">Back</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    {canRequest &&
                        ['draft', 'rejected'].includes(permit.status) && (
                            <Button
                                type="button"
                                onClick={() =>
                                    postAction(
                                        `/workforce/permits/${permit.id}/submit`,
                                    )
                                }
                            >
                                Submit
                            </Button>
                        )}
                    {canIssue && permit.status === 'pending_inspection' && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    postAction(
                                        `/workforce/permits/${permit.id}/inspection`,
                                        { as: 'issuer' },
                                    )
                                }
                            >
                                Inspect (issuer)
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    postAction(
                                        `/workforce/permits/${permit.id}/inspection`,
                                        { as: 'receiver' },
                                    )
                                }
                            >
                                Inspect (receiver)
                            </Button>
                        </>
                    )}
                    {canApprove && permit.status === 'pending_approval' && (
                        <Button
                            type="button"
                            onClick={() =>
                                postAction(
                                    `/workforce/permits/${permit.id}/approve`,
                                    {
                                        note: note || undefined,
                                    },
                                )
                            }
                        >
                            Approve
                        </Button>
                    )}
                    {canIssue && permit.status === 'pending_issue' && (
                        <Button
                            type="button"
                            onClick={() =>
                                postAction(
                                    `/workforce/permits/${permit.id}/issue`,
                                    {
                                        note: note || undefined,
                                    },
                                )
                            }
                        >
                            Issue
                        </Button>
                    )}
                    {canGasTest && permit.status === 'pending_gas_test' && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void loadGasSuggestion()}
                        >
                            Load gas suggestion
                        </Button>
                    )}
                    {canIssue && permit.status === 'active' && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    postAction(
                                        `/workforce/permits/${permit.id}/renew`,
                                    )
                                }
                            >
                                Renew
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() =>
                                    postAction(
                                        `/workforce/permits/${permit.id}/suspend`,
                                        {
                                            note: note || 'Suspended',
                                        },
                                    )
                                }
                            >
                                Suspend
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    postAction(
                                        `/workforce/permits/${permit.id}/close`,
                                        {
                                            note: note || 'Work complete',
                                        },
                                    )
                                }
                            >
                                Close
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() =>
                                    postAction(
                                        `/workforce/permits/${permit.id}/cancel`,
                                        {
                                            note: note || 'Cancelled',
                                        },
                                    )
                                }
                            >
                                Cancel
                            </Button>
                        </>
                    )}
                    {canIssue && permit.status === 'suspended' && (
                        <Button
                            type="button"
                            onClick={() =>
                                postAction(
                                    `/workforce/permits/${permit.id}/resume`,
                                )
                            }
                        >
                            Resume
                        </Button>
                    )}
                    {canIssue &&
                        !['active', 'closed', 'cancelled', 'rejected'].includes(
                            permit.status,
                        ) && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() =>
                                    postAction(
                                        `/workforce/permits/${permit.id}/reject`,
                                        {
                                            note: note || 'Rejected',
                                        },
                                    )
                                }
                            >
                                Reject
                            </Button>
                        )}
                </div>

                {isEditable ? (
                    <Form
                        action={`/workforce/permits/${permit.id}`}
                        method="put"
                        className="space-y-4 rounded-lg border border-border p-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <h2 className="text-sm font-medium">Edit draft</h2>

                                <div className="grid gap-2">
                                    <Label htmlFor="zone_id">Zone</Label>
                                    <select
                                        id="zone_id"
                                        name="zone_id"
                                        defaultValue={permit.zone?.id ?? ''}
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
                                    <Label htmlFor="task_description">Task</Label>
                                    <textarea
                                        id="task_description"
                                        name="task_description"
                                        rows={4}
                                        required
                                        defaultValue={permit.task_description}
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    />
                                    {errors.task_description && (
                                        <p className="text-sm text-destructive">
                                            {errors.task_description}
                                        </p>
                                    )}
                                </div>

                                {allowsExtended && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="is_extended"
                                            value="1"
                                            defaultChecked={permit.is_extended}
                                            className="rounded border-input"
                                        />
                                        Extended permit (requires approver)
                                    </label>
                                )}

                                <div className="space-y-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <Label>Personnel</Label>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={addPersonnelRow}
                                        >
                                            Add row
                                        </Button>
                                    </div>
                                    {personnel.map((row, index) => (
                                        <div
                                            key={index}
                                            className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-[1fr_1fr_auto]"
                                        >
                                            <div className="grid gap-1">
                                                <Label>Worker</Label>
                                                <select
                                                    name={`personnel[${index}][worker_id]`}
                                                    value={row.worker_id}
                                                    onChange={(event) =>
                                                        updatePersonnelRow(
                                                            index,
                                                            'worker_id',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                                >
                                                    <option value="">—</option>
                                                    {workers.map((worker) => (
                                                        <option
                                                            key={worker.id}
                                                            value={worker.id}
                                                        >
                                                            {worker.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label>Role</Label>
                                                <select
                                                    name={`personnel[${index}][role_code]`}
                                                    value={row.role_code}
                                                    onChange={(event) =>
                                                        updatePersonnelRow(
                                                            index,
                                                            'role_code',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                                >
                                                    <option value="">—</option>
                                                    {typeRoles.map((role) => (
                                                        <option
                                                            key={role.role_code}
                                                            value={role.role_code}
                                                        >
                                                            {role.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            {personnel.length > 1 && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    className="self-end"
                                                    onClick={() =>
                                                        removePersonnelRow(index)
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                    {errors.personnel && (
                                        <p className="text-sm text-destructive">
                                            {errors.personnel}
                                        </p>
                                    )}
                                </div>

                                {checklistItems.length > 0 && (
                                    <div className="space-y-3">
                                        <Label>Checklist / JSA</Label>
                                        <ul className="space-y-2">
                                            {checklistItems.map((item) => (
                                                <li
                                                    key={item.code}
                                                    className="flex items-start gap-2 rounded-md border border-border px-3 py-2 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        id={`checklist-${item.code}`}
                                                        name={`checklist[${item.code}]`}
                                                        value="1"
                                                        checked={
                                                            checklist[item.code] ??
                                                            false
                                                        }
                                                        onChange={(event) =>
                                                            toggleChecklistItem(
                                                                item.code,
                                                                event.target
                                                                    .checked,
                                                            )
                                                        }
                                                        className="mt-0.5 rounded border-input"
                                                    />
                                                    <label
                                                        htmlFor={`checklist-${item.code}`}
                                                        className="flex-1"
                                                    >
                                                        {item.label}
                                                        {item.is_mandatory && (
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                (required)
                                                            </span>
                                                        )}
                                                    </label>
                                                </li>
                                            ))}
                                        </ul>
                                        {errors.checklist && (
                                            <p className="text-sm text-destructive">
                                                {errors.checklist}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        Save changes
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Panel title="Task">
                            <p className="text-sm text-text">
                                {permit.task_description}
                            </p>
                            <dl className="mt-4 grid gap-2 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">Zone</dt>
                                    <dd>{permit.zone?.name ?? '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Receiver
                                    </dt>
                                    <dd>{permit.receiver?.name ?? '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Valid to
                                    </dt>
                                    <dd>{formatDate(permit.valid_to)}</dd>
                                </div>
                            </dl>
                        </Panel>

                        <Panel title="Crew">
                            {permit.personnel.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No personnel assigned.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {permit.personnel.map((person) => (
                                        <li
                                            key={person.id}
                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                                        >
                                            <span>
                                                {person.worker_label ??
                                                    `#${person.worker_id}`}{' '}
                                                · {person.role_code}
                                            </span>
                                            <StatusPill
                                                label={
                                                    person.document_status.status
                                                }
                                                tone={docTone(
                                                    person.document_status
                                                        .status,
                                                )}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    </div>
                )}

                {checklistItems.length > 0 && !isEditable && (
                    <Panel title="Checklist / JSA">
                        <ul className="space-y-2 text-sm">
                            {checklistItems.map((item) => {
                                const answered = checklistAnswered(
                                    permit.checklist,
                                    item.code,
                                    item.id,
                                );

                                return (
                                    <li
                                        key={item.code}
                                        className="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2"
                                    >
                                        <span>{item.label}</span>
                                        <StatusPill
                                            label={answered ? 'Checked' : 'Open'}
                                            tone={answered ? 'ok' : 'warn'}
                                        />
                                    </li>
                                );
                            })}
                        </ul>
                        {!checklistComplete &&
                            ['draft', 'rejected'].includes(permit.status) && (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Complete all mandatory checklist items before
                                    submitting.
                                </p>
                            )}
                    </Panel>
                )}

                {canGasTest && permit.status === 'pending_gas_test' && (
                    <Panel title="Record gas test">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {gasChannels.map((channel) => (
                                <div
                                    key={channel.channel_code}
                                    className="grid gap-1"
                                >
                                    <Label htmlFor={`gas-${channel.channel_code}`}>
                                        {channel.label}
                                        {channel.unit ? ` (${channel.unit})` : ''}
                                    </Label>
                                    <Input
                                        id={`gas-${channel.channel_code}`}
                                        value={
                                            gasReadings[channel.channel_code] ??
                                            ''
                                        }
                                        onChange={(event) =>
                                            setGasReadings((current) => ({
                                                ...current,
                                                [channel.channel_code]:
                                                    event.target.value,
                                            }))
                                        }
                                    />
                                    {(channel.alarm_below !== null ||
                                        channel.alarm_above !== null) && (
                                        <p className="text-xs text-muted-foreground">
                                            {channel.alarm_below !== null &&
                                                `min ${channel.alarm_below}`}
                                            {channel.alarm_below !== null &&
                                                channel.alarm_above !== null &&
                                                ' · '}
                                            {channel.alarm_above !== null &&
                                                `max ${channel.alarm_above}`}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                        {gasChannels.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No gas channels configured for this permit type.
                            </p>
                        )}
                        <div className="mt-3 grid gap-2 sm:max-w-xs">
                            <Label htmlFor="gas-phase">Phase</Label>
                            <select
                                id="gas-phase"
                                value={gasPhase}
                                onChange={(event) =>
                                    setGasPhase(event.target.value)
                                }
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                {gasPhaseOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Button
                            type="button"
                            className="mt-3"
                            disabled={gasChannels.length === 0}
                            onClick={() =>
                                postAction(
                                    `/workforce/permits/${permit.id}/gas-tests`,
                                    {
                                        readings: gasReadings,
                                        source: 'manual',
                                        phase: gasPhase,
                                    },
                                )
                            }
                        >
                            Record test
                        </Button>
                    </Panel>
                )}

                <Panel title="Gas tests">
                    {permit.gas_tests.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No gas tests recorded.
                        </p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {permit.gas_tests.map((test) => (
                                <li
                                    key={test.id}
                                    className="rounded-md border border-border px-3 py-2"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StatusPill
                                            label={test.result_label}
                                            tone={
                                                test.result === 'pass'
                                                    ? 'ok'
                                                    : 'crit'
                                            }
                                        />
                                        <span>{formatDate(test.tested_at)}</span>
                                        <span className="text-muted-foreground">
                                            {test.phase_label} ·{' '}
                                            {test.source_label}
                                        </span>
                                    </div>
                                    {Object.keys(test.readings).length > 0 && (
                                        <dl className="mt-2 grid gap-1 text-xs text-muted-foreground sm:grid-cols-2">
                                            {Object.entries(test.readings).map(
                                                ([code, value]) => (
                                                    <div
                                                        key={code}
                                                        className="flex justify-between gap-2"
                                                    >
                                                        <dt>{code}</dt>
                                                        <dd>{String(value)}</dd>
                                                    </div>
                                                ),
                                            )}
                                        </dl>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Approvals">
                        {permit.approvals.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No approvals yet.
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {permit.approvals.map((approval) => (
                                    <li key={approval.id}>
                                        <span className="font-medium">
                                            {approval.action_label}
                                        </span>{' '}
                                        · {approval.user_name ?? 'System'} ·{' '}
                                        {formatDate(approval.signed_at)}
                                        {approval.note ? (
                                            <p className="text-muted-foreground">
                                                {approval.note}
                                            </p>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel title="Events">
                        {permit.events.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No events logged.
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {permit.events.map((event) => (
                                    <li key={event.id}>
                                        <span className="font-medium">
                                            {event.event}
                                        </span>{' '}
                                        · {event.user_name ?? 'System'} ·{' '}
                                        {formatDate(event.occurred_at)}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                {(canIssue || canApprove) && (
                    <Panel title="Action note">
                        <Input
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            placeholder="Optional note for approve / issue / close…"
                        />
                    </Panel>
                )}
            </div>
        </>
    );
}

PermitShow.layout = {
    breadcrumbs: [
        { title: 'Workforce', href: '/workforce/workers' },
        { title: 'Permits', href: '/workforce/permits' },
    ],
};
