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
    FileCheck,
    FileWarning,
    HardHat,
    IdCard,
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

function firstHref(items: NavItem[], fallback: string): string {
    const href = items[0]?.href;

    return typeof href === 'string' ? href : fallback;
}

export function AppSidebar() {
    const { can } = usePermissions();
    const { bellCount } = useAlertStore();

    // Daily command-centre surfaces — highest traffic first.
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
        ...(can('view-live-cameras')
            ? [
                  {
                      title: 'Live View',
                      href: '/live',
                      icon: Video,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-dashboard')
            ? [
                  {
                      title: 'Environment',
                      href: '/environment',
                      icon: CloudSun,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const liveChildren: NavItem[] = [
        ...(can('view-tracking')
            ? [
                  {
                      title: 'Live Tracking',
                      href: '/tracking',
                      icon: Radio,
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
        ...(can('trigger-evacuation') || can('manage-evacuation')
            ? [
                  {
                      title: 'Evacuation',
                      href: '/tracking/evacuation',
                      icon: Siren,
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
    ];

    const live: NavItem[] =
        liveChildren.length > 0
            ? [
                  {
                      title: 'Site',
                      href: firstHref(liveChildren, '/tracking'),
                      icon: MapPinned,
                      items: liveChildren,
                  },
              ]
            : [];

    // Safety screens operators open during a shift.
    const safety: NavItem[] = [
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
                      ],
                  } satisfies NavItem,
              ]
            : []),
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

    // People & permits — frequent, but after live safety.
    const workforceChildren: NavItem[] = [
        ...(can('view-tracking')
            ? [
                  {
                      title: 'Workers',
                      href: '/workforce/workers',
                      icon: Users,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-permits')
            ? [
                  {
                      title: 'Permits',
                      href: '/workforce/permits',
                      icon: FileCheck,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-portable-devices')
            ? [
                  {
                      title: 'Portable Devices',
                      href: '/workforce/portable-devices',
                      icon: Smartphone,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-permit-catalogue')
            ? [
                  {
                      title: 'Permit types',
                      href: '/workforce/permit-types',
                      icon: IdCard,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const workforce: NavItem[] =
        workforceChildren.length > 0
            ? [
                  {
                      title: 'Workforce',
                      href: firstHref(
                          workforceChildren,
                          '/workforce/workers',
                      ),
                      icon: Users,
                      items: workforceChildren,
                  },
              ]
            : [];

    // Admin / config — least visited; keep at the bottom.
    const hardwareChildren: NavItem[] = [
        ...(can('manage-devices')
            ? [
                  {
                      title: 'Assets',
                      href: '/hardware/assets',
                      icon: Boxes,
                  } satisfies NavItem,
                  {
                      title: 'Devices',
                      href: '/hardware/devices',
                      icon: Cpu,
                  } satisfies NavItem,
                  {
                      title: 'Cameras',
                      href: '/hardware/cameras',
                      icon: Camera,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-tracking') || can('manage-tags')
            ? [
                  {
                      title: 'Tags',
                      href: '/hardware/tags',
                      icon: Tag,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const accessChildren: NavItem[] = [
        ...(can('manage-users')
            ? [
                  {
                      title: 'Users',
                      href: '/access/users',
                      icon: UserCog,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-roles')
            ? [
                  {
                      title: 'User roles',
                      href: '/access/roles',
                      icon: Shield,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('manage-permit-catalogue')
            ? [
                  {
                      title: 'Crew roles',
                      href: '/access/crew-roles',
                      icon: HardHat,
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

    const settingsChildren: NavItem[] = [
        ...(can('manage-settings') || can('configure-alerts')
            ? [
                  {
                      title: 'General',
                      href: '/settings/general',
                      icon: Settings2,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-gas') || can('manage-gas-thresholds')
            ? [
                  {
                      title: 'Gas thresholds',
                      href: '/settings/gas-thresholds',
                      icon: SlidersHorizontal,
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

    const admin: NavItem[] = [
        ...(hardwareChildren.length > 0
            ? [
                  {
                      title: 'Hardware',
                      href: firstHref(hardwareChildren, '/hardware/assets'),
                      icon: Cpu,
                      items: hardwareChildren,
                  } satisfies NavItem,
              ]
            : []),
        ...(accessChildren.length > 0
            ? [
                  {
                      title: 'Access',
                      href: firstHref(accessChildren, '/access/users'),
                      icon: UserCog,
                      items: accessChildren,
                  } satisfies NavItem,
              ]
            : []),
        ...(reportsChildren.length > 0
            ? [
                  {
                      title: 'Reports',
                      href: firstHref(reportsChildren, '/reports'),
                      icon: FileBarChart,
                      items: reportsChildren,
                  } satisfies NavItem,
              ]
            : []),
        ...(settingsChildren.length > 0
            ? [
                  {
                      title: 'Settings',
                      href: firstHref(
                          settingsChildren,
                          '/settings/general',
                      ),
                      icon: Settings2,
                      items: settingsChildren,
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
                <NavMain items={live} label="Live" />
                <NavMain items={safety} label="Safety" />
                <NavMain items={workforce} label="Workforce" />
                <NavMain items={admin} label="Admin" />
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
