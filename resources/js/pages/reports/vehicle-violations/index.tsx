import { Form, Head, Link, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/ir4/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { VehicleViolation } from '@/types/report';

type Props = {
    violations: {
        data: VehicleViolation[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: { search: string };
    violationTypes: string[];
    cameras: Array<{ id: number; name: string; reference: string }>;
    canCreate: boolean;
};

export default function VehicleViolationsIndex({
    violations,
    filters,
    violationTypes,
    cameras,
    canCreate,
}: Props) {
    const form = useForm({
        observed_at: '',
        vehicle_description: '',
        violation_type: violationTypes[0] ?? 'speeding',
        description: '',
        action_taken: '',
        camera_id: '',
    });

    return (
        <>
            <Head title="Vehicle violations" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Vehicle violations"
                        description="Manual item vii for the weekly report"
                    />
                    <Button variant="outline" asChild>
                        <Link href="/reports">Back to reports</Link>
                    </Button>
                </div>

                {canCreate && (
                    <form
                        className="grid gap-3 rounded-lg border p-4 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/reports/vehicle-violations', {
                                onSuccess: () => form.reset(),
                            });
                        }}
                    >
                        <div>
                            <Label htmlFor="observed_at">Observed at</Label>
                            <Input
                                id="observed_at"
                                type="datetime-local"
                                value={form.data.observed_at}
                                onChange={(event) =>
                                    form.setData(
                                        'observed_at',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div>
                            <Label htmlFor="vehicle_description">Vehicle</Label>
                            <Input
                                id="vehicle_description"
                                value={form.data.vehicle_description}
                                onChange={(event) =>
                                    form.setData(
                                        'vehicle_description',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div>
                            <Label htmlFor="violation_type">Type</Label>
                            <select
                                id="violation_type"
                                className="w-full rounded-md border px-3 py-2 text-sm"
                                value={form.data.violation_type}
                                onChange={(event) =>
                                    form.setData(
                                        'violation_type',
                                        event.target.value,
                                    )
                                }
                            >
                                {violationTypes.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label htmlFor="camera_id">Camera (optional)</Label>
                            <select
                                id="camera_id"
                                className="w-full rounded-md border px-3 py-2 text-sm"
                                value={form.data.camera_id}
                                onChange={(event) =>
                                    form.setData(
                                        'camera_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">None</option>
                                {cameras.map((camera) => (
                                    <option key={camera.id} value={camera.id}>
                                        {camera.name || camera.reference}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="action_taken">
                                Action taken (required)
                            </Label>
                            <Input
                                id="action_taken"
                                value={form.data.action_taken}
                                onChange={(event) =>
                                    form.setData(
                                        'action_taken',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            {form.errors.action_taken && (
                                <p className="mt-1 text-sm text-destructive">
                                    {form.errors.action_taken}
                                </p>
                            )}
                        </div>
                        <div>
                            <Button type="submit" disabled={form.processing}>
                                Log violation
                            </Button>
                        </div>
                    </form>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Observed</th>
                                <th className="p-3">Vehicle</th>
                                <th className="p-3">Type</th>
                                <th className="p-3">Action taken</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {violations.data.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="p-3">{row.observed_at}</td>
                                    <td className="p-3">
                                        {row.vehicle_description}
                                    </td>
                                    <td className="p-3">{row.violation_type}</td>
                                    <td className="p-3">{row.action_taken}</td>
                                    <td className="p-3 text-right">
                                        <Form
                                            method="delete"
                                            action={`/reports/vehicle-violations/${row.id}`}
                                        >
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                size="sm"
                                            >
                                                Remove
                                            </Button>
                                        </Form>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <Pagination
                        meta={violations.meta}
                        pageUrl="/reports/vehicle-violations"
                        params={filters}
                    />
                </div>
            </div>
        </>
    );
}

VehicleViolationsIndex.layout = {
    breadcrumbs: [
        { title: 'Reports', href: '/reports' },
        { title: 'Vehicle violations', href: '/reports/vehicle-violations' },
    ],
};
