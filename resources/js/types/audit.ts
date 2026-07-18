export type AuditEvent =
    | 'login'
    | 'logout'
    | 'login_failed'
    | 'data_access'
    | 'created'
    | 'updated'
    | 'deleted'
    | 'config_changed'
    | 'published'
    | 'acknowledged'
    | 'exported'
    | 'wiped';

export type AuditDiff = Record<string, unknown>;

export type AuditLog = {
    id: number;
    event: AuditEvent;
    user: { id: number; name: string } | null;
    auditable_type: string | null;
    auditable_label: string | null;
    auditable_id: number | string | null;
    description: string | null;
    old_values: AuditDiff | null;
    new_values: AuditDiff | null;
    ip_address: string | null;
    user_agent: string | null;
    route: string | null;
    occurred_at: string;
};
