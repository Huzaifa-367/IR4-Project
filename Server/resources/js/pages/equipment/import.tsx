import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { QrLabelsBulkButton } from '@/components/ir4/qr-label-button';
import { Button } from '@/components/ui/button';
import type { EquipmentImportResult } from '@/types/equipment';

type Props = {
    latestImport: {
        id: number;
        original_filename: string;
        status: string;
        summary: EquipmentImportResult | null;
        created_at: string | null;
    } | null;
};

export default function EquipmentImport({ latestImport }: Props) {
    const printIds = [
        ...(latestImport?.summary?.createdIds ??
            latestImport?.summary?.created_ids ??
            []),
        ...(latestImport?.summary?.updated_ids ?? []),
    ];

    return (
        <>
            <Head title="Import equipment" />
            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Import equipment"
                        description="CSV commissioning import. New rows get a permanent QR token for bulk label printing."
                    />
                    <Button asChild variant="outline">
                        <Link href="/equipment">Back</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="secondary">
                        <a href="/equipment/import/template">
                            Download template
                        </a>
                    </Button>
                </div>

                <Form
                    action="/equipment/import"
                    method="post"
                    encType="multipart/form-data"
                    className="max-w-lg space-y-4 rounded-lg border border-border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <label
                                    htmlFor="file"
                                    className="text-sm font-medium"
                                >
                                    CSV file
                                </label>
                                <input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept=".csv,text/csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    required
                                />
                                {errors.file && (
                                    <p className="text-sm text-destructive">
                                        {errors.file}
                                    </p>
                                )}
                            </div>
                            <Button type="submit" disabled={processing}>
                                Upload &amp; import
                            </Button>
                        </>
                    )}
                </Form>

                {latestImport && (
                    <div className="max-w-2xl space-y-3 rounded-lg border border-border p-4 text-sm">
                        <h2 className="font-medium">Latest import</h2>
                        <p>
                            {latestImport.original_filename} ·{' '}
                            {latestImport.status}
                            {latestImport.created_at
                                ? ` · ${new Date(latestImport.created_at).toLocaleString()}`
                                : ''}
                        </p>
                        {latestImport.summary && (
                            <>
                                <p>
                                    Created {latestImport.summary.created},
                                    updated {latestImport.summary.updated},
                                    skipped {latestImport.summary.skipped}
                                </p>
                                {latestImport.summary.errors.length > 0 && (
                                    <ul className="list-disc space-y-1 pl-5 text-destructive">
                                        {latestImport.summary.errors.map(
                                            (error) => (
                                                <li
                                                    key={`e-${error.row}-${error.message}`}
                                                >
                                                    Row {error.row}:{' '}
                                                    {error.message}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                                {printIds.length > 0 && (
                                    <div className="pt-2">
                                        <QrLabelsBulkButton
                                            ids={printIds}
                                            label="Print imported labels"
                                        />
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
