import { Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { useAlertStore } from '@/components/ir4/alert-provider';
import { LiveStatusPill } from '@/components/ir4/live-status-pill';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { bellCount, status } = useAlertStore();

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-border bg-bg/80 px-4 backdrop-blur md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <LiveStatusPill status={status} />
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="relative text-text-dim"
                >
                    <Link href="/alerts" aria-label="Alerts">
                        <Bell className="size-4" />
                        {bellCount > 0 ? (
                            <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-pill bg-[color:var(--crit)] px-1 text-[10px] font-semibold text-white">
                                {bellCount > 99 ? '99+' : bellCount}
                            </span>
                        ) : null}
                    </Link>
                </Button>
                <AppearanceTabs className="hidden lg:flex" />
            </div>
        </header>
    );
}
