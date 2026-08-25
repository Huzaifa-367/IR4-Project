import type { StatusPillTone } from '@/components/ir4/status-pill';
import {
    CameraRoiSetStatus,
    CameraRoiSetStatusLabels,
} from '@/types/enums';
import type { CameraRoiSetStatus as RoiStatus } from '@/types/enums';

export function cameraRoiStatusTone(status: string | null): StatusPillTone {
    switch (status) {
        case CameraRoiSetStatus.Active:
            return 'ok';
        case CameraRoiSetStatus.Stale:
            return 'warn';
        case CameraRoiSetStatus.Draft:
            return 'info';
        default:
            return 'neutral';
    }
}

export function cameraRoiStatusLabel(status: string | null): string {
    if (status && status in CameraRoiSetStatusLabels) {
        return CameraRoiSetStatusLabels[status as RoiStatus];
    }

    return 'None';
}
