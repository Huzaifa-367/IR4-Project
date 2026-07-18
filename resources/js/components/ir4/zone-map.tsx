import type { TrackingPosition, TrackingZone } from '@/types/tracking';

type Occupancy = Array<{ zone_id: number; zone_name: string; count: number }>;

type Props = {
    zones: TrackingZone[];
    positions: TrackingPosition[];
    occupancy?: Occupancy;
};

export function ZoneMap({ zones, positions, occupancy = [] }: Props) {
    const countByZone = new Map(
        occupancy.map((row) => [row.zone_id, row.count]),
    );

    return (
        <div className="relative aspect-[16/10] w-full overflow-hidden rounded-[var(--radius)] border border-border bg-surface-2">
            <svg
                viewBox="0 0 100 100"
                className="absolute inset-0 h-full w-full text-text-dim"
            >
                {zones.map((zone) => {
                    const x = Number(zone.map_x ?? 50);
                    const y = Number(zone.map_y ?? 50);
                    const r = Number(zone.map_radius ?? 8);
                    const count = countByZone.get(zone.id);

                    return (
                        <g key={zone.id}>
                            <circle
                                cx={x}
                                cy={y}
                                r={r}
                                fill={zone.color ?? 'var(--accent)'}
                                fillOpacity={0.22}
                                stroke={zone.color ?? 'var(--accent)'}
                                strokeWidth={0.4}
                            />
                            <text
                                x={x}
                                y={Math.max(4, y - r - 1)}
                                textAnchor="middle"
                                fontSize={2.5}
                                fill="var(--text-dim)"
                            >
                                {zone.name}
                                {count !== undefined ? ` (${count})` : ''}
                            </text>
                        </g>
                    );
                })}
                {positions.map((pos) => {
                    const zone = zones.find((z) => z.id === pos.zone_id);
                    const x = Number(zone?.map_x ?? 50) + (pos.tag_id % 5) - 2;
                    const y =
                        Number(zone?.map_y ?? 50) + (pos.worker_id % 5) - 2;

                    return (
                        <circle
                            key={pos.tag_id}
                            cx={x}
                            cy={y}
                            r={1.2}
                            fill="var(--accent)"
                        >
                            <title>{pos.worker_label}</title>
                        </circle>
                    );
                })}
            </svg>
            {zones.length === 0 && (
                <div className="absolute inset-0 flex items-center justify-center text-sm text-text-faint">
                    No zones with map placement yet.
                </div>
            )}
        </div>
    );
}
