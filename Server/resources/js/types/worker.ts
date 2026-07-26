export type Worker = {
    id: number;
    uuid: string;
    name: string;
    contractor: string;
    role_title: string | null;
    worker_type: string;
    worker_type_label: string;
    is_active: boolean;
    present: boolean;
    last_seen_at: string | null;
    notes: string | null;
    created_at: string | null;
    badge_number: string | null;
    photo_url: string | null;
    phone: string | null;
    employee_code: string | null;
    can_see_identity: boolean;
};

export type WorkerListFilters = {
    search: string;
    contractor: string;
    worker_type: string;
    is_active: boolean;
    present: boolean | null;
    sort: string;
    direction: string;
};

export type WorkerImportSummary = {
    created: number;
    updated: number;
    skipped: number;
    errors: Array<{ row: number; message: string }>;
    flagged: Array<{ row: number; message: string }>;
};
