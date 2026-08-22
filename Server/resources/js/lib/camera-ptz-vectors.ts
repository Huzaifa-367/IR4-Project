/** Relative pan/tilt step per click (degrees). */
export const PTZ_PAN_TILT_DEGREES = 30;

/** Relative zoom step per click (Hikvision ISAPI zoom unit). */
export const PTZ_ZOOM_STEP = 1;

export type PtzMoveKey =
    'up' | 'down' | 'left' | 'right' | 'zoom-in' | 'zoom-out';

export type PtzMoveVector = {
    pan: number;
    tilt: number;
    zoom: number;
};

/** Step size sent to the API; backend maps this to a short continuous burst (~few degrees). */
export const ptzMoveVectors: Record<PtzMoveKey, PtzMoveVector> = {
    up: { pan: 0, tilt: PTZ_PAN_TILT_DEGREES, zoom: 0 },
    down: { pan: 0, tilt: -PTZ_PAN_TILT_DEGREES, zoom: 0 },
    left: { pan: -PTZ_PAN_TILT_DEGREES, tilt: 0, zoom: 0 },
    right: { pan: PTZ_PAN_TILT_DEGREES, tilt: 0, zoom: 0 },
    'zoom-in': { pan: 0, tilt: 0, zoom: PTZ_ZOOM_STEP },
    'zoom-out': { pan: 0, tilt: 0, zoom: -PTZ_ZOOM_STEP },
};

export const ptzMoveKeys = Object.keys(ptzMoveVectors) as PtzMoveKey[];
