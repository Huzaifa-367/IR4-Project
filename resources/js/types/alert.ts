export type Alert = {
    id: number;
    alert_type: string;
    alert_type_label: string;
    severity: 'info' | 'warning' | 'critical';
    title: string;
    payload: Record<string, unknown>;
    status: 'open' | 'acknowledged' | 'resolved';
    raised_at: string;
    acknowledged_by: number | null;
    acknowledged_at: string | null;
    resolved_at: string | null;
    audible: boolean;
    dedupe_key: string | null;
    occurrences: number;
    alertable_type: string | null;
    alertable_id: number | null;
};
