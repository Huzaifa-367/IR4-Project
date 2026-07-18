import type {
    EvidenceType,
    IncidentSeverity,
    IncidentSource,
    IncidentStatus,
    IncidentType,
    Involvement,
    LsrCategory,
    LsrStatus,
} from '@/types/enums';

export type HseOption = { value: string; label: string };

export type IncidentPersonnel = {
    id: number;
    worker_id: number;
    worker_label: string | null;
    involvement: Involvement | string;
    involvement_label: string;
};

export type IncidentEvidence = {
    id: number;
    evidence_type: EvidenceType | string;
    evidence_type_label: string;
    download_url: string | null;
    payload: Record<string, unknown> | null;
    ppe_violation_id: number | null;
    camera_id: number | null;
    captured_at: string | null;
    auto_captured: boolean;
    added_by_name: string | null;
};

export type HseIncident = {
    id: number;
    incident_number: string;
    source: IncidentSource | string;
    source_label: string;
    status: IncidentStatus | string;
    status_label: string;
    alert_id: number | null;
    zone_id: number | null;
    zone_name: string | null;
    camera_id: number | null;
    camera_name: string | null;
    occurred_at: string | null;
    incident_type: IncidentType | string | null;
    incident_type_label: string | null;
    severity: IncidentSeverity | string | null;
    severity_label: string | null;
    nature_of_incident: string | null;
    immediate_action: string | null;
    corrective_action: string | null;
    classified_at: string | null;
    classified_by_name: string | null;
    closed_at: string | null;
    closed_by_name: string | null;
    close_note: string | null;
    created_by_name: string | null;
    created_at: string | null;
    personnel: IncidentPersonnel[];
    evidence: IncidentEvidence[];
};

export type LsrViolation = {
    id: number;
    category: LsrCategory | string;
    category_label: string;
    occurred_at: string | null;
    worker_id: number | null;
    worker_label: string | null;
    zone_id: number | null;
    zone_name: string | null;
    camera_id: number | null;
    alert_id: number | null;
    ppe_violation_id: number | null;
    description: string | null;
    action_taken: string | null;
    status: LsrStatus | string;
    status_label: string;
    closed_at: string | null;
    closed_by_name: string | null;
    logged_by_name: string | null;
    created_at: string | null;
};

export type IncidentPrefill = {
    source: string;
    alert_id: number;
    occurred_at: string | null;
    zone_id: number | null;
    camera_id: number | null;
    nature_of_incident: string | null;
    suggested_action: string | null;
    snapshot_path: string | null;
    ppe_violation_id: number | null;
    alert: {
        id: number;
        alert_type: string;
        title: string;
        raised_at: string | null;
    };
};

export type LsrPrefill = {
    category: string;
    occurred_at: string | null;
    worker_id: number | null;
    zone_id: number | null;
    camera_id: number | null;
    alert_id: number;
    ppe_violation_id: number | null;
    description: string | null;
    alert: {
        id: number;
        alert_type: string;
        title: string;
    };
};
