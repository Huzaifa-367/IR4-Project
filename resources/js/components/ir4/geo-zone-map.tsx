import maplibregl, { setWorkerUrl } from 'maplibre-gl';
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-csp-worker.js?url';
import 'maplibre-gl/dist/maplibre-gl.css';
import { useEffect, useRef, useState } from 'react';
import { circlePolygon } from '@/lib/geo';

// In dev, Vite serves assets from a different origin/port than the app, and
// browsers refuse to construct a Worker from a cross-origin URL directly.
// Fetch the worker script and re-host it as a same-origin blob: URL instead.
const workerReady = fetch(maplibreWorkerUrl)
    .then((response) => response.text())
    .then((source) => {
        const blobUrl = URL.createObjectURL(
            new Blob([source], { type: 'application/javascript' }),
        );
        setWorkerUrl(blobUrl);
    })
    .catch(() => undefined);

/** Gulf region basemap: local country outlines + city labels, no remote tiles or fonts (DOC-06 offline map). */
const GULF_CENTER: [number, number] = [48.5, 24.2];

const BASE_STYLE: maplibregl.StyleSpecification = {
    version: 8,
    sources: {
        countries: { type: 'geojson', data: '/maps/gulf-countries.geojson' },
    },
    layers: [
        {
            id: 'sea',
            type: 'background',
            paint: { 'background-color': '#0c2536' },
        },
        {
            id: 'country-fill',
            type: 'fill',
            source: 'countries',
            paint: { 'fill-color': '#d9c39a', 'fill-opacity': 0.95 },
        },
        {
            id: 'country-outline',
            type: 'line',
            source: 'countries',
            paint: {
                'line-color': '#e0473f',
                'line-width': 1.5,
                'line-dasharray': [2, 1.5],
            },
        },
    ],
};

type CityFeature = { name: string };

async function loadCities(): Promise<
    Array<{ name: string; lng: number; lat: number }>
> {
    const response = await fetch('/maps/gulf-cities.geojson');
    const data = (await response.json()) as {
        features: Array<{
            properties: CityFeature;
            geometry: { coordinates: [number, number] };
        }>;
    };

    return data.features.map((feature) => ({
        name: feature.properties.name,
        lng: feature.geometry.coordinates[0],
        lat: feature.geometry.coordinates[1],
    }));
}

function cityLabelElement(name: string): HTMLDivElement {
    const el = document.createElement('div');
    el.className =
        'pointer-events-none rounded-pill bg-[#0c2536]/70 px-1.5 py-0.5 text-[10px] font-semibold whitespace-nowrap text-white shadow';
    el.textContent = name;

    return el;
}

function cityDotElement(): HTMLDivElement {
    const el = document.createElement('div');
    el.className = 'size-1.5 rounded-full bg-white/80';

    return el;
}

/** Creates the offline Gulf basemap once and exposes the live instance via state. */
function useBaseMap(
    containerRef: React.RefObject<HTMLDivElement | null>,
    center: [number, number],
    zoom: number,
): maplibregl.Map | null {
    const [map, setMap] = useState<maplibregl.Map | null>(null);

    useEffect(() => {
        if (!containerRef.current) {
            return;
        }

        let cancelled = false;
        let instance: maplibregl.Map | null = null;
        let resizeObserver: ResizeObserver | null = null;

        workerReady.then(() => {
            if (cancelled || !containerRef.current) {
                return;
            }

            instance = new maplibregl.Map({
                container: containerRef.current,
                style: BASE_STYLE,
                center,
                zoom,
                attributionControl: false,
                dragRotate: false,
                pitchWithRotate: false,
                touchPitch: false,
            });

            instance.on('error', (event) => {
                console.error('Gulf basemap error', event.error);
            });

            // The container's real size (aspect-ratio/flex layout) isn't known
            // synchronously at construction, so the canvas can init at a stale
            // fallback size — keep it in sync as layout settles.
            resizeObserver = new ResizeObserver(() => instance?.resize());
            resizeObserver.observe(containerRef.current);

            loadCities()
                .then((cities) => {
                    cities.forEach((city) => {
                        new maplibregl.Marker({ element: cityDotElement() })
                            .setLngLat([city.lng, city.lat])
                            .addTo(instance as maplibregl.Map);
                        new maplibregl.Marker({
                            element: cityLabelElement(city.name),
                            anchor: 'left',
                            offset: [6, 0],
                        })
                            .setLngLat([city.lng, city.lat])
                            .addTo(instance as maplibregl.Map);
                    });
                })
                .catch(() => undefined);

            setMap(instance);
        });

        return () => {
            cancelled = true;
            resizeObserver?.disconnect();
            instance?.remove();
            setMap(null);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return map;
}

type ViewZone = {
    id: number;
    name: string;
    color?: string | null;
    latitude: string | number | null;
    longitude: string | number | null;
    radius_meters: string | number | null;
};

type ZoneMapViewProps = {
    zones: ViewZone[];
    onSelect?: (zone: ViewZone) => void;
    className?: string;
};

/** Read-only Gulf map plotting every zone with a location as a geofence circle. */
export function GeoZoneMapView({
    zones,
    onSelect,
    className = '',
}: ZoneMapViewProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const map = useBaseMap(containerRef, GULF_CENTER, 5);
    const located = zones.filter((z) => z.latitude && z.longitude);
    const locatedKey = JSON.stringify(located);

    useEffect(() => {
        if (!map) {
            return;
        }

        const markers: maplibregl.Marker[] = [];

        function render(): void {
            const sourceId = 'zone-circles';
            const geojson: GeoJSON.FeatureCollection = {
                type: 'FeatureCollection',
                features: located.map((zone) => ({
                    type: 'Feature',
                    properties: { color: zone.color ?? '#6366f1' },
                    geometry: {
                        type: 'Polygon',
                        coordinates: [
                            circlePolygon(
                                Number(zone.latitude),
                                Number(zone.longitude),
                                Number(zone.radius_meters) || 60,
                            ),
                        ],
                    },
                })),
            };

            const existing = map?.getSource(sourceId) as
                maplibregl.GeoJSONSource | undefined;

            if (existing) {
                existing.setData(geojson);
            } else {
                map?.addSource(sourceId, { type: 'geojson', data: geojson });
                map?.addLayer({
                    id: 'zone-circle-fill',
                    type: 'fill',
                    source: sourceId,
                    paint: {
                        'fill-color': ['get', 'color'],
                        'fill-opacity': 0.35,
                    },
                });
                map?.addLayer({
                    id: 'zone-circle-outline',
                    type: 'line',
                    source: sourceId,
                    paint: {
                        'line-color': ['get', 'color'],
                        'line-width': 2,
                    },
                });
            }

            located.forEach((zone) => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className =
                    'rounded-pill border border-white/70 bg-[color:var(--accent)] px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-white shadow-[var(--shadow-pop)] hover:brightness-110';
                el.textContent = zone.name;
                el.onclick = () => onSelect?.(zone);
                const marker = new maplibregl.Marker({
                    element: el,
                    anchor: 'bottom',
                })
                    .setLngLat([Number(zone.longitude), Number(zone.latitude)])
                    .addTo(map as maplibregl.Map);
                markers.push(marker);
            });

            if (located.length > 0) {
                const bounds = located.reduce(
                    (b, z) =>
                        b.extend([Number(z.longitude), Number(z.latitude)]),
                    new maplibregl.LngLatBounds(),
                );
                map?.fitBounds(bounds, {
                    padding: 60,
                    maxZoom: 15,
                    duration: 0,
                });
            }
        }

        if (map.isStyleLoaded()) {
            render();
        } else {
            map.once('load', render);
        }

        return () => {
            markers.forEach((marker) => marker.remove());
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [map, locatedKey]);

    return (
        <div
            ref={containerRef}
            className={`aspect-[16/10] w-full overflow-hidden rounded-[var(--radius-sm)] border border-border ${className}`}
        />
    );
}

type ZonePickerProps = {
    latitude: number | null;
    longitude: number | null;
    radiusMeters: number;
    color?: string;
    onChange: (lat: number, lng: number) => void;
    className?: string;
};

/** Interactive Gulf map picker — click or drag the pin to set a zone's real coordinates. */
export function GeoZonePicker({
    latitude,
    longitude,
    radiusMeters,
    color = '#6366f1',
    onChange,
    className = '',
}: ZonePickerProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const initialCenter: [number, number] =
        longitude && latitude ? [longitude, latitude] : GULF_CENTER;
    const map = useBaseMap(containerRef, initialCenter, latitude ? 14 : 5);
    const markerRef = useRef<maplibregl.Marker | null>(null);
    const onChangeRef = useRef(onChange);

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    useEffect(() => {
        if (!map) {
            return;
        }

        const mapInstance = map;

        function placeMarker(lat: number, lng: number): void {
            if (markerRef.current) {
                markerRef.current.setLngLat([lng, lat]);

                return;
            }

            const el = document.createElement('div');
            el.className =
                'size-4 -translate-y-1 rounded-full border-2 border-white shadow-[var(--shadow-pop)]';
            el.style.backgroundColor = color;
            const marker = new maplibregl.Marker({
                element: el,
                draggable: true,
            })
                .setLngLat([lng, lat])
                .addTo(mapInstance);

            marker.on('dragend', () => {
                const pos = marker.getLngLat();
                onChangeRef.current(pos.lat, pos.lng);
            });
            markerRef.current = marker;
        }

        function onClick(event: maplibregl.MapMouseEvent): void {
            const { lat, lng } = event.lngLat;
            placeMarker(lat, lng);
            onChangeRef.current(lat, lng);
        }

        map.on('click', onClick);

        if (latitude && longitude) {
            placeMarker(latitude, longitude);
        }

        return () => {
            map.off('click', onClick);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [map]);

    useEffect(() => {
        if (markerRef.current && latitude && longitude) {
            markerRef.current.setLngLat([longitude, latitude]);
        }
    }, [latitude, longitude]);

    useEffect(() => {
        if (!map || !latitude || !longitude) {
            return;
        }

        function render(): void {
            if (!latitude || !longitude) {
                return;
            }

            const sourceId = 'picker-radius';
            const geojson: GeoJSON.Feature = {
                type: 'Feature',
                properties: {},
                geometry: {
                    type: 'Polygon',
                    coordinates: [
                        circlePolygon(latitude, longitude, radiusMeters),
                    ],
                },
            };
            const existing = map?.getSource(sourceId) as
                maplibregl.GeoJSONSource | undefined;

            if (existing) {
                existing.setData(geojson);
            } else {
                map?.addSource(sourceId, { type: 'geojson', data: geojson });
                map?.addLayer({
                    id: 'picker-radius-fill',
                    type: 'fill',
                    source: sourceId,
                    paint: { 'fill-color': color, 'fill-opacity': 0.25 },
                });
                map?.addLayer({
                    id: 'picker-radius-outline',
                    type: 'line',
                    source: sourceId,
                    paint: { 'line-color': color, 'line-width': 2 },
                });
            }
        }

        if (map.isStyleLoaded()) {
            render();
        } else {
            map.once('load', render);
        }
    }, [map, latitude, longitude, radiusMeters, color]);

    return (
        <div
            ref={containerRef}
            className={`aspect-[16/10] w-full overflow-hidden rounded-[var(--radius-sm)] border border-border ${className}`}
        />
    );
}
