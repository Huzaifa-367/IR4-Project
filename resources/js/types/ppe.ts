export type PpeViolation = {
    id: number;
    camera_id: number;
    camera_ref: string | null;
    camera_name: string | null;
    violation_type: string;
    detected_at: string;
    worker_count: number;
    confidence: number | null;
    location_label: string | null;
    alert_id: number | null;
    review_status: string;
    reviewed_by: number | null;
    reviewed_by_name: string | null;
    reviewed_at: string | null;
    review_note: string | null;
    is_backfill: boolean;
    snapshot_url: string;
};

export type LiveCamera = {
    id: number;
    name: string;
    reference: string;
    playback_url: string | null;
    ai_enabled: boolean;
    status: string;
    is_online: boolean;
    last_frame_at: string | null;
    location_label: string | null;
};

export type PpeSummary = {
    by_type: Record<string, number>;
    by_camera: Array<{ camera_id: number; camera_ref: string; count: number }>;
    by_hour: number[];
    false_positive_rate: number;
    excluded_false_positives: number;
    total: number;
    group_by?: string;
};
