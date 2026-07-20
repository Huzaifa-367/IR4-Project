import type { PaginatedMeta } from '@/types/hardware';

export type PermitOption = { value: string; label: string };

export type PermitTypeRole = {
    role_code: string;
    label: string;
    min_count: number;
    is_mandatory: boolean;
};

export type PermitTypeGasChannel = {
    channel_code: string;
    label: string;
    unit: string | null;
    alarm_below: number | null;
    alarm_above: number | null;
};

export type PermitDocumentRequirement = {
    role_code: string | null;
    is_mandatory: boolean;
    must_be_verified: boolean;
    worker_document_type: {
        id: number;
        code: string;
        name: string;
    } | null;
};

export type PermitTypeSummary = {
    id: number;
    code: string;
    name: string;
    colour_token: string | null;
    requires_gas_test: boolean;
    requires_joint_inspection: boolean;
    requires_approver: boolean;
    allows_extended: boolean;
    roles: PermitTypeRole[];
    gas_channels: PermitTypeGasChannel[];
    document_requirements: PermitDocumentRequirement[];
};

export type PermitListItem = {
    id: number;
    permit_number: string;
    status: string;
    status_label: string;
    task_description: string;
    valid_to: string | null;
    type: {
        id: number;
        name: string;
        colour_token: string | null;
    } | null;
    zone: {
        id: number;
        name: string;
    } | null;
};

export type PermitPersonnelDocumentStatus = {
    status: 'green' | 'amber' | 'red' | string;
    missing: string[];
    expiring_soon: string[];
};

export type PermitPersonnel = {
    id: number;
    worker_id: number;
    worker_label: string | null;
    employee_code: string | null;
    role_code: string;
    documents_verified_at: string | null;
    document_status: PermitPersonnelDocumentStatus;
};

export type PermitGasTest = {
    id: number;
    tested_at: string;
    readings: Record<string, number | string | null>;
    result: string;
    result_label: string;
    source: string;
    source_label: string;
    phase: string;
    phase_label: string;
    device_id: number | null;
    tested_by_name: string | null;
};

export type PermitApproval = {
    id: number;
    action: string;
    action_label: string;
    note: string | null;
    signed_at: string;
    user_name: string | null;
};

export type PermitEvent = {
    id: number;
    event: string;
    payload: Record<string, unknown> | null;
    occurred_at: string;
    user_name: string | null;
};

export type PermitDetail = {
    id: number;
    permit_number: string;
    status: string;
    status_label: string;
    task_description: string;
    is_extended: boolean;
    renewal_count: number;
    gas_test_required: boolean;
    valid_from: string | null;
    valid_to: string | null;
    issued_at: string | null;
    closed_at: string | null;
    close_note: string | null;
    cancel_reason: string | null;
    joint_inspection_at: string | null;
    checklist: Record<string, unknown> | null;
    controls: Record<string, unknown> | null;
    source: string;
    type: {
        id: number;
        code: string;
        name: string;
        colour_token: string | null;
        sa_form_code: string | null;
    } | null;
    zone: {
        id: number;
        name: string;
        requires_permit: boolean;
    } | null;
    work_order_id: number | null;
    receiver: { id: number; name: string } | null;
    issuer: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
    personnel: PermitPersonnel[];
    gas_tests: PermitGasTest[];
    approvals: PermitApproval[];
    events: PermitEvent[];
};

export type PermitTypeCatalogueRow = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    colour_token: string | null;
    sa_form_code: string | null;
    requires_gas_test: boolean;
    requires_approver: boolean;
    requires_joint_inspection: boolean;
    default_validity_minutes: number;
    is_active: boolean;
    roles_count: number;
    gas_channels_count: number;
    checklist_items_count: number;
    document_requirements_count: number;
};

export type WorkerOption = {
    id: number;
    label: string;
    reference: string | null;
};

export type ZoneOption = {
    id: number;
    name: string;
    requires_permit: boolean;
};

export type PaginatedPermits = {
    data: PermitListItem[];
    meta: PaginatedMeta;
};
