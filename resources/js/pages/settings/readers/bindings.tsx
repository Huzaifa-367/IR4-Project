import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Props = {
    device: { id: number; uuid: string; name: string; reference: string };
    bindings: Array<{
        id: number;
        zone_name: string | null;
        zone_type: string | null;
        bound_from: string | null;
        bound_until: string | null;
        note: string | null;
    }>;
};

export default function ReaderBindingsHistory({ device, bindings }: Props) {
    return (
        <>
            <Head title={`${device.name} bindings`} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={device.name}
                        description={`Binding history · ${device.reference}`}
                    />
                    <Button asChild variant="outline">
                        <Link href="/settings/repositioning">Back</Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Zone</th>
                                <th className="px-3 py-2 font-medium">From</th>
                                <th className="px-3 py-2 font-medium">Until</th>
                                <th className="px-3 py-2 font-medium">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            {bindings.map((binding) => (
                                <tr
                                    key={binding.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-3 py-2">
                                        {binding.zone_name} ({binding.zone_type}
                                        )
                                    </td>
                                    <td className="px-3 py-2">
                                        {binding.bound_from}
                                    </td>
                                    <td className="px-3 py-2">
                                        {binding.bound_until ?? 'open'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {binding.note ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
