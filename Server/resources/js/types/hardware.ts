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

export type CameraUnitAiDevice = {
    id: number;
    uuid: string;
    reference: string;
    has_token: boolean;
    is_online: boolean;
    last_seen_at: string | null;
    status: HardwareStatus | string;
};

export type CameraUnitRow = {
    kind: 'camera';
    id: number;
    uuid: string;
    name: string;
    reference: string;
    camera_type: CameraType | string;
    camera_type_label: string;
    stream_url: string;
    ai_enabled: boolean;
    status: HardwareStatus | string;
    is_online: boolean;
    stream_is_online: boolean;
    has_token: boolean;
    last_seen_at: string | null;
    last_frame_at: string | null;
    is_incomplete: boolean;
    api_url?: string | null;
    ai_device: CameraUnitAiDevice | null;
    asset: { id: number; uuid: string; name: string } | null;
};

export type FieldDeviceRow = {
    kind: 'device';
    id: number;
    uuid: string;
    name: string;
    reference: string;
    serial_number?: string | null;
    device_type: DeviceType | string;
    device_type_label: string;
    status: HardwareStatus | string;
    is_online: boolean;
    has_token: boolean;
    last_seen_at: string | null;
    printer_host?: string | null;
    printer_port?: number | null;
    is_orphan_camera_ai: boolean;
    asset: { id: number; uuid: string; name: string } | null;
};

export type RegistryRow = CameraUnitRow | FieldDeviceRow;

export type DeviceRow = FieldDeviceRow;

export type CameraRow = CameraUnitRow;

export type PlainDeviceToken = {
    device_id: number;
    device_name: string;
    token: string;
};
