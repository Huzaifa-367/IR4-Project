import { Form, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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

export type DocumentRow = {
    id: number;
    worker_document_type_id: number;
    type_name: string;
    document_number: string | null;
    issuing_body: string | null;
    issued_at: string | null;
    expires_at: string | null;
    notes?: string | null;
    verification_status: string;
    verification_status_label: string;
    has_file: boolean;
    download_url: string | null;
};

type Props = {
    workerId: number;
    documents: DocumentRow[];
    documentTypes?: DocumentTypeOption[];
    checklist: DocumentChecklistItem[];
    permitReadiness: PermitReadinessRow[];
    summary: ReadinessSummary | null;
    onboarding?: boolean;
};

type EditorMode =
    | { kind: 'create'; typeId: number }
    | { kind: 'edit'; documentId: number }
    | null;

function statusTone(
    status: DocumentChecklistItem['status'] | string,
): StatusPillTone {
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

function DocumentFormFields({
    type,
    document,
    processing,
    errors,
    multiFile,
}: {
    type: Pick<
        DocumentChecklistItem,
        'id' | 'requires_file' | 'requires_expiry'
    >;
    document?: DocumentRow;
    processing: boolean;
    errors: Record<string, string>;
    multiFile: boolean;
}) {
    const fileId = document
        ? `file-edit-${document.id}`
        : `file-create-${type.id}`;
    const numberId = document
        ? `num-edit-${document.id}`
        : `num-create-${type.id}`;
    const expiryId = document
        ? `exp-edit-${document.id}`
        : `exp-create-${type.id}`;
    const bodyId = document
        ? `body-edit-${document.id}`
        : `body-create-${type.id}`;

    return (
        <>
            {!document && (
                <input
                    type="hidden"
                    name="worker_document_type_id"
                    value={type.id}
                />
            )}
            <div className="space-y-1">
                <Label htmlFor={numberId}>Number</Label>
                <Input
                    id={numberId}
                    name="document_number"
                    defaultValue={document?.document_number ?? ''}
                />
            </div>
            <div className="space-y-1">
                <Label htmlFor={bodyId}>Issuing body</Label>
                <Input
                    id={bodyId}
                    name="issuing_body"
                    defaultValue={document?.issuing_body ?? ''}
                />
            </div>
            <div className="space-y-1">
                <Label htmlFor={expiryId}>Expires</Label>
                <Input
                    id={expiryId}
                    name="expires_at"
                    type="date"
                    required={type.requires_expiry}
                    defaultValue={document?.expires_at ?? ''}
                />
                {errors.expires_at ? (
                    <p className="text-sm text-destructive">
                        {errors.expires_at}
                    </p>
                ) : null}
            </div>
            <div className="space-y-1 sm:col-span-2">
                <Label htmlFor={fileId}>
                    {multiFile ? 'Files' : 'File'}
                    {type.requires_file && !document?.has_file
                        ? ' (required)'
                        : document
                          ? ' (leave empty to keep current)'
                          : ''}
                </Label>
                <Input
                    id={fileId}
                    name={multiFile ? 'files[]' : 'file'}
                    type="file"
                    multiple={multiFile}
                    required={type.requires_file && !document?.has_file}
                    accept="application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png"
                />
                {multiFile ? (
                    <p className="text-xs text-muted-foreground">
                        Select one or more files — each becomes its own document
                        row for this type.
                    </p>
                ) : null}
                {errors.file ? (
                    <p className="text-sm text-destructive">{errors.file}</p>
                ) : null}
                {errors['files.0'] ? (
                    <p className="text-sm text-destructive">
                        {errors['files.0']}
                    </p>
                ) : null}
            </div>
            <div className="sm:col-span-2">
                <Button type="submit" disabled={processing}>
                    {document ? 'Save changes' : multiFile ? 'Upload' : 'Add document'}
                </Button>
            </div>
        </>
    );
}

export function WorkerDocumentsPanel({
    workerId,
    documents,
    checklist,
    permitReadiness,
    summary,
    onboarding = false,
}: Props) {
    const [editor, setEditor] = useState<EditorMode>(null);
    const [showRoles, setShowRoles] = useState(false);

    const recommended = checklist.filter((item) => item.used_by_roles.length > 0);
    const rows = recommended.length > 0 ? recommended : checklist;
    const readyRoles = permitReadiness.filter((row) => row.ready);
    const blockedRoles = permitReadiness.filter((row) => !row.ready);

    const documentsByType = useMemo(() => {
        const map = new Map<number, DocumentRow[]>();

        for (const doc of documents) {
            const list = map.get(doc.worker_document_type_id) ?? [];
            list.push(doc);
            map.set(doc.worker_document_type_id, list);
        }

        return map;
    }, [documents]);

    function closeEditor(): void {
        setEditor(null);
    }

    function toggleCreate(typeId: number): void {
        setEditor((current) =>
            current?.kind === 'create' && current.typeId === typeId
                ? null
                : { kind: 'create', typeId },
        );
    }

    function toggleEdit(documentId: number): void {
        setEditor((current) =>
            current?.kind === 'edit' && current.documentId === documentId
                ? null
                : { kind: 'edit', documentId },
        );
    }

    function deleteDocument(documentId: number): void {
        if (
            !window.confirm(
                'Remove this document? The file will be deleted from private storage.',
            )
        ) {
            return;
        }

        router.delete(`/workforce/workers/${workerId}/documents/${documentId}`, {
            preserveScroll: true,
        });
    }

    return (
        <div className="space-y-4" id="worker-documents">
            {onboarding ? (
                <div className="rounded-lg border border-primary/30 bg-primary/5 px-4 py-3">
                    <p className="text-sm font-medium text-text">
                        Upload certificates next
                    </p>
                    <p className="mt-1 text-sm text-text-dim">
                        Add the packs below once. Permit requests only pick
                        workers who are already ready for the role.
                    </p>
                    <Button asChild size="sm" variant="outline" className="mt-3">
                        <Link href={`/workforce/workers/${workerId}`}>Done</Link>
                    </Button>
                </div>
            ) : null}

            {summary ? (
                <p className="text-sm text-text-dim">
                    <span className="text-text">{summary.verified_docs}</span>{' '}
                    verified ·{' '}
                    <span className="text-text">{summary.pending_docs}</span>{' '}
                    pending ·{' '}
                    <span className="text-text">
                        {summary.missing_recommended}
                    </span>{' '}
                    still needed ·{' '}
                    <span className="text-text">{summary.ready_roles}</span>{' '}
                    roles ready
                </p>
            ) : null}

            <ul className="divide-y divide-border rounded-lg border border-border">
                {rows.map((item) => {
                    const typeDocs = documentsByType.get(item.id) ?? [];
                    const creating =
                        editor?.kind === 'create' && editor.typeId === item.id;

                    return (
                        <li key={item.id} className="px-4 py-3">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium text-text">
                                        {item.name}
                                    </p>
                                    <p className="text-xs text-text-faint">
                                        {item.used_by_roles.length > 0
                                            ? item.used_by_roles
                                                  .slice(0, 2)
                                                  .map(
                                                      (use) =>
                                                          `${use.permit_type} · ${use.role_label}`,
                                                  )
                                                  .join(' · ')
                                            : (item.category ?? item.code)}
                                        {typeDocs.length > 0
                                            ? ` · ${typeDocs.length} on file`
                                            : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusPill
                                        label={item.status_label}
                                        tone={statusTone(item.status)}
                                    />
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => toggleCreate(item.id)}
                                    >
                                        {creating ? 'Cancel' : 'Add'}
                                    </Button>
                                </div>
                            </div>

                            {typeDocs.length > 0 ? (
                                <ul className="mt-3 space-y-2">
                                    {typeDocs.map((document) => {
                                        const editing =
                                            editor?.kind === 'edit' &&
                                            editor.documentId === document.id;

                                        return (
                                            <li
                                                key={document.id}
                                                className="rounded-md border border-border bg-surface-2/40 px-3 py-2"
                                            >
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <div className="min-w-0 text-sm">
                                                        <p className="text-text">
                                                            {document.document_number ||
                                                                'No number'}
                                                            {document.expires_at
                                                                ? ` · expires ${document.expires_at}`
                                                                : ''}
                                                        </p>
                                                        <p className="text-xs text-text-faint">
                                                            {document.issuing_body ||
                                                                'No issuing body'}
                                                            {document.has_file
                                                                ? ' · file attached'
                                                                : ' · no file'}
                                                        </p>
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <StatusPill
                                                            label={
                                                                document.verification_status_label
                                                            }
                                                            tone={statusTone(
                                                                document.verification_status,
                                                            )}
                                                        />
                                                        {document.verification_status ===
                                                            'pending' && (
                                                            <>
                                                                <Form
                                                                    action={`/workforce/workers/${workerId}/documents/${document.id}/verify`}
                                                                    method="post"
                                                                >
                                                                    {({
                                                                        processing,
                                                                    }) => (
                                                                        <Button
                                                                            type="submit"
                                                                            size="sm"
                                                                            disabled={
                                                                                processing ||
                                                                                (item.requires_file &&
                                                                                    !document.has_file)
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
                                                                    {({
                                                                        processing,
                                                                    }) => (
                                                                        <Button
                                                                            type="submit"
                                                                            size="sm"
                                                                            variant="ghost"
                                                                            disabled={
                                                                                processing
                                                                            }
                                                                        >
                                                                            Reject
                                                                        </Button>
                                                                    )}
                                                                </Form>
                                                            </>
                                                        )}
                                                        {document.download_url ? (
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                <a
                                                                    href={
                                                                        document.download_url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    File
                                                                </a>
                                                            </Button>
                                                        ) : null}
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                toggleEdit(
                                                                    document.id,
                                                                )
                                                            }
                                                        >
                                                            {editing
                                                                ? 'Cancel'
                                                                : 'Edit'}
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() =>
                                                                deleteDocument(
                                                                    document.id,
                                                                )
                                                            }
                                                        >
                                                            Delete
                                                        </Button>
                                                    </div>
                                                </div>

                                                {editing ? (
                                                    <Form
                                                        action={`/workforce/workers/${workerId}/documents/${document.id}`}
                                                        method="put"
                                                        encType="multipart/form-data"
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                        className="mt-3 grid gap-3 rounded-md border border-dashed border-border bg-background p-3 sm:grid-cols-2"
                                                        onSuccess={closeEditor}
                                                    >
                                                        {({
                                                            processing,
                                                            errors,
                                                        }) => (
                                                            <DocumentFormFields
                                                                type={item}
                                                                document={
                                                                    document
                                                                }
                                                                processing={
                                                                    processing
                                                                }
                                                                errors={errors}
                                                                multiFile={
                                                                    false
                                                                }
                                                            />
                                                        )}
                                                    </Form>
                                                ) : null}
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Nothing on file for this type yet.
                                </p>
                            )}

                            {creating ? (
                                <Form
                                    action={`/workforce/workers/${workerId}/documents`}
                                    method="post"
                                    encType="multipart/form-data"
                                    options={{ preserveScroll: true }}
                                    className="mt-3 grid gap-3 rounded-md border border-dashed border-border bg-surface-2 p-3 sm:grid-cols-2"
                                    onSuccess={closeEditor}
                                >
                                    {({ processing, errors }) => (
                                        <DocumentFormFields
                                            type={item}
                                            processing={processing}
                                            errors={errors}
                                            multiFile
                                        />
                                    )}
                                </Form>
                            ) : null}
                        </li>
                    );
                })}
                {rows.length === 0 ? (
                    <li className="px-4 py-6 text-sm text-text-faint">
                        No document types configured. Add them under Catalogue →
                        Document types.
                    </li>
                ) : null}
            </ul>

            <div className="rounded-lg border border-border">
                <button
                    type="button"
                    className="flex w-full items-center justify-between px-4 py-3 text-left text-sm"
                    onClick={() => setShowRoles((value) => !value)}
                >
                    <span className="font-medium text-text">
                        Permit roles this worker can fill
                    </span>
                    <span className="text-text-faint">
                        {readyRoles.length} ready · {blockedRoles.length}{' '}
                        blocked · {showRoles ? 'Hide' : 'Show'}
                    </span>
                </button>
                {showRoles ? (
                    <ul className="max-h-64 divide-y divide-border overflow-y-auto border-t border-border">
                        {[...readyRoles, ...blockedRoles].map((row) => (
                            <li
                                key={`${row.permit_type_id}:${row.role_code}`}
                                className="flex flex-wrap items-start justify-between gap-2 px-4 py-2 text-sm"
                            >
                                <div>
                                    <p className="text-text">
                                        {row.permit_type_name} ·{' '}
                                        {row.role_label}
                                    </p>
                                    {!row.ready ? (
                                        <p className="text-xs text-destructive">
                                            Need:{' '}
                                            {row.missing_labels.join(', ') ||
                                                row.missing.join(', ')}
                                        </p>
                                    ) : null}
                                </div>
                                <StatusPill
                                    label={row.ready ? 'Ready' : 'Blocked'}
                                    tone={row.ready ? 'ok' : 'crit'}
                                />
                            </li>
                        ))}
                    </ul>
                ) : null}
            </div>
        </div>
    );
}
