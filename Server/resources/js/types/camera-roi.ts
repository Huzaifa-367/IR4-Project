import type {
    CameraRoiSetStatus,
    CameraRoiStaleReason,
} from '@/types/enums';

export type RoiPoint = {
    x: number;
    y: number;
};

export type CameraRoi = {
    id?: number;
    name: string;
    reference: string;
    polygon: RoiPoint[];
    color: string;
    sort_order: number;
    is_enabled: boolean;
    meta?: Record<string, string | number | boolean | null> | null;
};

export type CameraRoiSet = {
    id: number;
    status: CameraRoiSetStatus;
    view_fingerprint: string;
    published_at: string | null;
    stale_at: string | null;
    stale_reason: CameraRoiStaleReason | null;
    rois: CameraRoi[];
};

export type CameraRoiOverlay = {
    status: CameraRoiSetStatus;
    stale_reason: CameraRoiStaleReason | null;
    rois: CameraRoi[];
};

export type CameraRoiIndexRow = {
    id: number;
    uuid: string;
    name: string;
    reference: string;
    location_label: string | null;
    roi_status: CameraRoiSetStatus | null;
    roi_count: number;
    stale_reason: CameraRoiStaleReason | null;
    published_at: string | null;
};
