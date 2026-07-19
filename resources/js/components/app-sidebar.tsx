import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRightLeft,
    Bell,
    Boxes,
    Camera,
    Car,
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
    Siren,
    SlidersHorizontal,
    Smartphone,
    Tag,
    TrendingUp,
    UserCog,
    Users,
    Video,
    Wind,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { useAlertStore } from '@/components/ir4/alert-provider';
import { SystemStatusPanel } from '@/components/ir4/system-status-panel';
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

    const overview: NavItem[] = [
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
                      title: 'Live View',
                      href: '/live',
                      icon: Video,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const trackingChildren: NavItem[] = [
        ...(can('view-tracking')
            ? [
                  {
                      title: 'Overview',
                      href: '/tracking',
                      icon: Radio,
                  } satisfies NavItem,
                  {
                      title: 'Workers',
                      href: '/tracking/workers',
                      icon: Users,
                  } satisfies NavItem,
                  {
                      title: 'Tags',
                      href: '/tracking/tags',
                      icon: Tag,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-portable-devices')
            ? [
                  {
                      title: 'Portable Devices',
                      href: '/tracking/portable-devices',
                      icon: Smartphone,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-entry-exit')
            ? [
                  {
                      title: 'Entry / Exit',
                      href: '/tracking/entry-exit',
                      icon: ArrowRightLeft,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-zones')
            ? [
                  {
                      title: 'Zones & Map',
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
        ...(can('trigger-evacuation') || can('manage-evacuation')
            ? [
                  {
                      title: 'Evacuation',
                      href: '/tracking/evacuation',
                      icon: Siren,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const tracking: NavItem[] =
        trackingChildren.length > 0
            ? [
                  {
                      title: 'Tracking',
                      href: '/tracking',
                      icon: Radio,
                      items: trackingChildren,
                  },
              ]
            : [];

    const safety: NavItem[] = [
        ...(can('view-ppe')
            ? [
                  {
                      title: 'PPE',
                      href: '/ppe/violations',
                      icon: Shield,
                      items: [
                          {
                              title: 'Violations',
                              href: '/ppe/violations',
                              icon: Shield,
                          },
                          {
                              title: 'Trends',
                              href: '/ppe/trends',
                              icon: TrendingUp,
                          },
                      ],
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-gas')
            ? [
                  {
                      title: 'Gas & CO₂',
                      href: '/gas',
                      icon: Wind,
                      items: [
                          { title: 'Dashboard', href: '/gas', icon: Wind },
                          {
                              title: 'Alarms',
                              href: '/gas/alarms',
                              icon: AlertTriangle,
                          },
                          {
                              title: 'Trends',
                              href: '/gas/trends',
                              icon: TrendingUp,
                          },
                          {
                              title: 'Thresholds',
                              href: '/gas/thresholds',
                              icon: SlidersHorizontal,
                          },
                      ],
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
        ...(can('view-equipment')
            ? [
                  {
                      title: 'Equipment',
                      href: '/equipment',
                      icon: Package,
                      items: [
                          {
                              title: 'Items',
                              href: '/equipment',
                              icon: Package,
                          },
                          {
                              title: 'Checkouts',
                              href: '/equipment/checkouts',
                              icon: ClipboardList,
                          },
                      ],
                  } satisfies NavItem,
              ]
            : []),
    ];

    const reportsChildren: NavItem[] = [
        ...(can('view-reports')
            ? [
                  {
                      title: 'Weekly Reports',
                      href: '/reports',
                      icon: FileBarChart,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('log-vehicle-violations')
            ? [
                  {
                      title: 'Vehicle Violations',
                      href: '/reports/vehicle-violations',
                      icon: Car,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-settings')
            ? [
                  {
                      title: 'Report Settings',
                      href: '/reports/settings',
                      icon: Settings2,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const operations: NavItem[] = [
        ...(reportsChildren.length > 0
            ? [
                  {
                      title: 'Reports',
                      href: '/reports',
                      icon: FileBarChart,
                      items: reportsChildren,
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
    ];

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
                <NavMain items={overview} label="Overview" />
                <NavMain items={tracking} label="Tracking" />
                <NavMain items={safety} label="Safety" />
                <NavMain items={operations} label="Operations" />
            </SidebarContent>

            <SidebarFooter className="gap-3 border-t border-sidebar-border p-3">
                <div className="px-1 group-data-[collapsible=icon]:hidden">
                    <SystemStatusPanel />
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
