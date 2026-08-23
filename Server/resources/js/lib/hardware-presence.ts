import type { StatusPillTone } from '@/components/ir4/status-pill';

/**
 * Operator pill label: keep maintenance / retired / fault / degraded;
 * otherwise show live presence (online / offline).
 */
export function hardwarePresenceLabel(status: string, isOnline: boolean): string {
    if (
        status === 'maintenance' ||
        status === 'retired' ||
        status === 'fault' ||
        status === 'degraded'
    ) {
        return status;
    }

    return isOnline ? 'online' : 'offline';
}

export function hardwareStatusTone(status: string): StatusPillTone {
    if (status === 'online') {
        return 'ok';
    }

    if (status === 'maintenance' || status === 'degraded') {
        return 'warn';
    }

    if (status === 'retired' || status === 'fault' || status === 'offline') {
        return 'crit';
    }

    return 'neutral';
}
