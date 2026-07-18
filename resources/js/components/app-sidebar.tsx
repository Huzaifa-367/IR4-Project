import { Link } from '@inertiajs/react';
import {
    Bell,
    ClipboardList,
    CloudSun,
    FileBarChart,
    FileWarning,
    LayoutGrid,
    MapPinned,
    Package,
    Radio,
    ScrollText,
    ShieldAlert,
    Users,
    Video,
    Wind,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { useAlertStore } from '@/components/ir4/alert-provider';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { can } = usePermissions();
    const { bellCount } = useAlertStore();

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(can('view-dashboard')
            ? [
                  {
                      title: 'Environment',
                      href: '/environment',
                      icon: CloudSun,
                  } satisfies NavItem,
              ]
            : []),
        {
            title: bellCount > 0 ? `Alerts (${bellCount})` : 'Alerts',
            href: '/alerts',
            icon: Bell,
        },
        ...(can('view-tracking')
            ? [
                  {
                      title: 'Tracking',
                      href: '/tracking',
                      icon: Radio,
                  } satisfies NavItem,
                  {
                      title: 'Workers',
                      href: '/tracking/workers',
                      icon: Users,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-live-cameras')
            ? [
                  {
                      title: 'Live wall',
                      href: '/live',
                      icon: Video,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-ppe')
            ? [
                  {
                      title: 'PPE',
                      href: '/ppe/violations',
                      icon: ShieldAlert,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-gas')
            ? [
                  {
                      title: 'Gas & CO₂',
                      href: '/gas',
                      icon: Wind,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-equipment')
            ? [
                  {
                      title: 'Equipment Items',
                      href: '/equipment',
                      icon: Package,
                  } satisfies NavItem,
                  {
                      title: 'Checkouts',
                      href: '/equipment/checkouts',
                      icon: ClipboardList,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-incidents')
            ? [
                  {
                      title: 'Incidents',
                      href: '/incidents',
                      icon: FileWarning,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-lsr')
            ? [
                  {
                      title: 'LSR',
                      href: '/lsr-violations',
                      icon: ShieldAlert,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-reports')
            ? [
                  {
                      title: 'Reports',
                      href: '/reports',
                      icon: FileBarChart,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-zones')
            ? [
                  {
                      title: 'Zones',
                      href: '/settings/zones',
                      icon: MapPinned,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-audit-log')
            ? [
                  {
                      title: 'Audit Log',
                      href: '/settings/audit-log',
                      icon: ScrollText,
                  } satisfies NavItem,
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
