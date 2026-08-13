import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    HeadcountSnapshot,
    TrackingCoverage,
    TrackingPosition,
    TrackingReading,
    TrackingZone,
} from '@/types/tracking';

type OccupancyProps = {
    zones: TrackingZone[];
    occupancy?: HeadcountSnapshot['by_zone'];
    onSelect?: (zone: TrackingZone) => void;
};

export function ZoneOccupancyTable({
    zones,
    occupancy = [],
    onSelect,
}: OccupancyProps) {
    const counts = new Map(occupancy.map((row) => [row.zone_id, row.count]));

    if (zones.length === 0) {
        return (
            <p className="px-3 py-6 text-sm text-text-faint">
                No active zones.
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead>Zone</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead className="text-right">On site</TableHead>
                    <TableHead className="text-right">Limit</TableHead>
                    <TableHead className="text-right">Readers</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {zones.map((zone) => {
                    const count = counts.get(zone.id) ?? 0;
                    const over =
                        zone.occupancy_limit != null &&
                        count > zone.occupancy_limit;

                    return (
                        <TableRow
                            key={zone.id}
                            className={onSelect ? 'cursor-pointer' : undefined}
                            onClick={() => onSelect?.(zone)}
                        >
                            <TableCell className="font-medium">
                                <span
                                    className="mr-2 inline-block size-2 rounded-full"
                                    style={{
                                        background:
                                            zone.color ?? 'var(--accent)',
                                    }}
                                />
                                {zone.name}
                            </TableCell>
                            <TableCell className="text-text-dim capitalize">
                                {zone.zone_type.replaceAll('_', ' ')}
                            </TableCell>
                            <TableCell
                                className={`text-right font-mono tabular-nums ${over ? 'text-[color:var(--crit)]' : ''}`}
                            >
                                {count}
                            </TableCell>
                            <TableCell className="text-right font-mono text-text-faint tabular-nums">
                                {zone.occupancy_limit ?? '—'}
                            </TableCell>
                            <TableCell className="text-right font-mono text-text-faint tabular-nums">
                                {zone.reader_count ?? '—'}
                            </TableCell>
                        </TableRow>
                    );
                })}
            </TableBody>
        </Table>
    );
}

type PresenceProps = {
    positions: TrackingPosition[];
};

export function ZonePresenceTable({ positions }: PresenceProps) {
    if (positions.length === 0) {
        return (
            <p className="px-3 py-6 text-sm text-text-faint">
                No one currently on site.
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead>Person</TableHead>
                    <TableHead>Tag</TableHead>
                    <TableHead>Zone</TableHead>
                    <TableHead>Last seen</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {positions.map((row) => (
                    <TableRow key={row.tag_id}>
                        <TableCell className="font-medium">
                            {row.worker_label}
                        </TableCell>
                        <TableCell className="font-mono text-xs text-text-dim">
                            {row.tag_uid ?? `#${row.tag_id}`}
                        </TableCell>
                        <TableCell>{row.zone_name ?? 'Unbound'}</TableCell>
                        <TableCell className="font-mono text-xs text-text-faint">
                            {new Date(row.last_seen_at).toLocaleString()}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

type CoverageProps = {
    coverage: TrackingCoverage[];
};

export function ZoneCoverageTable({ coverage }: CoverageProps) {
    if (coverage.length === 0) {
        return (
            <p className="px-3 py-6 text-sm text-text-faint">
                No RFID readers registered.
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead>Reader</TableHead>
                    <TableHead>Reference</TableHead>
                    <TableHead>Bound zone</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {coverage.map((row) => (
                    <TableRow key={row.device_id}>
                        <TableCell className="font-medium">
                            {row.device_name ?? '—'}
                        </TableCell>
                        <TableCell className="font-mono text-xs text-text-dim">
                            {row.reference ?? '—'}
                        </TableCell>
                        <TableCell>
                            {row.zone ? (
                                row.zone.name
                            ) : (
                                <span className="text-[color:var(--warn)]">
                                    Unbound
                                </span>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

type ReadingsProps = {
    readings: TrackingReading[];
};

export function ZoneReadingsTable({ readings }: ReadingsProps) {
    if (readings.length === 0) {
        return (
            <p className="px-3 py-6 text-sm text-text-faint">
                No tag readings for this filter.
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead>Time</TableHead>
                    <TableHead>Zone</TableHead>
                    <TableHead>Reader</TableHead>
                    <TableHead>Tag</TableHead>
                    <TableHead>Person</TableHead>
                    <TableHead className="text-right">RSSI</TableHead>
                    <TableHead className="text-right">Ant</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {readings.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell className="font-mono text-xs whitespace-nowrap">
                            {new Date(row.recorded_at).toLocaleString()}
                        </TableCell>
                        <TableCell>{row.zone_name ?? '—'}</TableCell>
                        <TableCell className="font-mono text-xs text-text-dim">
                            {row.reader_ref ?? row.reader_name ?? '—'}
                        </TableCell>
                        <TableCell className="font-mono text-xs">
                            {row.tag_uid ?? '—'}
                        </TableCell>
                        <TableCell>{row.worker_label ?? '—'}</TableCell>
                        <TableCell className="text-right font-mono text-text-faint tabular-nums">
                            {row.rssi ?? '—'}
                        </TableCell>
                        <TableCell className="text-right font-mono text-text-faint tabular-nums">
                            {row.antenna ?? '—'}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
