import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Trash2, Undo2, X } from 'lucide-react';
import { useState } from 'react';
import {
    CameraRoiCanvas,
    nextRoiColor,
} from '@/components/ir4/camera-roi-canvas';
import { LiveCameraFeed } from '@/components/ir4/live-camera-feed';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePropSyncedState } from '@/hooks/use-prop-synced-state';
import {
    cameraRoiStatusLabel,
    cameraRoiStatusTone,
} from '@/lib/camera-roi-status';
import hardware from '@/routes/hardware';
import type { CameraRoi, CameraRoiSet, RoiPoint } from '@/types/camera-roi';
import { CameraRoiSetStatus, CameraRoiStaleReasonLabels } from '@/types/enums';
import type {
    CameraRoiSetStatus as RoiStatus,
    CameraRoiStaleReason,
} from '@/types/enums';

type Props = {
    camera: {
        id: number;
        uuid: string;
        name: string;
        reference: string;
        playback_url: string | null;
        location_label: string | null;
    };
    set: CameraRoiSet | null;
    canManage: boolean;
};

const EMPTY_ROIS: CameraRoi[] = [];

function isComplete(roi: CameraRoi): boolean {
    return roi.polygon.length >= 3;
}

function clampIndex(index: number | null, length: number): number | null {
    if (index === null) {
        return null;
    }

    if (length === 0) {
        return null;
    }

    return Math.min(index, length - 1);
}

export default function CameraRoisEdit({ camera, set, canManage }: Props) {
    const [rois, setRois] = usePropSyncedState(set?.rois ?? EMPTY_ROIS);
    const [selectedIndex, setSelectedIndex] = useState<number | null>(
        (set?.rois?.length ?? 0) > 0 ? 0 : null,
    );
    const [draftPoints, setDraftPoints] = useState<RoiPoint[]>([]);
    const [saving, setSaving] = useState(false);

    const status = (set?.status ?? null) as RoiStatus | null;
    const isStale = status === CameraRoiSetStatus.Stale;
    const isActive = status === CameraRoiSetStatus.Active;
    const isDrawing = draftPoints.length > 0;
    const canFinishDraft = draftPoints.length >= 3;
    const activeIndex = clampIndex(selectedIndex, rois.length);
    const selected = activeIndex !== null ? (rois[activeIndex] ?? null) : null;
    const canPublish =
        canManage &&
        !isDrawing &&
        rois.some((roi) => roi.is_enabled && isComplete(roi));

    function updateSelected(patch: Partial<CameraRoi>): void {
        if (activeIndex === null) {
            return;
        }

        setRois((current) =>
            current.map((roi, index) =>
                index === activeIndex ? { ...roi, ...patch } : roi,
            ),
        );
    }

    function removeSelected(): void {
        if (activeIndex === null) {
            return;
        }

        const nextLength = rois.length - 1;
        setRois((current) =>
            current.filter((_, index) => index !== activeIndex),
        );
        setSelectedIndex(clampIndex(activeIndex, nextLength));
    }

    function finishDraft(): void {
        if (!canManage || draftPoints.length < 3) {
            return;
        }

        const index = rois.length;
        setRois((current) => [
            ...current,
            {
                name: `Red Zone ${index + 1}`,
                reference: '',
                polygon: draftPoints,
                color: nextRoiColor(index),
                sort_order: index,
                is_enabled: true,
            },
        ]);
        setDraftPoints([]);
        setSelectedIndex(index);
    }

    function saveDraft(): void {
        const payload = rois.filter(isComplete);

        if (!canManage || isDrawing || payload.length === 0) {
            return;
        }

        setSaving(true);
        router.put(
            hardware.cameraRois.update.url(camera.uuid),
            { rois: payload },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    }

    function publish(): void {
        if (!canPublish) {
            return;
        }

        setSaving(true);
        router.post(
            hardware.cameraRois.publish.url(camera.uuid),
            { rois: rois.filter(isComplete) },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    }

    function markStale(): void {
        if (!canManage || !isActive || isDrawing) {
            return;
        }

        setSaving(true);
        router.post(
            hardware.cameraRois.markStale.url(camera.uuid),
            {},
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    }

    return (
        <>
            <Head title={`Red Zones · ${camera.name}`} />
            <div className="flex h-[calc(100dvh-3.5rem)] flex-col gap-3 p-3 md:p-4">
                <header className="flex shrink-0 flex-wrap items-center gap-2">
                    <Button asChild size="sm" variant="ghost" className="-ml-1">
                        <Link href={hardware.cameraRois.index.url()}>
                            <ArrowLeft className="size-4" />
                            <span className="sr-only">Back</span>
                        </Link>
                    </Button>
                    <h1 className="min-w-0 truncate font-display text-base font-semibold text-text md:text-lg">
                        {camera.name}
                    </h1>
                    <StatusPill
                        label={cameraRoiStatusLabel(status)}
                        tone={cameraRoiStatusTone(status)}
                    />
                    {canManage && (
                        <div className="ml-auto flex gap-2">
                            {isActive && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    disabled={saving || isDrawing}
                                    onClick={markStale}
                                >
                                    Mark stale
                                </Button>
                            )}
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                disabled={
                                    saving || isDrawing || rois.length === 0
                                }
                                onClick={saveDraft}
                            >
                                Save
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                disabled={saving || !canPublish}
                                onClick={publish}
                            >
                                <Check className="mr-1 size-3.5" />
                                Publish
                            </Button>
                        </div>
                    )}
                </header>

                {isStale && (
                    <p
                        role="alert"
                        className="shrink-0 rounded-[var(--radius)] border border-[color:var(--warn)]/40 bg-[color:var(--warn)]/10 px-3 py-2 text-xs text-text"
                    >
                        {set?.stale_reason
                            ? CameraRoiStaleReasonLabels[
                                  set.stale_reason as CameraRoiStaleReason
                              ]
                            : 'View changed'}
                        {
                            ' — publish again before edge AI uses these red zones.'
                        }
                    </p>
                )}

                <div className="grid min-h-0 flex-1 gap-3 lg:grid-cols-[minmax(0,1fr)_16rem]">
                    <div className="relative min-h-[50vh] overflow-hidden rounded-[var(--radius)] border border-border bg-black lg:min-h-0">
                        {camera.playback_url ? (
                            <LiveCameraFeed
                                playbackUrl={camera.playback_url}
                                title={`${camera.name} red zone editor`}
                                canControlPtz={false}
                                fillFrame
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center text-sm text-text-faint">
                                No stream
                            </div>
                        )}
                        <CameraRoiCanvas
                            rois={rois}
                            selectedIndex={activeIndex}
                            draftPoints={draftPoints}
                            editable={canManage}
                            stale={isStale}
                            onSelect={(index) => {
                                if (isDrawing && index !== null) {
                                    return;
                                }

                                setSelectedIndex(index);
                            }}
                            onDraftPointsChange={
                                canManage ? setDraftPoints : undefined
                            }
                            onFinishDraft={canManage ? finishDraft : undefined}
                        />

                        {canManage && isDrawing && (
                            <div className="absolute inset-x-0 bottom-0 z-[2] flex flex-wrap items-center gap-2 bg-gradient-to-t from-black/80 to-transparent p-3 pt-8">
                                <span className="text-xs text-white/80">
                                    {draftPoints.length} pt
                                    {draftPoints.length === 1 ? '' : 's'}
                                    {!canFinishDraft ? ' · need 3+' : ''}
                                </span>
                                <div className="ml-auto flex gap-1.5">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        disabled={draftPoints.length === 0}
                                        onClick={() =>
                                            setDraftPoints((c) =>
                                                c.slice(0, -1),
                                            )
                                        }
                                    >
                                        <Undo2 className="size-3.5" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        onClick={() => setDraftPoints([])}
                                    >
                                        <X className="size-3.5" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={!canFinishDraft}
                                        onClick={finishDraft}
                                    >
                                        Finish
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>

                    <aside className="flex min-h-0 flex-col gap-2 rounded-[var(--radius)] border border-border bg-surface p-3">
                        <p className="text-xs font-medium text-text-faint">
                            Red Zones ({rois.length})
                        </p>

                        <ul className="min-h-0 flex-1 space-y-0.5 overflow-y-auto">
                            {rois.map((roi, index) => (
                                <li key={`${roi.reference}-${index}`}>
                                    <button
                                        type="button"
                                        disabled={isDrawing}
                                        className={`flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm ${
                                            activeIndex === index
                                                ? 'bg-surface-2 text-text'
                                                : 'text-text-dim hover:bg-surface-2/60'
                                        } ${isDrawing ? 'opacity-40' : ''}`}
                                        onClick={() => setSelectedIndex(index)}
                                    >
                                        <span
                                            className="size-2 shrink-0 rounded-full"
                                            style={{ background: roi.color }}
                                        />
                                        <span className="min-w-0 flex-1 truncate">
                                            {roi.name}
                                        </span>
                                    </button>
                                </li>
                            ))}
                            {rois.length === 0 && (
                                <li className="px-1 py-6 text-center text-xs text-text-faint">
                                    Click the feed to draw
                                </li>
                            )}
                        </ul>

                        {selected && canManage && !isDrawing && (
                            <div className="shrink-0 space-y-2 border-t border-border pt-2">
                                <Input
                                    aria-label="Red Zone name"
                                    value={selected.name}
                                    onChange={(event) =>
                                        updateSelected({
                                            name: event.target.value,
                                        })
                                    }
                                />
                                <div className="flex items-center gap-2">
                                    <Input
                                        aria-label="Red Zone color"
                                        type="color"
                                        className="h-8 w-10 shrink-0 cursor-pointer p-0.5"
                                        value={selected.color}
                                        onChange={(event) =>
                                            updateSelected({
                                                color: event.target.value,
                                            })
                                        }
                                    />
                                    <label className="flex flex-1 items-center gap-1.5 text-xs text-text-dim">
                                        <input
                                            type="checkbox"
                                            className="size-3.5"
                                            checked={selected.is_enabled}
                                            onChange={(event) =>
                                                updateSelected({
                                                    is_enabled:
                                                        event.target.checked,
                                                })
                                            }
                                        />
                                        On
                                    </label>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="text-[color:var(--crit)]"
                                        onClick={removeSelected}
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

CameraRoisEdit.layout = {
    breadcrumbs: [
        { title: 'Red Zones', href: hardware.cameraRois.index.url() },
        { title: 'Edit' },
    ],
};
