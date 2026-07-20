import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from '@/components/ir4/panel';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PermitDetail, PermitOption } from '@/types/permit';

type Props = {
    permit: PermitDetail;
    gasPhaseOptions: PermitOption[];
    canRequest: boolean;
    canIssue: boolean;
    canApprove: boolean;
    canGasTest: boolean;
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

export default function PermitShow({
    permit,
    gasPhaseOptions,
    canRequest,
    canIssue,
    canApprove,
    canGasTest,
}: Props) {
    const [note, setNote] = useState('');
    const [gasReadings, setGasReadings] = useState<Record<string, string>>({});
    const [gasPhase, setGasPhase] = useState(
        gasPhaseOptions[0]?.value ?? 'pre_start',
    );

    function postAction(url: string, data: RequestPayload = {}): void {
        router.post(url, data as RequestPayload, { preserveScroll: true });
    }

    async function loadGasSuggestion(): Promise<void> {
        const response = await fetch(`/workforce/permits/${permit.id}/gas-suggestion`);
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
                    {canRequest && permit.status === 'draft' && (
                        <Button
                            type="button"
                            onClick={() =>
                                postAction(`/workforce/permits/${permit.id}/submit`)
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
                                postAction(`/workforce/permits/${permit.id}/approve`, {
                                    note: note || undefined,
                                })
                            }
                        >
                            Approve
                        </Button>
                    )}
                    {canIssue && permit.status === 'pending_issue' && (
                        <Button
                            type="button"
                            onClick={() =>
                                postAction(`/workforce/permits/${permit.id}/issue`, {
                                    note: note || undefined,
                                })
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
                                    postAction(`/workforce/permits/${permit.id}/renew`)
                                }
                            >
                                Renew
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() =>
                                    postAction(`/workforce/permits/${permit.id}/suspend`, {
                                        note: note || 'Suspended',
                                    })
                                }
                            >
                                Suspend
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    postAction(`/workforce/permits/${permit.id}/close`, {
                                        note: note || 'Work complete',
                                    })
                                }
                            >
                                Close
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() =>
                                    postAction(`/workforce/permits/${permit.id}/cancel`, {
                                        note: note || 'Cancelled',
                                    })
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
                                postAction(`/workforce/permits/${permit.id}/resume`)
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
                                    postAction(`/workforce/permits/${permit.id}/reject`, {
                                        note: note || 'Rejected',
                                    })
                                }
                            >
                                Reject
                            </Button>
                        )}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Task">
                        <p className="text-sm text-text">{permit.task_description}</p>
                        <dl className="mt-4 grid gap-2 text-sm">
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Zone</dt>
                                <dd>{permit.zone?.name ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Receiver</dt>
                                <dd>{permit.receiver?.name ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Valid to</dt>
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
                                            {person.worker_label ?? `#${person.worker_id}`}{' '}
                                            · {person.role_code}
                                        </span>
                                        <StatusPill
                                            label={person.document_status.status}
                                            tone={docTone(
                                                person.document_status.status,
                                            )}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>

                {canGasTest && permit.status === 'pending_gas_test' && (
                    <Panel title="Record gas test">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {['o2_pct', 'lel_pct', 'h2s_ppm', 'co_ppm'].map(
                                (channel) => (
                                    <div key={channel} className="grid gap-1">
                                        <Label htmlFor={`gas-${channel}`}>
                                            {channel}
                                        </Label>
                                        <Input
                                            id={`gas-${channel}`}
                                            value={gasReadings[channel] ?? ''}
                                            onChange={(event) =>
                                                setGasReadings((current) => ({
                                                    ...current,
                                                    [channel]:
                                                        event.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                ),
                            )}
                        </div>
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
                                            {test.phase_label} · {test.source_label}
                                        </span>
                                    </div>
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
