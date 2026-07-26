const EARTH_RADIUS_METERS = 6378137;

/** Geodesic destination point given a start coordinate, bearing (deg), and distance (m). */
function destinationPoint(
    lat: number,
    lng: number,
    distanceMeters: number,
    bearingDeg: number,
): [number, number] {
    const angularDistance = distanceMeters / EARTH_RADIUS_METERS;
    const bearing = (bearingDeg * Math.PI) / 180;
    const lat1 = (lat * Math.PI) / 180;
    const lng1 = (lng * Math.PI) / 180;

    const lat2 = Math.asin(
        Math.sin(lat1) * Math.cos(angularDistance) +
            Math.cos(lat1) * Math.sin(angularDistance) * Math.cos(bearing),
    );
    const lng2 =
        lng1 +
        Math.atan2(
            Math.sin(bearing) * Math.sin(angularDistance) * Math.cos(lat1),
            Math.cos(angularDistance) - Math.sin(lat1) * Math.sin(lat2),
        );

    return [(lng2 * 180) / Math.PI, (lat2 * 180) / Math.PI];
}

/** Builds a geodesic circle polygon (GeoJSON [lng, lat] ring) around a center point. */
export function circlePolygon(
    lat: number,
    lng: number,
    radiusMeters: number,
    points = 64,
): [number, number][] {
    const ring: [number, number][] = [];

    for (let i = 0; i <= points; i++) {
        const bearing = (360 * i) / points;
        ring.push(destinationPoint(lat, lng, radiusMeters, bearing));
    }

    return ring;
}
