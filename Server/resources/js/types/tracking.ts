export type HeadcountSnapshot = {
    total_on_site: number;
    by_zone: Array<{ zone_id: number; count: number; zone_name: string }>;
};

export type TrackingPosition = {
    tag_id: number;
    tag_uid?: string | null;
    worker_id: number;
    worker_label: string;
    zone_id: number | null;
    zone_name: string | null;
    last_seen_at: string;
    is_on_site: boolean;
};

export type TrackingZone = {
    id: number;
    uuid: string;
    name: string;
    zone_type: string;
    color: string | null;
    occupancy_limit?: number | null;
    reader_count?: number;
};

export type TrackingReading = {
    id: number;
    recorded_at: string;
    zone_id: number | null;
    zone_name: string | null;
    reader_id?: number | null;
    reader_ref: string | null;
    reader_name: string | null;
    tag_uid: string | null;
    worker_label: string | null;
    rssi: number | null;
    antenna: number | null;
    proximity: string | null;
    is_backfill: boolean;
};

export type TrackingCoverage = {
    device_id: number;
    device_uuid: string;
    device_name: string | null;
    reference: string | null;
    zone: {
        id: number;
        uuid: string;
        name: string;
        zone_type: string;
        color: string | null;
    } | null;
};
