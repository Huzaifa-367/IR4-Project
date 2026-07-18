import { Link } from '@inertiajs/react';
import {
    Bell,
    Boxes,
    Camera,
    ClipboardList,
    CloudSun,
    Cpu,
    FileBarChart,
    FileWarning,
    LayoutGrid,
    MapPinned,
    Move,
    Package,
    Radio,
    ScrollText,
    Settings2,
    Shield,
    ShieldAlert,
    UserCog,
    Users,
    Video,
    Wind,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { useAlertStore } from '@/components/ir4/alert-provider';
import { StatusPill } from '@/components/ir4/status-pill';
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

    const general: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: bellCount > 0 ? `Alerts (${bellCount})` : 'Alerts',
            href: '/alerts',
            icon: Bell,
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
        ...(can('view-live-cameras')
            ? [
                  {
                      title: 'Live wall',
                      href: '/live',
                      icon: Video,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const tracking: NavItem[] = [
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
        ...(can('manage-zones')
            ? [
                  {
                      title: 'Zones',
                      href: '/settings/zones',
                      icon: MapPinned,
                  } satisfies NavItem,
                  {
                      title: 'Repositioning',
                      href: '/settings/repositioning',
                      icon: Move,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const gas: NavItem[] = [
        ...(can('view-gas')
            ? [
                  {
                      title: 'Gas & CO₂',
                      href: '/gas',
                      icon: Wind,
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
    ];

    const hse: NavItem[] = [
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
        ...(can('view-reports')
            ? [
                  {
                      title: 'Reports',
                      href: '/reports',
                      icon: FileBarChart,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const settings: NavItem[] = [
        ...(can('manage-settings') ||
        can('configure-alerts') ||
        can('manage-gas-thresholds')
            ? [
                  {
                      title: 'General',
                      href: '/settings/general',
                      icon: Settings2,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-roles')
            ? [
                  {
                      title: 'Roles',
                      href: '/settings/roles',
                      icon: Shield,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-users')
            ? [
                  {
                      title: 'Users',
                      href: '/settings/users',
                      icon: UserCog,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-devices')
            ? [
                  {
                      title: 'Assets',
                      href: '/settings/assets',
                      icon: Boxes,
                  } satisfies NavItem,
                  {
                      title: 'Devices',
                      href: '/settings/devices',
                      icon: Cpu,
                  } satisfies NavItem,
                  {
                      title: 'Cameras',
                      href: '/settings/cameras',
                      icon: Camera,
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

    const healthTone = bellCount > 5 ? 'crit' : bellCount > 0 ? 'warn' : 'ok';
    const healthLabel =
        bellCount > 5
            ? 'Elevated risk'
            : bellCount > 0
              ? 'Attention needed'
              : 'Posture nominal';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="border-b border-sidebar-border">
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

            <SidebarContent className="gap-1 py-2">
                <NavMain items={general} label="Overview" />
                <NavMain items={tracking} label="Tracking" />
                <NavMain items={gas} label="Gas & PPE" />
                <NavMain items={hse} label="HSE & Ops" />
                <NavMain items={settings} label="Settings" />
            </SidebarContent>

            <SidebarFooter className="gap-3 border-t border-sidebar-border p-3">
                <div className="hidden space-y-1.5 px-1 group-data-[collapsible=icon]:hidden">
                    <p className="eyebrow">System posture</p>
                    <StatusPill label={healthLabel} tone={healthTone} />
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
