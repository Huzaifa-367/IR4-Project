import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background p-6 text-foreground">
                <div className="w-full max-w-lg space-y-8 text-center">
                    <div className="space-y-2">
                        <p className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                            IR4
                        </p>
                        <h1 className="text-4xl font-semibold tracking-tight">
                            Safety command centre
                        </h1>
                        <p className="text-muted-foreground">
                            On-premise platform for site tracking, gas, PPE, and
                            HSE workflows.
                        </p>
                    </div>

                    <nav className="flex items-center justify-center gap-3">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex h-10 items-center rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground"
                            >
                                Open dashboard
                            </Link>
                        ) : (
                            <Link
                                href={login()}
                                className="inline-flex h-10 items-center rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground"
                            >
                                Log in
                            </Link>
                        )}
                    </nav>
                </div>
            </div>
        </>
    );
}
