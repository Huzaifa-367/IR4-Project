import type {
    CheckoutState,
    EquipmentStatus,
    InspectionOutcome,
    MaintenanceType,
    ReturnStatus,
    ScheduleType,
} from '@/types/enums';

export type {
    CheckoutState,
    EquipmentStatus,
    InspectionOutcome,
    MaintenanceType,
    ReturnStatus,
    ScheduleType,
};

export {
    ReturnStatus as ReturnStatusValues,
    ReturnStatusLabels,
} from '@/types/enums';

export type EquipmentWorkerRef = {
    id: number;
    name: string;
};

export type EquipmentZoneRef = {
    id: number;
    name: string;
};

export type EquipmentUserRef = {
    id: number;
    name: string;
};

export type EquipmentCheckout = {
    id: number;
    equipment_id: number;
    worker_id: number;
    worker: EquipmentWorkerRef | null;
    checked_out_at: string;
    checked_out_by: number | null;
    checked_out_by_user: EquipmentUserRef | null;
    reason: string | null;
    zone_id: number | null;
    zone: EquipmentZoneRef | null;
    expected_return_at: string | null;
    returned_at: string | null;
    returned_to: number | null;
    returned_to_user: EquipmentUserRef | null;
    condition_out: string | null;
    condition_in: string | null;
    return_status: ReturnStatus | string | null;
    return_reason: string | null;
    notes: string | null;
    is_overdue_return?: boolean;
    equipment?: Pick<
        Equipment,
        | 'id'
        | 'equipment_code'
        | 'name'
        | 'equipment_type'
        | 'status'
        | 'checkout_state'
    >;
};

export type EquipmentInspection = {
    id: number;
    equipment_id: number;
    inspected_at: string;
    outcome: InspectionOutcome;
    outcome_label?: string;
    notes: string | null;
    inspector_id: number | null;
    inspector: EquipmentUserRef | null;
    next_due: string | null;
    created_at: string | null;
};

export type EquipmentMaintenance = {
    id: number;
    equipment_id: number;
    performed_at: string;
    maintenance_type: MaintenanceType;
    maintenance_type_label?: string;
    description: string;
    performed_by_name: string | null;
    recorded_by: number | null;
    recorded_by_user: EquipmentUserRef | null;
    next_due: string | null;
    created_at: string | null;
};

export type MaintenanceSchedule = {
    id: number;
    equipment_id: number;
    schedule_type: ScheduleType;
    schedule_type_label?: string;
    interval_days: number;
    notes: string | null;
};

export type EquipmentDocument = {
    id: number;
    equipment_id: number;
    title: string;
    mime: string;
    uploaded_by: number | null;
    uploaded_by_user: EquipmentUserRef | null;
    download_url: string | null;
    created_at: string | null;
};

export type Equipment = {
    id: number;
    equipment_code: string;
    qr_token: string;
    name: string;
    equipment_type: string;
    status: EquipmentStatus;
    status_label?: string;
    is_checkoutable: boolean;
    location_label: string | null;
    description: string | null;
    next_inspection_due: string | null;
    next_service_due: string | null;
    checkout_state: CheckoutState;
    is_inspection_overdue: boolean;
    is_service_overdue: boolean;
    is_due_soon: boolean;
    open_checkout: EquipmentCheckout | null;
    created_at: string | null;
    updated_at: string | null;
};

export type EquipmentDetail = Equipment & {
    inspections: EquipmentInspection[];
    maintenances: EquipmentMaintenance[];
    schedules: MaintenanceSchedule[];
    documents: EquipmentDocument[];
    checkouts: EquipmentCheckout[];
};

/** Authenticated scan lookup payload (`GET /api/equipment/by-token/{qr_token}`). */
export type EquipmentByToken = Equipment & {
    open_checkout: EquipmentCheckout | null;
};

export type PublicEquipmentRecord = {
    qr_token: string;
    equipment_code: string;
    name: string;
    equipment_type: string;
    status: EquipmentStatus;
    status_label?: string;
    description: string | null;
    location_label: string | null;
    next_inspection_due: string | null;
    next_service_due: string | null;
    checkout_state: CheckoutState;
    custody_label: string | null;
    inspections: Array<{
        inspected_at: string;
        outcome: InspectionOutcome;
        notes: string | null;
    }>;
    maintenances: Array<{
        performed_at: string;
        maintenance_type: MaintenanceType;
        description: string;
    }>;
    schedules: Array<{
        schedule_type: ScheduleType;
        interval_days: number;
        notes: string | null;
    }>;
    documents: Array<{
        title: string;
        download_url: string;
    }>;
};

export type EquipmentImportResult = {
    created: number;
    updated: number;
    skipped: number;
    errors: Array<{ row: number; message: string }>;
    /** Backend `compact()` key from ImportEquipmentJob summary. */
    createdIds?: number[];
    created_ids?: number[];
    updated_ids?: number[];
};

export type EquipmentListFilters = {
    search: string;
    equipment_type: string;
    status: string;
    overdue: boolean | null;
    checkout_state: string;
    sort: string;
    direction: string;
};

export type PaginatedEquipment = {
    data: Equipment[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

export type EquipmentOption = {
    value: string;
    label: string;
};
