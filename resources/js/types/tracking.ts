export type HeadcountSnapshot = {
    total_on_site: number;
    by_zone: Array<{ zone_id: number; count: number; zone_name: string }>;
};

export type TrackingPosition = {
    tag_id: number;
    worker_id: number;
    worker_label: string;
    zone_id: number | null;
    zone_name: string | null;
    map_x: string | number | null;
    map_y: string | number | null;
    last_seen_at: string;
    is_on_site: boolean;
};

export type TrackingZone = {
    id: number;
    name: string;
    zone_type: string;
    map_x: string | number | null;
    map_y: string | number | null;
    map_radius: string | number | null;
    latitude: string | number | null;
    longitude: string | number | null;
    radius_meters: string | number | null;
    color: string | null;
};
