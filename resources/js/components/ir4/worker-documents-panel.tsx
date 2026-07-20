import { Form, Link } from '@inertiajs/react';
import { StatusPill } from '@/components/ir4/status-pill';
import type { StatusPillTone } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type DocumentChecklistItem = {
    id: number;
    code: string;
    name: string;
    category: string | null;
    requires_file: boolean;
    requires_expiry: boolean;
    status: 'missing' | 'pending' | 'verified' | 'rejected' | 'expired';
    status_label: string;
    document_id: number | null;
    expires_at: string | null;
    used_by_roles: Array<{
        permit_type: string;
        role_code: string | null;
        role_label: string;
    }>;
};

export type PermitReadinessRow = {
    permit_type_id: number;
    permit_type_code: string;
    permit_type_name: string;
    role_code: string;
    role_label: string;
    is_mandatory: boolean;
    ready: boolean;
    missing: string[];
    missing_labels: string[];
};

export type ReadinessSummary = {
    ready_roles: number;
    blocked_roles: number;
    verified_docs: number;
    pending_docs: number;
    missing_recommended: number;
};

type DocumentTypeOption = {
    id: number;
    name: string;
    code: string;
    requires_file: boolean;
    requires_expiry?: boolean;
    category?: string | null;
};

type DocumentRow = {
    id: number;
    type_name: string;
    document_number: string | null;
    issuing_body: string | null;
    issued_at: string | null;
    expires_at: string | null;
    verification_status: string;
    verification_status_label: string;
    has_file: boolean;
    download_url: string | null;
};

type Props = {
    workerId: number;
    documents: DocumentRow[];
    documentTypes: DocumentTypeOption[];
    checklist: DocumentChecklistItem[];
    permitReadiness: PermitReadinessRow[];
    summary: ReadinessSummary | null;
    onboarding?: boolean;
};

function checklistTone(status: DocumentChecklistItem['status']): StatusPillTone {
    if (status === 'verified') {
        return 'ok';
    }

    if (status === 'pending') {
        return 'warn';
    }

    if (status === 'missing') {
        return 'neutral';
    }

    return 'crit';
}

function documentStatusTone(status: string): StatusPillTone {
    if (status === 'verified') {
        return 'ok';
    }

    if (status === 'rejected' || status === 'expired') {
        return 'crit';
    }

    return 'warn';
}

export function WorkerDocumentsPanel({
    workerId,
    documents,
    documentTypes,
    checklist,
    permitReadiness,
    summary,
    onboarding = false,
}: Props) {
    const recommended = checklist.filter((item) => item.used_by_roles.length > 0);
    const readyRoles = permitReadiness.filter((row) => row.ready);
    const blockedRoles = permitReadiness.filter((row) => !row.ready);

    return (
        <div className="space-y-4" id="worker-documents">
            {onboarding ? (
                <div className="rounded-lg border border-primary/30 bg-primary/5 p-4">
                    <p className="text-xs font-medium uppercase tracking-wide text-primary">
                        Onboarding · step 2 of 3
                    </p>
                    <h2 className="mt-1 text-base font-semibold text-text">
                        Add certificates & documents
                    </h2>
                    <p className="mt-1 text-sm text-text-dim">
                        Upload and verify competence docs here. Permit assignment
                        only picks workers who already meet the role pack — it
                        will not collect documents.
                    </p>
                    {summary ? (
                        <p className="mt-2 text-sm text-text">
                            {summary.verified_docs} verified ·{' '}
                            {summary.pending_docs} pending ·{' '}
                            {summary.missing_recommended} recommended still
                            missing · {summary.ready_roles} permit roles ready
                        </p>
                    ) : null}
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href={`/workforce/workers/${workerId}`}>
                                Finish onboarding
                            </Link>
                        </Button>
                    </div>
                </div>
            ) : null}

            <div className="grid gap-4 xl:grid-cols-2">
                <section className="rounded-lg border border-border p-4">
                    <h3 className="text-sm font-semibold text-text">
                        Recommended for permits
                    </h3>
                    <p className="mt-1 text-xs text-text-faint">
                        Types required by active permit catalogues. Upload on
                        this worker once; reuse across every future permit.
                    </p>
                    <ul className="mt-3 flex flex-col gap-2 text-sm">
                        {(recommended.length > 0 ? recommended : checklist).map(
                            (item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap items-start justify-between gap-2 border-b border-border pb-2 last:border-0"
                                >
                                    <div>
                                        <p className="text-text">{item.name}</p>
                                        <p className="text-xs text-text-faint">
                                            {item.used_by_roles.length > 0
                                                ? item.used_by_roles
                                                      .slice(0, 3)
                                                      .map(
                                                          (use) =>
                                                              `${use.permit_type} · ${use.role_label}`,
                                                      )
                                                      .join(' · ')
                                                : item.category ?? item.code}
                                            {item.expires_at
                                                ? ` · expires ${item.expires_at}`
                                                : ''}
                                        </p>
                                    </div>
                                    <StatusPill
                                        label={item.status_label}
                                        tone={checklistTone(item.status)}
                                    />
                                </li>
                            ),
                        )}
                        {checklist.length === 0 ? (
                            <li className="text-text-faint">
                                No document types configured. Add them under
                                Workforce → Document types.
                            </li>
                        ) : null}
                    </ul>
                </section>

                <section className="rounded-lg border border-border p-4">
                    <h3 className="text-sm font-semibold text-text">
                        Permit role readiness
                    </h3>
                    <p className="mt-1 text-xs text-text-faint">
                        Green roles can be selected on a permit without further
                        document work.
                    </p>
                    <ul className="mt-3 flex max-h-72 flex-col gap-2 overflow-y-auto text-sm">
                        {[...readyRoles, ...blockedRoles].map((row) => (
                            <li
                                key={`${row.permit_type_id}:${row.role_code}`}
                                className="flex flex-wrap items-start justify-between gap-2 border-b border-border pb-2 last:border-0"
                            >
                                <div>
                                    <p className="text-text">
                                        {row.permit_type_name} · {row.role_label}
                                    </p>
                                    {!row.ready ? (
                                        <p className="text-xs text-destructive">
                                            Missing:{' '}
                                            {row.missing_labels.join(', ') ||
                                                row.missing.join(', ')}
                                        </p>
                                    ) : (
                                        <p className="text-xs text-text-faint">
                                            Pack satisfied
                                        </p>
                                    )}
                                </div>
                                <StatusPill
                                    label={row.ready ? 'Ready' : 'Blocked'}
                                    tone={row.ready ? 'ok' : 'crit'}
                                />
                            </li>
                        ))}
                        {permitReadiness.length === 0 ? (
                            <li className="text-text-faint">
                                No active permit-type roles configured.
                            </li>
                        ) : null}
                    </ul>
                </section>
            </div>

            <section className="rounded-lg border border-border p-4">
                <h3 className="text-sm font-semibold text-text">On file</h3>
                <ul className="mt-3 flex flex-col gap-2 text-sm">
                    {documents.map((document) => (
                        <li
                            key={document.id}
                            className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2 last:border-0"
                        >
                            <div>
                                <p className="text-text">
                                    {document.type_name}
                                    {document.document_number
                                        ? ` · ${document.document_number}`
                                        : ''}
                                </p>
                                <p className="text-xs text-text-faint">
                                    {[
                                        document.issuing_body,
                                        document.expires_at
                                            ? `Expires ${document.expires_at}`
                                            : null,
                                        document.has_file
                                            ? 'Attachment on file'
                                            : 'No attachment',
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || '—'}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusPill
                                    label={document.verification_status_label}
                                    tone={documentStatusTone(
                                        document.verification_status,
                                    )}
                                />
                                {document.download_url && (
                                    <Button asChild size="sm" variant="outline">
                                        <a
                                            href={document.download_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Download
                                        </a>
                                    </Button>
                                )}
                                {document.verification_status === 'pending' && (
                                    <>
                                        <Form
                                            action={`/workforce/workers/${workerId}/documents/${document.id}/verify`}
                                            method="post"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="secondary"
                                                    disabled={
                                                        processing ||
                                                        !document.has_file
                                                    }
                                                    title={
                                                        document.has_file
                                                            ? undefined
                                                            : 'Attach a file before verifying'
                                                    }
                                                >
                                                    Verify
                                                </Button>
                                            )}
                                        </Form>
                                        <Form
                                            action={`/workforce/workers/${workerId}/documents/${document.id}/reject`}
                                            method="post"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    Reject
                                                </Button>
                                            )}
                                        </Form>
                                    </>
                                )}
                                <Form
                                    action={`/workforce/workers/${workerId}/documents/${document.id}`}
                                    method="delete"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="ghost"
                                            disabled={processing}
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </li>
                    ))}
                    {documents.length === 0 && (
                        <li className="text-text-faint">No documents on file.</li>
                    )}
                </ul>

                <Form
                    action={`/workforce/workers/${workerId}/documents`}
                    method="post"
                    encType="multipart/form-data"
                    options={{ preserveScroll: true }}
                    className="mt-4 grid gap-3 border-t border-border pt-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-1.5">
                                <Label htmlFor="worker_document_type_id">
                                    Document type
                                </Label>
                                <select
                                    id="worker_document_type_id"
                                    name="worker_document_type_id"
                                    required
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none"
                                    defaultValue=""
                                >
                                    <option value="" disabled>
                                        Select type
                                    </option>
                                    {documentTypes.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.name}
                                            {type.requires_file
                                                ? ' (file required)'
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.worker_document_type_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.worker_document_type_id}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="document_number">Number</Label>
                                <Input
                                    id="document_number"
                                    name="document_number"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="issuing_body">
                                    Issuing body
                                </Label>
                                <Input id="issuing_body" name="issuing_body" />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="issued_at">Issued</Label>
                                <Input
                                    id="issued_at"
                                    name="issued_at"
                                    type="date"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="expires_at">Expires</Label>
                                <Input
                                    id="expires_at"
                                    name="expires_at"
                                    type="date"
                                />
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="file">
                                    Attachment (PDF or image, ≤50 MB)
                                </Label>
                                <Input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept="application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png"
                                />
                                {errors.file && (
                                    <p className="text-sm text-destructive">
                                        {errors.file}
                                    </p>
                                )}
                            </div>
                            <div className="flex items-end">
                                <Button type="submit" disabled={processing}>
                                    Add document
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </section>
        </div>
    );
}
