import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { WorkerImportSummary } from '@/types/worker';

type Props = {
    latestImport: {
        id: number;
        original_filename: string;
        status: string;
        summary: WorkerImportSummary | null;
        created_at: string | null;
    } | null;
};

export default function WorkersImport({ latestImport }: Props) {
    return (
        <>
            <Head title="Import workers" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Import workers"
                        description="CSV roster import. Tags are assigned later — not during import."
                    />
                    <Button asChild variant="outline">
                        <Link href="/workforce/workers">Back</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="secondary">
                        <a href="/workforce/workers/import/template">
                            Download template
                        </a>
                    </Button>
                </div>

                <Form
                    action="/workforce/workers/import"
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
                                    accept=".csv,text/csv"
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
                                {latestImport.summary.flagged.length > 0 && (
                                    <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
                                        {latestImport.summary.flagged.map(
                                            (flag) => (
                                                <li
                                                    key={`f-${flag.row}-${flag.message}`}
                                                >
                                                    Row {flag.row}:{' '}
                                                    {flag.message}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
