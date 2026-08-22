import { PpeSnapshotStill } from '@/components/ir4/ppe-snapshot-still';

export type AlertPrefillCardProps = {
    typeLabel?: string | null;
    cameraName?: string | null;
    cameraRef?: string | null;
    locationLabel?: string | null;
    violationLabel?: string | null;
    confidence?: number | null;
    snapshotUrl?: string | null;
};

function Fact({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm text-text">{value}</dd>
        </div>
    );
}

export function AlertPrefillCard({
    typeLabel,
    cameraName,
    cameraRef,
    locationLabel,
    violationLabel,
    confidence,
    snapshotUrl,
}: AlertPrefillCardProps) {
    const camera = cameraName ?? cameraRef ?? locationLabel ?? null;
    const type = violationLabel ?? typeLabel ?? null;
    const hasFacts =
        type !== null ||
        camera !== null ||
        (confidence !== null && confidence !== undefined);

    return (
        <div className="overflow-hidden rounded-md border border-border bg-muted/20">
            <PpeSnapshotStill
                url={snapshotUrl ?? null}
                alt={type ?? 'Alert snapshot'}
                className="aspect-video w-full bg-muted/40 object-contain"
            />
            {hasFacts ? (
                <dl className="grid gap-3 p-3 sm:grid-cols-2">
                    <Fact label="Type" value={type} />
                    <Fact label="Camera" value={camera} />
                    <Fact
                        label="Confidence"
                        value={
                            confidence === null || confidence === undefined
                                ? null
                                : String(confidence)
                        }
                    />
                </dl>
            ) : null}
        </div>
    );
}
