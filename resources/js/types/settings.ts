export type SettingType =
    | 'bool'
    | 'int'
    | 'float'
    | 'string'
    | 'timezone'
    | 'time'
    | 'enum';

export type SettingGroupKey =
    | 'general'
    | 'auth'
    | 'alerts'
    | 'ingest'
    | 'health'
    | 'tracking'
    | 'gas'
    | 'environment'
    | 'equipment'
    | 'reports'
    | 'retention'
    | 'display';

export type SettingSchema = {
    key: string;
    label: string;
    description: string | null;
    type: SettingType;
    unit: string | null;
    value: string | number | boolean | null;
    default: string | number | boolean | null;
    min: number | null;
    max: number | null;
    options: string[] | null;
    requires_confirm: boolean;
    editable: boolean;
    permission: string;
    updated_at: string | null;
    updated_by: { id: number; name: string } | null;
};

export type SettingGroup = {
    key: SettingGroupKey | string;
    label: string;
    settings: SettingSchema[];
};

export type AppSettings = Record<string, string | number | boolean | null>;
