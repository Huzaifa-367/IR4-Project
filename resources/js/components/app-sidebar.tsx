import { Link } from '@inertiajs/react';
import {
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
        ...(can('view-gas')
            ? [
                  {
                      title: 'Gas',
                      href: '/gas',
                      icon: Wind,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-ppe')
            ? [
                  {
                      title: 'PPE Trends',
                      href: '/ppe',
                      icon: TrendingUp,
                  } satisfies NavItem,
              ]
            : []),
    ];

    // Live operations only — zone setup lives under Settings.
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
        ...(can('create-evacuation') || can('update-evacuation')
            ? [
                  {
                      title: 'Evacuation',
                      href: '/tracking/evacuation',
                      icon: Siren,
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
    // Violation / event forms are grouped: PPE → LSR → Vehicle → Incidents.
    const safety: NavItem[] = [
        ...(can('view-gas')
            ? [
                  {
                      title: 'Gas Alarms',
                      href: '/gas/alarms',
                      icon: Wind,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-ppe')
            ? [
                  {
                      title: 'PPE Violations',
                      href: '/ppe/violations',
                      icon: Shield,
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
        ...(can('view-vehicle-violations') || can('create-vehicle-violations')
            ? [
                  {
                      title: 'Vehicle Violations',
                      href: '/reports/vehicle-violations',
                      icon: Car,
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
    ];

    // Equipment custody — above Workforce.
    const equipment: NavItem[] = can('view-equipment')
        ? [
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
          ]
        : [];

    // Workforce usage — day-to-day pages only.
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
                  {
                      title: 'Work orders',
                      href: '/workforce/work-orders',
                      icon: ClipboardList,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-portable-devices') || can('create-portable-devices') || can('update-portable-devices')
            ? [
                  {
                      title: 'Portable Devices',
                      href: '/workforce/portable-devices',
                      icon: Smartphone,
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

    // Catalogue / values to configure — bottom of nav.
    const catalogueChildren: NavItem[] = [
        ...(can('view-permit-catalogue') || can('create-permit-catalogue') || can('update-permit-catalogue') || can('delete-permit-catalogue')
            ? [
                  {
                      title: 'Permit types',
                      href: '/workforce/permit-types',
                      icon: IdCard,
                  } satisfies NavItem,
                  {
                      title: 'Crew roles',
                      href: '/workforce/crew-roles',
                      icon: HardHat,
                  } satisfies NavItem,
                  {
                      title: 'Document types',
                      href: '/workforce/worker-document-types',
                      icon: ScrollText,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const catalogue: NavItem[] =
        catalogueChildren.length > 0
            ? [
                  {
                      title: 'PTW catalogue',
                      href: firstHref(
                          catalogueChildren,
                          '/workforce/permit-types',
                      ),
                      icon: IdCard,
                      items: catalogueChildren,
                  },
              ]
            : [];

    const hardwareChildren: NavItem[] = [
        ...(can('view-devices') || can('create-devices') || can('update-devices') || can('delete-devices')
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
        ...(can('view-tracking') || can('create-tags') || can('update-tags')
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
        ...(can('view-users') || can('create-users') || can('update-users')
            ? [
                  {
                      title: 'Users',
                      href: '/access/users',
                      icon: UserCog,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-roles')
            ? [
                  {
                      title: 'User roles',
                      href: '/access/roles',
                      icon: Shield,
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
    ];

    const settingsChildren: NavItem[] = [
        ...(can('view-settings') || can('update-settings') || can('update-alert-settings') || can('view-gas-thresholds') || can('update-gas-thresholds')
            ? [
                  {
                      title: 'General',
                      href: '/settings/general',
                      icon: Settings2,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-settings') || can('update-settings')
            ? [
                  {
                      title: 'Report settings',
                      href: '/settings/reports',
                      icon: FileBarChart,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-zones') || can('create-zones') || can('update-zones') || can('delete-zones')
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
        ...(can('view-gas-thresholds') || can('update-gas-thresholds')
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
                <NavMain items={equipment} label="Equipment" />
                <NavMain items={workforce} label="Workforce" />
                <NavMain items={admin} label="Admin" />
                <NavMain items={catalogue} label="Catalogue" />
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
