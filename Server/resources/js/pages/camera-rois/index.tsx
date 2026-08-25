import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import Heading from '@/components/heading';
import { StatusPill } from '@/components/ir4/status-pill';
import { Button } from '@/components/ui/button';
import {
    cameraRoiStatusLabel,
    cameraRoiStatusTone,
} from '@/lib/camera-roi-status';
import hardware from '@/routes/hardware';
import type { CameraRoiIndexRow } from '@/types/camera-roi';
import { CameraRoiStaleReasonLabels } from '@/types/enums';
import type { CameraRoiStaleReason } from '@/types/enums';

type Props = {
    cameras: CameraRoiIndexRow[];
    canManage: boolean;
};

export default function CameraRoisIndex({ cameras, canManage }: Props) {
    return (
        <>
            <Head title="Camera ROIs" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Camera ROIs"
                    description="Detection polygons on live camera feeds for edge AI."
                />

                <div className="overflow-hidden rounded-[var(--radius)] border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-surface-2 text-left text-xs tracking-wide text-text-faint uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Camera
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">ROIs</th>
                                <th className="px-4 py-3 font-medium" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {cameras.map((camera) => (
                                <tr key={camera.id} className="bg-surface">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-text">
                                            {camera.name}
                                        </div>
                                        <div className="text-xs text-text-faint">
                                            {camera.reference}
                                            {camera.location_label
                                                ? ` · ${camera.location_label}`
                                                : ''}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusPill
                                            label={cameraRoiStatusLabel(
                                                camera.roi_status,
                                            )}
                                            tone={cameraRoiStatusTone(
                                                camera.roi_status,
                                            )}
                                        />
                                        {camera.stale_reason ? (
                                            <p className="mt-1 text-xs text-text-faint">
                                                {
                                                    CameraRoiStaleReasonLabels[
                                                        camera.stale_reason as CameraRoiStaleReason
                                                    ]
                                                }
                                            </p>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-text-dim tabular-nums">
                                        {camera.roi_count}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="secondary"
                                        >
                                            <Link
                                                href={hardware.cameraRois.edit.url(
                                                    camera.uuid,
                                                )}
                                            >
                                                <Pencil className="mr-1 size-3.5" />
                                                {canManage ? 'Edit' : 'View'}
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {cameras.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-10 text-center text-text-faint"
                                    >
                                        No online cameras right now.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

CameraRoisIndex.layout = {
    breadcrumbs: [
        { title: 'Camera ROIs', href: hardware.cameraRois.index.url() },
    ],
};
