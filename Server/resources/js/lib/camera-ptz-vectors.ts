export const PTZ_SPEED = 45;

export type PtzMoveKey =
    | 'up'
    | 'down'
    | 'left'
    | 'right'
    | 'zoom-in'
    | 'zoom-out';

export type PtzMoveVector = {
    pan: number;
    tilt: number;
    zoom: number;
};

/** Hikvision ISAPI continuous PTZ vectors (-100..100). */
export const ptzMoveVectors: Record<PtzMoveKey, PtzMoveVector> = {
    up: { pan: 0, tilt: PTZ_SPEED, zoom: 0 },
    down: { pan: 0, tilt: -PTZ_SPEED, zoom: 0 },
    left: { pan: -PTZ_SPEED, tilt: 0, zoom: 0 },
    right: { pan: PTZ_SPEED, tilt: 0, zoom: 0 },
    'zoom-in': { pan: 0, tilt: 0, zoom: PTZ_SPEED },
    'zoom-out': { pan: 0, tilt: 0, zoom: -PTZ_SPEED },
};

export const ptzMoveKeys = Object.keys(ptzMoveVectors) as PtzMoveKey[];
