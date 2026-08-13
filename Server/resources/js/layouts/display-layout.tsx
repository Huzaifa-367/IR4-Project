import { Head } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { useIdleLogout } from '@/hooks/use-idle-logout';

/**
 * Chrome-less shell for `/live?display=1` (camera wall only).
 * Same session and idle timeout as the rest of the operator UI (DOC-02).
 */
export default function DisplayLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const idleWarning = useIdleLogout();

    return (
        <div className="dark min-h-screen bg-background text-foreground">
            <Head title="Live wall" />
            <main className="min-h-screen p-6 md:p-8">{children}</main>
            {idleWarning}
            <Toaster />
        </div>
    );
}
