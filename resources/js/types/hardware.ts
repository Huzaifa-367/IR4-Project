import type {
    AssetStatus,
    AssetType,
    CameraType,
    DeviceType,
    HardwareStatus,
} from '@/types/enums';

export type PaginatedMeta = {
    current_page: number;
    last_page: number;
    total: number;
    per_page?: number;
};

export type Paginated<T> = {
    data: T[];
    meta: PaginatedMeta;
};

export type HardwareOption = {
    value: string;
    label: string;
};

export type AssetRow = {
    id: number;
    uuid: string;
    name: string;
    identifier: string;
    asset_type: AssetType | string;
    asset_type_label: string;
    status: AssetStatus | string;
    is_mobile: boolean;
    current_location_label?: string | null;
    last_heartbeat_at?: string | null;
    cameras_count: number;
    devices_count: number;
};

export type DeviceRow = {
    id: number;
    uuid: string;
    name: string;
    reference: string;
    serial_number?: string | null;
    device_type: DeviceType | string;
    device_type_label: string;
    status: HardwareStatus | string;
    has_token: boolean;
    last_seen_at: string | null;
    asset: { id: number; uuid: string; name: string } | null;
};

export type CameraRow = {
    id: number;
    uuid: string;
    name: string;
    reference: string;
    camera_type: CameraType | string;
    camera_type_label?: string;
    status: HardwareStatus | string;
    ai_enabled: boolean;
    last_frame_at: string | null;
    stream_url?: string | null;
    asset: { id: number; uuid: string; name: string } | null;
};

export type PlainDeviceToken = {
    device_id: number;
    device_name: string;
    token: string;
};
