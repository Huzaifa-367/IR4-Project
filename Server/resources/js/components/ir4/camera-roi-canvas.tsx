import { useId, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type { CameraRoi, RoiPoint } from '@/types/camera-roi';

type Props = {
    rois: CameraRoi[];
    selectedIndex: number | null;
    draftPoints?: RoiPoint[];
    editable?: boolean;
    stale?: boolean;
    className?: string;
    onSelect?: (index: number | null) => void;
    onDraftPointsChange?: (points: RoiPoint[]) => void;
    onFinishDraft?: () => void;
};

const ROI_PALETTE = [
    '#22d3ee',
    '#a78bfa',
    '#34d399',
    '#fbbf24',
    '#f472b6',
    '#60a5fa',
];

export function nextRoiColor(index: number): string {
    return ROI_PALETTE[index % ROI_PALETTE.length] ?? '#22d3ee';
}

function clamp01(n: number): number {
    return Math.min(1, Math.max(0, n));
}

function toNorm(
    event: React.PointerEvent<SVGSVGElement>,
    svg: SVGSVGElement,
): RoiPoint {
    const rect = svg.getBoundingClientRect();

    return {
        x: clamp01((event.clientX - rect.left) / Math.max(rect.width, 1)),
        y: clamp01((event.clientY - rect.top) / Math.max(rect.height, 1)),
    };
}

export function CameraRoiCanvas({
    rois,
    selectedIndex,
    draftPoints = [],
    editable = false,
    stale = false,
    className,
    onSelect,
    onDraftPointsChange,
    onFinishDraft,
}: Props) {
    const svgRef = useRef<SVGSVGElement | null>(null);
    const clipId = useId();
    const [hoverPoint, setHoverPoint] = useState<RoiPoint | null>(null);

    const onPointerDown = (event: React.PointerEvent<SVGSVGElement>): void => {
        if (!editable || svgRef.current === null || !onDraftPointsChange) {
            return;
        }

        if (event.button !== 0) {
            return;
        }

        event.preventDefault();

        if (event.detail >= 2) {
            if (draftPoints.length >= 3) {
                onFinishDraft?.();
            }

            return;
        }

        onSelect?.(null);
        onDraftPointsChange([
            ...draftPoints,
            toNorm(event, svgRef.current),
        ]);
    };

    const previewPoints =
        hoverPoint !== null && draftPoints.length > 0
            ? [...draftPoints, hoverPoint]
            : draftPoints;

    return (
        <svg
            ref={svgRef}
            className={cn(
                'absolute inset-0 z-[1] size-full touch-none',
                editable ? 'cursor-crosshair' : 'pointer-events-none',
                className,
            )}
            viewBox="0 0 100 100"
            preserveAspectRatio="none"
            onPointerDown={onPointerDown}
            onPointerMove={(event) => {
                if (
                    !editable ||
                    svgRef.current === null ||
                    draftPoints.length === 0
                ) {
                    setHoverPoint(null);

                    return;
                }

                setHoverPoint(toNorm(event, svgRef.current));
            }}
            onPointerLeave={() => setHoverPoint(null)}
            role="img"
            aria-label="Camera ROI overlay"
        >
            <defs>
                <clipPath id={clipId}>
                    <rect x="0" y="0" width="100" height="100" />
                </clipPath>
            </defs>
            <g clipPath={`url(#${clipId})`}>
                {rois.map((roi, index) => {
                    if (!roi.is_enabled || roi.polygon.length < 3) {
                        return null;
                    }

                    const selected = selectedIndex === index;
                    const stroke = roi.color || nextRoiColor(index);

                    return (
                        <g key={`${roi.reference || roi.name}-${index}`}>
                            <polygon
                                points={roi.polygon
                                    .map((p) => `${p.x * 100},${p.y * 100}`)
                                    .join(' ')}
                                fill={stroke}
                                fillOpacity={selected ? 0.3 : 0.14}
                                stroke={stroke}
                                strokeWidth={selected ? 0.7 : 0.35}
                                strokeDasharray={stale ? '1.5 1' : undefined}
                                vectorEffect="non-scaling-stroke"
                                className={
                                    editable
                                        ? 'pointer-events-auto cursor-pointer'
                                        : undefined
                                }
                                onPointerDown={(event) => {
                                    if (!editable) {
                                        return;
                                    }

                                    event.stopPropagation();
                                    onSelect?.(index);
                                }}
                            />
                            <text
                                x={roi.polygon[0].x * 100}
                                y={roi.polygon[0].y * 100 - 1.4}
                                fill="#fafafa"
                                fontSize="2.6"
                                fontWeight={600}
                                className="pointer-events-none"
                            >
                                {roi.name}
                            </text>
                        </g>
                    );
                })}
                {previewPoints.length > 0 && (
                    <polyline
                        points={previewPoints
                            .map((p) => `${p.x * 100},${p.y * 100}`)
                            .join(' ')}
                        fill={
                            previewPoints.length >= 3
                                ? 'rgba(245, 158, 11, 0.15)'
                                : 'none'
                        }
                        stroke="#f59e0b"
                        strokeWidth={0.5}
                        strokeDasharray="1.2 0.8"
                        vectorEffect="non-scaling-stroke"
                    />
                )}
                {draftPoints.map((p, i) => (
                    <circle
                        key={`draft-${i}`}
                        cx={p.x * 100}
                        cy={p.y * 100}
                        r={i === 0 ? 0.9 : 0.65}
                        fill={i === 0 ? '#fbbf24' : '#f59e0b'}
                        stroke="#0b0d10"
                        strokeWidth={0.15}
                    />
                ))}
            </g>
        </svg>
    );
}
