import { Head } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { useSharedSettings } from '@/hooks/use-auth';
import { useIdleLogout } from '@/hooks/use-idle-logout';

/**
 * Kiosk shell for /display (DOC-02 §5.3). Same session as the operator.
 */
export default function DisplayLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { display_keep_session_alive: keepSessionAlive } = useSharedSettings();
    const idleWarning = useIdleLogout({ keepAlive: keepSessionAlive });

    return (
        <div className="dark min-h-screen bg-background text-foreground">
            <Head title="Display" />
            <main className="min-h-screen p-6 md:p-8">{children}</main>
            {idleWarning}
            <Toaster />
        </div>
    );
}
