import { Toaster } from '@/components/ui/sonner';
import { useIdleLogout } from '@/hooks/use-idle-logout';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const idleWarning = useIdleLogout();

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
            {idleWarning}
            <Toaster />
        </AppLayoutTemplate>
    );
}
