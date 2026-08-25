import type { RoiViolationType } from '@/types/enums';

export type RoiViolation = {
    id: number;
    uuid: string;
    camera_id: number;
    device_id: number | null;
    camera_ref: string | null;
    camera_name: string | null;
    camera_roi_id: number | null;
    roi_reference: string;
    roi_name: string | null;
    event_type: RoiViolationType;
    detected_at: string;
    confidence: number | null;
    location_label: string | null;
    alert_id: number | null;
    review_status: string;
    reviewed_by: number | null;
    reviewed_by_name: string | null;
    reviewed_at: string | null;
    review_note: string | null;
    is_backfill: boolean;
    snapshot_url: string | null;
};
