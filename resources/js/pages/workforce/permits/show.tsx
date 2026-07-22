import type { RequestPayload } from '@inertiajs/core';
import { Form, Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import {
    permitTypeBarClass,
    permitTypeSoftClass,
} from '@/lib/permit-colours';
import { cn } from '@/lib/utils';
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
    canInspect: boolean;
};

type PersonnelRow = {
    worker_id: string;
    role_code: string;
};

type FlowStep = {
    id: string;
    label: string;
    status: 'done' | 'current' | 'upcoming' | 'skipped';
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

const ACTION_TONE_CLASS: Record<
    'ok' | 'warn' | 'crit' | 'accent' | 'neutral',
    string
> = {
    ok: 'border-[color:var(--ok)] bg-[color:var(--ok-bg)]',
    warn: 'border-[color:var(--warn)] bg-[color:var(--warn-bg)]',
    crit: 'border-[color:var(--crit)] bg-[color:var(--crit-bg)]',
    accent: 'border-[color:var(--accent)] bg-[color:var(--accent-dim)]',
    neutral: 'border-border bg-surface-2',
};

const ACTION_TITLE_CLASS: Record<
    'ok' | 'warn' | 'crit' | 'accent' | 'neutral',
    string
> = {
    ok: 'text-[color:var(--ok)]',
    warn: 'text-[color:var(--warn)]',
    crit: 'text-[color:var(--crit)]',
    accent: 'text-[color:var(--accent)]',
    neutral: 'text-text',
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

function FactTile({
    label,
    value,
    tone = 'neutral',
}: {
    label: string;
    value: string;
    tone?: 'ok' | 'warn' | 'crit' | 'accent' | 'neutral';
}) {
    return (
        <div
            className={cn(
                'rounded-[var(--radius)] border border-border bg-surface px-3 py-2.5 shadow-[var(--shadow-card)]',
                tone === 'ok' && 'border-[color:var(--ok)]/35',
                tone === 'warn' && 'border-[color:var(--warn)]/35',
                tone === 'crit' && 'border-[color:var(--crit)]/35',
                tone === 'accent' && 'border-[color:var(--accent)]/35',
            )}
        >
            <p className="eyebrow">{label}</p>
            <p className="mt-1 truncate text-sm font-semibold text-text">
                {value}
            </p>
        </div>
    );
}

function SectionBadge({
    step,
    tone = 'accent',
}: {
    step: number;
    tone?: 'ok' | 'warn' | 'accent';
}) {
    return (
        <span
            className={cn(
                'inline-flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                tone === 'accent' &&
                    'bg-[color:var(--accent-dim)] text-[color:var(--accent)]',
                tone === 'ok' &&
                    'bg-[color:var(--ok-bg)] text-[color:var(--ok)]',
                tone === 'warn' &&
                    'bg-[color:var(--warn-bg)] text-[color:var(--warn)]',
            )}
        >
            {step}
        </span>
    );
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

function eligibilityFor(
    worker: WorkerOption | undefined,
    permitTypeId: number | undefined,
    roleCode: string,
): { ready: boolean; missing_labels: string[] } | null {
    if (!worker || !permitTypeId || !roleCode) {
        return null;
    }

    return (
        worker.role_eligibility?.[`${permitTypeId}:${roleCode}`] ?? {
            ready: true,
            missing: [],
            missing_labels: [],
        }
    );
}

function buildFlowSteps(permit: PermitDetail): FlowStep[] {
    const status = permit.status;
    const needsInspection =
        permit.joint_inspection?.required ??
        permit.type?.requires_joint_inspection ??
        false;
    const needsGas = permit.gas_test_required;
    const needsApproval =
        permit.is_extended || (permit.type?.requires_approver ?? false);

    const terminal = ['closed', 'cancelled', 'rejected', 'expired'].includes(
        status,
    );
    const activeLike = ['active', 'suspended'].includes(status);

    const order: string[] = ['draft'];

    if (needsInspection) {
        order.push('inspection');
    }

    if (needsGas) {
        order.push('gas');
    }

    if (needsApproval) {
        order.push('approval');
    }

    order.push('issue', 'active');

    const statusToStep: Record<string, string> = {
        draft: 'draft',
        rejected: 'draft',
        pending_inspection: 'inspection',
        pending_gas_test: 'gas',
        pending_approval: 'approval',
        pending_issue: 'issue',
        active: 'active',
        suspended: 'active',
        closed: 'active',
        cancelled: 'active',
        expired: 'active',
    };

    const currentId = statusToStep[status] ?? 'draft';
    const currentIndex = order.indexOf(currentId);

    return order.map((id, index) => {
        const labelMap: Record<string, string> = {
            draft: 'Prepare',
            inspection: 'Inspect',
            gas: 'Gas test',
            approval: 'Approve',
            issue: 'Issue',
            active: 'Active',
        };

        if (terminal && id === 'active') {
            return { id, label: permit.status_label, status: 'done' };
        }

        if (activeLike && id === 'active') {
            return { id, label: 'Active', status: 'current' };
        }

        if (!needsInspection && id === 'inspection') {
            return { id, label: labelMap[id], status: 'skipped' };
        }

        if (!needsGas && id === 'gas') {
            return { id, label: labelMap[id], status: 'skipped' };
        }

        if (!needsApproval && id === 'approval') {
            return { id, label: labelMap[id], status: 'skipped' };
        }

        if (index < currentIndex) {
            return { id, label: labelMap[id], status: 'done' };
        }

        if (index === currentIndex) {
            return { id, label: labelMap[id], status: 'current' };
        }

        return { id, label: labelMap[id], status: 'upcoming' };
    });
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
    canInspect,
}: Props) {
    const [note, setNote] = useState('');
    const [gasReadings, setGasReadings] = useState<Record<string, string>>({});
    const [gasPhase, setGasPhase] = useState(
        gasPhaseOptions[0]?.value ?? 'pre_start',
    );
    const [zoneId, setZoneId] = useState(
        permit.zone?.id ? String(permit.zone.id) : '',
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
    const permitTypeId = permit.type?.id;
    const flowSteps = useMemo(() => buildFlowSteps(permit), [permit]);

    const checklistComplete = useMemo(() => {
        const mandatory = checklistItems.filter((item) => item.is_mandatory);

        return mandatory.every((item) => checklist[item.code] === true);
    }, [checklist, checklistItems]);

    const roleShortfalls = useMemo(() => {
        const counts: Record<string, number> = {};

        for (const row of personnel) {
            if (!row.role_code || !row.worker_id) {
                continue;
            }

            counts[row.role_code] = (counts[row.role_code] ?? 0) + 1;
        }

        return typeRoles
            .filter((role) => role.is_mandatory)
            .filter((role) => (counts[role.role_code] ?? 0) < role.min_count)
            .map(
                (role) =>
                    `${role.label} (need ${role.min_count}, have ${counts[role.role_code] ?? 0})`,
            );
    }, [personnel, typeRoles]);

    const documentBlockers = useMemo(() => {
        if (isEditable) {
            return personnel
                .filter((row) => row.worker_id && row.role_code)
                .flatMap((row) => {
                    const worker = workers.find(
                        (item) => String(item.id) === row.worker_id,
                    );
                    const eligibility = eligibilityFor(
                        worker,
                        permitTypeId,
                        row.role_code,
                    );

                    if (!eligibility || eligibility.ready) {
                        return [];
                    }

                    return [
                        `${worker?.label ?? 'Worker'}: missing ${eligibility.missing_labels.join(', ') || 'documents'}`,
                    ];
                });
        }

        return permit.personnel
            .filter((person) => person.document_status.status === 'red')
            .map(
                (person) =>
                    `${person.worker_label ?? `#${person.worker_id}`}: missing ${(person.document_status.missing ?? []).join(', ') || 'documents'}`,
            );
    }, [isEditable, permit.personnel, personnel, permitTypeId, workers]);

    const blockers = useMemo(() => {
        if (!['draft', 'rejected'].includes(permit.status)) {
            return [] as string[];
        }

        const items: string[] = [];

        if (!checklistComplete && checklistItems.some((item) => item.is_mandatory)) {
            items.push('Complete all mandatory checklist / JSA items');
        }

        if (roleShortfalls.length > 0) {
            items.push(`Assign mandatory crew: ${roleShortfalls.join('; ')}`);
        }

        if (documentBlockers.length > 0) {
            items.push(...documentBlockers);
        }

        if (personnel.every((row) => !row.worker_id || !row.role_code)) {
            items.push('Assign at least one crew member');
        }

        return items;
    }, [
        checklistComplete,
        checklistItems,
        documentBlockers,
        permit.status,
        personnel,
        roleShortfalls,
    ]);

    const canSubmit =
        canRequest &&
        ['draft', 'rejected'].includes(permit.status) &&
        blockers.length === 0;

    const nextAction = useMemo(() => {
        switch (permit.status) {
            case 'draft':
            case 'rejected':
                return {
                    title: 'Prepare & submit',
                    detail:
                        blockers.length > 0
                            ? 'Finish the items below, save, then submit for the next gate.'
                            : 'Checklist and crew look ready. Submit to start inspection / gas / issue.',
                    tone: blockers.length > 0 ? ('warn' as const) : ('ok' as const),
                };
            case 'pending_inspection':
                return {
                    title: 'Joint site inspection',
                    detail: `Issuer ${permit.joint_inspection?.issuer_signed ? 'signed' : 'pending'} · Receiver ${permit.joint_inspection?.receiver_signed ? 'signed' : 'pending'}. Both must sign before work can proceed.`,
                    tone: 'warn' as const,
                };
            case 'pending_gas_test':
                return {
                    title: 'Atmospheric gas test',
                    detail: 'Record a passing pre-start gas test for the configured channels.',
                    tone: 'warn' as const,
                };
            case 'pending_approval':
                return {
                    title: 'Approver sign-off',
                    detail: 'Extended or high-risk type — approver must authorize before issue.',
                    tone: 'warn' as const,
                };
            case 'pending_issue':
                return {
                    title: 'Ready to issue',
                    detail: 'Issuer authorizes the permit and starts the validity window.',
                    tone: 'accent' as const,
                };
            case 'active':
                return {
                    title: 'Permit active',
                    detail: `Valid to ${formatDate(permit.valid_to)}. Renew, suspend, or close when work ends.`,
                    tone: 'ok' as const,
                };
            case 'suspended':
                return {
                    title: 'Stop work — suspended',
                    detail: 'Resume only when conditions are safe again.',
                    tone: 'crit' as const,
                };
            default:
                return {
                    title: permit.status_label,
                    detail: 'This permit is no longer in the live authorization path.',
                    tone: 'neutral' as const,
                };
        }
    }, [blockers.length, permit]);

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
            rows.map((row, i) => {
                if (i !== index) {
                    return row;
                }

                if (field === 'role_code') {
                    return { ...row, role_code: value, worker_id: '' };
                }

                return { ...row, [field]: value };
            }),
        );
    }

    function workersForRole(roleCode: string): WorkerOption[] {
        if (!roleCode || !permitTypeId) {
            return workers;
        }

        return workers.filter((worker) => {
            const eligibility = eligibilityFor(worker, permitTypeId, roleCode);

            return eligibility?.ready ?? true;
        });
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

    const needsNote =
        canIssue ||
        canApprove ||
        ['pending_issue', 'pending_approval', 'active', 'suspended'].includes(
            permit.status,
        );

    return (
        <>
            <Head title={permit.permit_number} />
            <div className="mx-auto flex max-w-6xl flex-col gap-4 p-4 md:p-5">
                <header className="overflow-hidden rounded-[var(--radius)] border border-border bg-surface shadow-[var(--shadow-card)]">
                    <div
                        className={cn(
                            'h-1.5 w-full',
                            permitTypeBarClass(permit.type?.colour_token),
                        )}
                        aria-hidden
                    />
                    <div className="flex flex-wrap items-start justify-between gap-4 p-4 md:p-5">
                        <div className="min-w-0 space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <span
                                    className={cn(
                                        'inline-flex items-center rounded-pill px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase',
                                        permitTypeSoftClass(
                                            permit.type?.colour_token,
                                        ),
                                    )}
                                >
                                    {permit.type?.name ?? 'Permit'}
                                </span>
                                {permit.type?.sa_form_code ? (
                                    <span className="font-mono text-[11px] text-text-faint">
                                        {permit.type.sa_form_code}
                                    </span>
                                ) : null}
                            </div>
                            <h1 className="font-display text-2xl font-semibold tracking-tight text-text md:text-3xl">
                                {permit.permit_number}
                            </h1>
                            <div className="flex flex-wrap gap-1.5">
                                <StatusPill
                                    label={permit.status_label}
                                    tone={
                                        STATUS_TONE[permit.status] ?? 'neutral'
                                    }
                                />
                                {permit.gas_test_required ? (
                                    <StatusPill
                                        label="Gas test required"
                                        tone="warn"
                                    />
                                ) : null}
                                {permit.is_extended ? (
                                    <StatusPill
                                        label="Extended"
                                        tone="accent"
                                    />
                                ) : null}
                            </div>
                            <p className="text-sm text-text-dim">
                                {permit.zone?.name ?? 'No zone'}
                                {permit.work_order ? (
                                    <>
                                        {' · '}
                                        <Link
                                            href={`/workforce/work-orders/${permit.work_order.id}`}
                                            className="text-[color:var(--accent)] underline-offset-2 hover:underline"
                                        >
                                            {permit.work_order.reference}
                                        </Link>
                                    </>
                                ) : null}
                            </p>
                        </div>
                        <Button asChild variant="outline">
                            <Link href="/workforce/permits">All permits</Link>
                        </Button>
                    </div>

                    <nav
                        aria-label="Permit progress"
                        className="border-t border-border bg-surface-2/60 px-4 py-4 md:px-5"
                    >
                        <ol className="flex flex-wrap items-center gap-y-3">
                            {flowSteps.map((step, index) => (
                                <li
                                    key={step.id}
                                    className="flex min-w-0 items-center"
                                >
                                    {index > 0 ? (
                                        <span
                                            className={cn(
                                                'mx-1.5 h-0.5 w-4 shrink-0 rounded-full sm:mx-2 sm:w-6',
                                                step.status === 'done' ||
                                                    step.status === 'current'
                                                    ? 'bg-[color:var(--ok)]'
                                                    : 'bg-border',
                                            )}
                                            aria-hidden
                                        />
                                    ) : null}
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={cn(
                                                'flex size-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                                step.status === 'current' &&
                                                    'bg-[color:var(--accent)] text-white shadow-[0_0_0_3px_var(--accent-dim)]',
                                                step.status === 'done' &&
                                                    'bg-[color:var(--ok)] text-[#0b0f14]',
                                                step.status === 'upcoming' &&
                                                    'border border-border bg-surface text-text-dim',
                                                step.status === 'skipped' &&
                                                    'border border-dashed border-border bg-transparent text-text-faint',
                                            )}
                                        >
                                            {step.status === 'done'
                                                ? '✓'
                                                : index + 1}
                                        </span>
                                        <span
                                            className={cn(
                                                'text-xs font-semibold',
                                                step.status === 'current' &&
                                                    'text-[color:var(--accent)]',
                                                step.status === 'done' &&
                                                    'text-[color:var(--ok)]',
                                                step.status === 'upcoming' &&
                                                    'text-text-dim',
                                                step.status === 'skipped' &&
                                                    'text-text-faint line-through',
                                            )}
                                        >
                                            {step.label}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </nav>
                </header>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FactTile
                        label="Zone"
                        value={permit.zone?.name ?? '—'}
                        tone="accent"
                    />
                    <FactTile
                        label="Valid to"
                        value={formatDate(permit.valid_to)}
                        tone={
                            permit.status === 'active'
                                ? 'ok'
                                : permit.status === 'suspended'
                                  ? 'crit'
                                  : 'neutral'
                        }
                    />
                    <FactTile
                        label="Crew"
                        value={`${isEditable ? personnel.filter((row) => row.worker_id).length : permit.personnel.length} assigned`}
                        tone={
                            roleShortfalls.length > 0 ||
                            documentBlockers.length > 0
                                ? 'warn'
                                : 'ok'
                        }
                    />
                    <FactTile
                        label="Gas pack"
                        value={
                            permit.gas_test_required
                                ? `${permit.gas_tests.length} test${permit.gas_tests.length === 1 ? '' : 's'}`
                                : 'Not required'
                        }
                        tone={
                            permit.gas_test_required
                                ? permit.gas_tests.some(
                                      (test) => test.result === 'pass',
                                  )
                                    ? 'ok'
                                    : 'warn'
                                : 'neutral'
                        }
                    />
                </div>

                <section
                    className={cn(
                        'rounded-[var(--radius)] border-l-4 p-4 shadow-[var(--shadow-card)] md:p-5',
                        ACTION_TONE_CLASS[nextAction.tone],
                    )}
                >
                    <p className="eyebrow">Next action</p>
                    <h2
                        className={cn(
                            'mt-1 font-display text-lg font-semibold tracking-tight',
                            ACTION_TITLE_CLASS[nextAction.tone],
                        )}
                    >
                        {nextAction.title}
                    </h2>
                    <p className="mt-1 text-sm text-text-dim">
                        {nextAction.detail}
                    </p>

                    {blockers.length > 0 ? (
                        <ul className="mt-3 space-y-1.5 text-sm">
                            {blockers.map((blocker) => (
                                <li
                                    key={blocker}
                                    className="rounded-md border border-[color:var(--warn)]/35 bg-surface/70 px-3 py-2 text-text"
                                >
                                    {blocker}
                                </li>
                            ))}
                        </ul>
                    ) : null}

                    <div className="mt-4 flex flex-wrap gap-2">
                        {canSubmit && (
                            <Button
                                type="button"
                                onClick={() =>
                                    postAction(
                                        `/workforce/permits/${permit.id}/submit`,
                                    )
                                }
                            >
                                Submit for authorization
                            </Button>
                        )}

                        {canInspect &&
                            permit.status === 'pending_inspection' && (
                                <>
                                    {canIssue &&
                                        !permit.joint_inspection
                                            ?.issuer_signed && (
                                            <Button
                                                type="button"
                                                onClick={() =>
                                                    postAction(
                                                        `/workforce/permits/${permit.id}/inspection`,
                                                        { as: 'issuer' },
                                                    )
                                                }
                                            >
                                                Sign as issuer
                                            </Button>
                                        )}
                                    {canRequest &&
                                        !permit.joint_inspection
                                            ?.receiver_signed && (
                                            <Button
                                                type="button"
                                                variant={
                                                    canIssue
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                                onClick={() =>
                                                    postAction(
                                                        `/workforce/permits/${permit.id}/inspection`,
                                                        { as: 'receiver' },
                                                    )
                                                }
                                            >
                                                Sign as receiver
                                            </Button>
                                        )}
                                    {permit.joint_inspection?.issuer_signed &&
                                        permit.joint_inspection
                                            ?.receiver_signed && (
                                            <StatusPill
                                                label="Both parties signed"
                                                tone="ok"
                                            />
                                        )}
                                </>
                            )}

                        {canApprove &&
                            permit.status === 'pending_approval' && (
                                <Button
                                    type="button"
                                    onClick={() =>
                                        postAction(
                                            `/workforce/permits/${permit.id}/approve`,
                                            { note: note || undefined },
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
                                        { note: note || undefined },
                                    )
                                }
                            >
                                Issue permit
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
                                    variant="outline"
                                    onClick={() =>
                                        postAction(
                                            `/workforce/permits/${permit.id}/close`,
                                            {
                                                note:
                                                    note || 'Work complete',
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
                            ![
                                'active',
                                'closed',
                                'cancelled',
                                'rejected',
                                'expired',
                            ].includes(permit.status) && (
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

                    {needsNote && (
                        <div className="mt-3 grid gap-1 sm:max-w-md">
                            <Label htmlFor="action-note">Action note</Label>
                            <Input
                                id="action-note"
                                value={note}
                                onChange={(event) => setNote(event.target.value)}
                                placeholder="Optional note for approve / issue / close…"
                                className="bg-surface"
                            />
                        </div>
                    )}
                </section>

                {isEditable ? (
                    <Form
                        action={`/workforce/permits/${permit.id}`}
                        method="put"
                        className="space-y-6"
                        transform={(data) => ({
                            ...data,
                            zone_id: zoneId || null,
                            personnel: personnel
                                .filter(
                                    (row) =>
                                        row.worker_id !== '' ||
                                        row.role_code !== '',
                                )
                                .map((row) => ({
                                    worker_id: row.worker_id,
                                    role_code: row.role_code,
                                })),
                        })}
                    >
                        {({ processing, errors }) => (
                            <>
                                <section className="space-y-4 rounded-[var(--radius)] border border-border border-l-[3px] border-l-[color:var(--accent)] bg-surface p-4 shadow-[var(--shadow-card)]">
                                    <div className="flex items-center gap-2.5">
                                        <SectionBadge step={1} tone="accent" />
                                        <div>
                                            <p className="eyebrow">Job</p>
                                            <h2 className="text-sm font-semibold text-text">
                                                Task & location
                                            </h2>
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="zone_id">Zone</Label>
                                        <SearchableSelect
                                            id="zone_id"
                                            value={zoneId}
                                            onValueChange={setZoneId}
                                            allowClear
                                            clearLabel="—"
                                            placeholder="—"
                                            options={zones.map((zone) => ({
                                                value: String(zone.id),
                                                label: zone.name,
                                            }))}
                                        />
                                        {errors.zone_id && (
                                            <p className="text-sm text-destructive">
                                                {errors.zone_id}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="task_description">
                                            Task
                                        </Label>
                                        <textarea
                                            id="task_description"
                                            name="task_description"
                                            rows={4}
                                            required
                                            defaultValue={
                                                permit.task_description
                                            }
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
                                                defaultChecked={
                                                    permit.is_extended
                                                }
                                                className="rounded border-input"
                                            />
                                            Extended permit (requires
                                            approver)
                                        </label>
                                    )}
                                </section>

                                <section className="space-y-4 rounded-[var(--radius)] border border-border border-l-[3px] border-l-[color:var(--warn)] bg-surface p-4 shadow-[var(--shadow-card)]">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-2.5">
                                            <SectionBadge step={2} tone="warn" />
                                            <div>
                                                <p className="eyebrow">Crew</p>
                                                <h2 className="text-sm font-semibold text-text">
                                                    Roles first, then ready
                                                    workers
                                                </h2>
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={addPersonnelRow}
                                        >
                                            Add
                                        </Button>
                                    </div>

                                    {typeRoles.length === 0 && (
                                        <p className="rounded-md border border-[color:var(--warn)]/35 bg-[color:var(--warn-bg)] px-3 py-2 text-sm">
                                            No crew roles configured for this
                                            permit type. Add them under
                                            Catalogue → Crew roles.
                                        </p>
                                    )}

                                    {personnel.map((row, index) => {
                                        const readyWorkers = workersForRole(
                                            row.role_code,
                                        );
                                        const selected = workers.find(
                                            (worker) =>
                                                String(worker.id) ===
                                                row.worker_id,
                                        );
                                        const eligibility = eligibilityFor(
                                            selected,
                                            permitTypeId,
                                            row.role_code,
                                        );

                                        return (
                                            <div
                                                key={index}
                                                className="grid gap-2 rounded-md border border-border bg-surface-2/40 p-3 sm:grid-cols-[1fr_1fr_auto]"
                                            >
                                                <div className="grid gap-1">
                                                    <Label>Role</Label>
                                                    <SearchableSelect
                                                        value={row.role_code}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updatePersonnelRow(
                                                                index,
                                                                'role_code',
                                                                value,
                                                            )
                                                        }
                                                        allowClear
                                                        clearLabel="—"
                                                        placeholder="—"
                                                        options={typeRoles.map(
                                                            (role) => ({
                                                                value: role.role_code,
                                                                label: `${role.label}${
                                                                    role.is_mandatory
                                                                        ? ' *'
                                                                        : ''
                                                                }`,
                                                            }),
                                                        )}
                                                    />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label>Worker</Label>
                                                    <SearchableSelect
                                                        value={row.worker_id}
                                                        disabled={
                                                            !row.role_code
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updatePersonnelRow(
                                                                index,
                                                                'worker_id',
                                                                value,
                                                            )
                                                        }
                                                        allowClear
                                                        clearLabel={
                                                            row.role_code
                                                                ? readyWorkers.length ===
                                                                  0
                                                                    ? 'No ready workers'
                                                                    : 'Select worker'
                                                                : 'Pick role first'
                                                        }
                                                        placeholder={
                                                            row.role_code
                                                                ? readyWorkers.length ===
                                                                  0
                                                                    ? 'No ready workers'
                                                                    : 'Select worker'
                                                                : 'Pick role first'
                                                        }
                                                        options={readyWorkers.map(
                                                            (worker) => ({
                                                                value: String(
                                                                    worker.id,
                                                                ),
                                                                label: worker.label,
                                                            }),
                                                        )}
                                                    />
                                                    {eligibility &&
                                                        !eligibility.ready && (
                                                            <p className="text-xs text-destructive">
                                                                Missing:{' '}
                                                                {eligibility.missing_labels.join(
                                                                    ', ',
                                                                )}
                                                            </p>
                                                        )}
                                                </div>
                                                {personnel.length > 1 && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        className="self-end"
                                                        onClick={() =>
                                                            removePersonnelRow(
                                                                index,
                                                            )
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </div>
                                        );
                                    })}
                                    {errors.personnel && (
                                        <p className="text-sm text-destructive">
                                            {errors.personnel}
                                        </p>
                                    )}
                                </section>

                                {checklistItems.length > 0 && (
                                    <section className="space-y-4 rounded-[var(--radius)] border border-border border-l-[3px] border-l-[color:var(--ok)] bg-surface p-4 shadow-[var(--shadow-card)]">
                                        <div className="flex items-center gap-2.5">
                                            <SectionBadge step={3} tone="ok" />
                                            <div>
                                                <p className="eyebrow">
                                                    Checklist / JSA
                                                </p>
                                                <h2 className="text-sm font-semibold text-text">
                                                    Confirm hazards &
                                                    precautions
                                                </h2>
                                            </div>
                                        </div>
                                        <ul className="space-y-2">
                                            {checklistItems.map((item) => (
                                                <li
                                                    key={item.code}
                                                    className={cn(
                                                        'flex items-start gap-2 rounded-md border px-3 py-2 text-sm transition-colors',
                                                        checklist[item.code]
                                                            ? 'border-[color:var(--ok)]/35 bg-[color:var(--ok-bg)]'
                                                            : 'border-border bg-surface-2/30',
                                                    )}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        id={`checklist-${item.code}`}
                                                        name={`checklist[${item.code}]`}
                                                        value="1"
                                                        checked={
                                                            checklist[
                                                                item.code
                                                            ] ?? false
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
                                    </section>
                                )}

                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Save draft
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Panel title="Task" subtitle="Work scope and signatories">
                            <p className="rounded-md border border-border bg-surface-2/40 px-3 py-2.5 text-sm leading-relaxed text-text">
                                {permit.task_description}
                            </p>
                            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <div className="rounded-md border border-border/80 bg-surface-2/20 px-3 py-2">
                                    <dt className="eyebrow">Zone</dt>
                                    <dd className="mt-1 font-medium">
                                        {permit.zone?.name ?? '—'}
                                    </dd>
                                </div>
                                <div className="rounded-md border border-border/80 bg-surface-2/20 px-3 py-2">
                                    <dt className="eyebrow">Valid to</dt>
                                    <dd className="mt-1 font-medium tabular-nums">
                                        {formatDate(permit.valid_to)}
                                    </dd>
                                </div>
                                <div className="rounded-md border border-border/80 bg-surface-2/20 px-3 py-2">
                                    <dt className="eyebrow">Receiver</dt>
                                    <dd className="mt-1 font-medium">
                                        {permit.receiver?.name ?? '—'}
                                    </dd>
                                </div>
                                <div className="rounded-md border border-border/80 bg-surface-2/20 px-3 py-2">
                                    <dt className="eyebrow">Issuer</dt>
                                    <dd className="mt-1 font-medium">
                                        {permit.issuer?.name ?? '—'}
                                    </dd>
                                </div>
                            </dl>
                        </Panel>

                        <Panel title="Crew" subtitle="Assigned roles and document readiness">
                            {permit.personnel.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No personnel assigned.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {permit.personnel.map((person) => (
                                        <li
                                            key={person.id}
                                            className={cn(
                                                'flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2.5 text-sm',
                                                person.document_status
                                                    .status === 'green' &&
                                                    'border-[color:var(--ok)]/30 bg-[color:var(--ok-bg)]',
                                                person.document_status
                                                    .status === 'amber' &&
                                                    'border-[color:var(--warn)]/30 bg-[color:var(--warn-bg)]',
                                                person.document_status
                                                    .status === 'red' &&
                                                    'border-[color:var(--crit)]/30 bg-[color:var(--crit-bg)]',
                                            )}
                                        >
                                            <div>
                                                <p className="font-medium text-text">
                                                    {person.worker_label ??
                                                        `#${person.worker_id}`}
                                                </p>
                                                <p className="text-xs text-text-dim">
                                                    {person.role_code}
                                                </p>
                                            </div>
                                            <StatusPill
                                                label={
                                                    person.document_status
                                                        .status
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
                    <Panel title="Checklist / JSA" subtitle="Hazards and precautions">
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
                                        className={cn(
                                            'flex items-center justify-between gap-2 rounded-md border px-3 py-2',
                                            answered
                                                ? 'border-[color:var(--ok)]/30 bg-[color:var(--ok-bg)]'
                                                : 'border-[color:var(--warn)]/30 bg-[color:var(--warn-bg)]',
                                        )}
                                    >
                                        <span>
                                            {item.label}
                                            {item.is_mandatory ? ' *' : ''}
                                        </span>
                                        <StatusPill
                                            label={
                                                answered ? 'Checked' : 'Open'
                                            }
                                            tone={answered ? 'ok' : 'warn'}
                                        />
                                    </li>
                                );
                            })}
                        </ul>
                    </Panel>
                )}

                {permit.status === 'pending_inspection' && (
                    <Panel
                        title="Joint inspection"
                        subtitle="Issuer and receiver must both sign"
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div
                                className={cn(
                                    'rounded-md border px-3 py-3 text-sm',
                                    permit.joint_inspection?.issuer_signed
                                        ? 'border-[color:var(--ok)]/35 bg-[color:var(--ok-bg)]'
                                        : 'border-[color:var(--warn)]/35 bg-[color:var(--warn-bg)]',
                                )}
                            >
                                <p className="eyebrow">Issuer</p>
                                <div className="mt-2">
                                    <StatusPill
                                        label={
                                            permit.joint_inspection
                                                ?.issuer_signed
                                                ? 'Signed'
                                                : 'Awaiting signature'
                                        }
                                        tone={
                                            permit.joint_inspection
                                                ?.issuer_signed
                                                ? 'ok'
                                                : 'warn'
                                        }
                                    />
                                </div>
                            </div>
                            <div
                                className={cn(
                                    'rounded-md border px-3 py-3 text-sm',
                                    permit.joint_inspection?.receiver_signed
                                        ? 'border-[color:var(--ok)]/35 bg-[color:var(--ok-bg)]'
                                        : 'border-[color:var(--warn)]/35 bg-[color:var(--warn-bg)]',
                                )}
                            >
                                <p className="eyebrow">Receiver</p>
                                <div className="mt-2">
                                    <StatusPill
                                        label={
                                            permit.joint_inspection
                                                ?.receiver_signed
                                                ? 'Signed'
                                                : 'Awaiting signature'
                                        }
                                        tone={
                                            permit.joint_inspection
                                                ?.receiver_signed
                                                ? 'ok'
                                                : 'warn'
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    </Panel>
                )}

                {canGasTest && permit.status === 'pending_gas_test' && (
                    <Panel
                        title="Record gas test"
                        subtitle="Enter channel readings or prefill from live sensors"
                        className="border-[color:var(--warn)]/40"
                    >                        <div className="mb-3 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => void loadGasSuggestion()}
                            >
                                Prefill from live sensors
                            </Button>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {gasChannels.map((channel) => (
                                <div
                                    key={channel.channel_code}
                                    className="grid gap-1 rounded-md border border-border bg-surface-2/30 p-3"
                                >
                                    <Label
                                        htmlFor={`gas-${channel.channel_code}`}
                                    >
                                        {channel.label}
                                        {channel.unit
                                            ? ` (${channel.unit})`
                                            : ''}
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
                                No gas channels configured for this permit
                                type. Add them under Catalogue → Permit types.
                            </p>
                        )}
                        <div className="mt-3 grid gap-2 sm:max-w-xs">
                            <Label htmlFor="gas-phase">Phase</Label>
                            <SearchableSelect
                                id="gas-phase"
                                value={gasPhase}
                                onValueChange={setGasPhase}
                                options={gasPhaseOptions}
                            />
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

                {(permit.gas_tests.length > 0 ||
                    permit.gas_test_required) && (
                    <Panel title="Gas tests" subtitle="Atmospheric test history">
                        {permit.gas_tests.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No gas tests recorded yet.
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {permit.gas_tests.map((test) => (
                                    <li
                                        key={test.id}
                                        className={cn(
                                            'rounded-md border px-3 py-2.5',
                                            test.result === 'pass'
                                                ? 'border-[color:var(--ok)]/30 bg-[color:var(--ok-bg)]'
                                                : 'border-[color:var(--crit)]/30 bg-[color:var(--crit-bg)]',
                                        )}
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
                                            <span>
                                                {formatDate(test.tested_at)}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {test.phase_label} ·{' '}
                                                {test.source_label}
                                                {test.tested_by_name
                                                    ? ` · ${test.tested_by_name}`
                                                    : ''}
                                            </span>
                                        </div>
                                        {Object.keys(test.readings).length >
                                            0 && (
                                            <dl className="mt-2 grid gap-1 text-xs text-muted-foreground sm:grid-cols-2">
                                                {Object.entries(
                                                    test.readings,
                                                ).map(([code, value]) => (
                                                    <div
                                                        key={code}
                                                        className="flex justify-between gap-2"
                                                    >
                                                        <dt>{code}</dt>
                                                        <dd>
                                                            {String(value)}
                                                        </dd>
                                                    </div>
                                                ))}
                                            </dl>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Approvals" subtitle="Sign-off trail">
                        {permit.approvals.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No approvals yet.
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {permit.approvals.map((approval) => (
                                    <li
                                        key={approval.id}
                                        className="rounded-md border border-border bg-surface-2/30 px-3 py-2"
                                    >
                                        <span className="font-medium text-[color:var(--accent)]">
                                            {approval.action_label}
                                        </span>{' '}
                                        · {approval.user_name ?? 'System'} ·{' '}
                                        <span className="tabular-nums text-text-dim">
                                            {formatDate(approval.signed_at)}
                                        </span>
                                        {approval.note ? (
                                            <p className="mt-1 text-muted-foreground">
                                                {approval.note}
                                            </p>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel title="Events" subtitle="Lifecycle log">
                        {permit.events.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No events logged.
                            </p>
                        ) : (
                            <ul className="relative space-y-0 text-sm before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px before:bg-border">
                                {permit.events.map((event) => (
                                    <li
                                        key={event.id}
                                        className="relative flex gap-3 py-2 pl-5"
                                    >
                                        <span
                                            className="absolute top-3 left-0 size-3.5 rounded-full border-2 border-[color:var(--accent)] bg-surface"
                                            aria-hidden
                                        />
                                        <div>
                                            <span className="font-medium">
                                                {event.event}
                                            </span>{' '}
                                            · {event.user_name ?? 'System'}
                                            <p className="text-xs tabular-nums text-text-dim">
                                                {formatDate(event.occurred_at)}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
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
