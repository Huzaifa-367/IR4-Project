import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import tracking from '@/routes/tracking';
import type { WorkerImportSummary } from '@/types/worker';

type ImportResult = Pick<
    WorkerImportSummary,
    'created' | 'updated' | 'skipped' | 'errors' | 'flagged'
> & {
    filename: string;
    errors_truncated?: boolean;
    flagged_truncated?: boolean;
};

type Props = {
    importResult: ImportResult | null;
};

export default function WorkersImport({ importResult }: Props) {
    return (
        <>
            <Head title="Import workers" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Import workers"
                        description="Upload an Aramco Project Manpower List (.xlsx/.csv). The template uses those columns. The file is not kept on the server; row errors show once then clear."
                    />
                    <Button asChild variant="outline">
                        <Link href={tracking.workers.index()}>Back</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="secondary">
                        <a href={tracking.workers.import.template.url()}>
                            Download Aramco template
                        </a>
                    </Button>
                </div>

                <Form
                    action={tracking.workers.import.store.url()}
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
                                    CSV or Excel file
                                </label>
                                <input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept=".csv,text/csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    required
                                    disabled={processing}
                                />
                                {errors.file && (
                                    <p className="text-sm text-destructive">
                                        {errors.file}
                                    </p>
                                )}
                            </div>

                            {processing && (
                                <div
                                    className="space-y-2"
                                    role="status"
                                    aria-live="polite"
                                    aria-busy="true"
                                >
                                    <p className="text-sm text-muted-foreground">
                                        Importing workers — please wait. The
                                        upload is not saved on the server.
                                    </p>
                                    <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={cn(
                                                'h-full w-1/3 rounded-full bg-primary',
                                                'animate-[ir4-import-indeterminate_1.1s_ease-in-out_infinite]',
                                            )}
                                        />
                                    </div>
                                </div>
                            )}

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Importing…' : 'Upload & import'}
                            </Button>
                        </>
                    )}
                </Form>

                <style>{`
                    @keyframes ir4-import-indeterminate {
                        0% { transform: translateX(-120%); }
                        100% { transform: translateX(320%); }
                    }
                `}</style>

                {importResult && (
                    <div className="max-w-2xl space-y-3 rounded-lg border border-border p-4 text-sm">
                        <h2 className="font-medium">Import result</h2>
                        <p className="text-muted-foreground">
                            {importResult.filename} — shown once; refresh clears
                            this panel.
                        </p>
                        <p>
                            Created {importResult.created}, updated{' '}
                            {importResult.updated}, skipped{' '}
                            {importResult.skipped}
                        </p>
                        {importResult.errors.length > 0 && (
                            <ul className="list-disc space-y-1 pl-5 text-destructive">
                                {importResult.errors.map((error) => (
                                    <li
                                        key={`e-${error.row}-${error.message}`}
                                    >
                                        Row {error.row}: {error.message}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {importResult.errors_truncated && (
                            <p className="text-muted-foreground">
                                Additional row errors were omitted from this
                                one-time view.
                            </p>
                        )}
                        {importResult.flagged.length > 0 && (
                            <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
                                {importResult.flagged.map((flag) => (
                                    <li
                                        key={`f-${flag.row}-${flag.message}`}
                                    >
                                        Row {flag.row}: {flag.message}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
