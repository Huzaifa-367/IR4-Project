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
    Layers,
    List,
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
import alerts from '@/routes/alerts';
import environment from '@/routes/environment';
import equipment from '@/routes/equipment';
import gas from '@/routes/gas';
import hse from '@/routes/hse';
import live from '@/routes/live';
import permits from '@/routes/permits';
import ppe from '@/routes/ppe';
import reports from '@/routes/reports';
import settings from '@/routes/settings';
import tracking from '@/routes/tracking';
import workOrders from '@/routes/work-orders';
import type { NavItem } from '@/types';

function hrefUrl(href: NavItem['href']): string | null {
    if (typeof href === 'string') {
        return href;
    }

    if (href && typeof href === 'object' && 'url' in href) {
        return href.url;
    }

    return null;
}

function firstHref(items: NavItem[], fallback: string): string {
    return hrefUrl(items[0]?.href) ?? fallback;
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
            href: alerts.index(),
            icon: Bell,
        },
        ...(can('view-live-cameras')
            ? [
                  {
                      title: 'Live View',
                      href: live.index(),
                      icon: Video,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-dashboard')
            ? [
                  {
                      title: 'Environment',
                      href: environment.index(),
                      icon: CloudSun,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-gas')
            ? [
                  {
                      title: 'Gas',
                      href: gas.index(),
                      icon: Wind,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-ppe')
            ? [
                  {
                      title: 'PPE Trends',
                      href: ppe.index(),
                      icon: TrendingUp,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-permits')
            ? [
                  {
                      title: 'Permits',
                      href: permits.board(),
                      icon: FileCheck,
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
                      href: tracking.index(),
                      icon: Radio,
                  } satisfies NavItem,
                  {
                      title: 'Tag readings',
                      href: tracking.readings.index(),
                      icon: List,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-entry-exit')
            ? [
                  {
                      title: 'Entry / Exit',
                      href: tracking.entryExit.index(),
                      icon: ArrowRightLeft,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('create-evacuation') || can('update-evacuation')
            ? [
                  {
                      title: 'Evacuation',
                      href: tracking.evacuation.index(),
                      icon: Siren,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const liveNav: NavItem[] =
        liveChildren.length > 0
            ? [
                  {
                      title: 'Site',
                      href: firstHref(liveChildren, tracking.index.url()),
                      icon: Radio,
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
                      href: gas.alarms.index(),
                      icon: Wind,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-ppe')
            ? [
                  {
                      title: 'PPE Violations',
                      href: ppe.violations.index(),
                      icon: Shield,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-lsr')
            ? [
                  {
                      title: 'LSR',
                      href: hse.lsr.index(),
                      icon: ShieldAlert,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-vehicle-violations') || can('create-vehicle-violations')
            ? [
                  {
                      title: 'Vehicle Violations',
                      href: hse.vehicleViolations.index(),
                      icon: Car,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-incidents')
            ? [
                  {
                      title: 'Incidents',
                      href: hse.incidents.index(),
                      icon: FileWarning,
                  } satisfies NavItem,
              ]
            : []),
    ];

    // Equipment custody — above Workforce.
    const equipmentNav: NavItem[] = can('view-equipment')
        ? [
              {
                  title: 'Items',
                  href: equipment.index(),
                  icon: Package,
              },
              {
                  title: 'Checkouts',
                  href: equipment.checkouts.index(),
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
                      href: tracking.workers.index(),
                      icon: Users,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-permits')
            ? [
                  {
                      title: 'Permit register',
                      href: permits.index(),
                      icon: FileCheck,
                  } satisfies NavItem,
                  {
                      title: 'Work orders',
                      href: workOrders.index(),
                      icon: ClipboardList,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-portable-devices') ||
        can('create-portable-devices') ||
        can('update-portable-devices')
            ? [
                  {
                      title: 'Portable Devices',
                      href: tracking.portableDevices.index(),
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
                          tracking.workers.index.url(),
                      ),
                      icon: Users,
                      items: workforceChildren,
                  },
              ]
            : [];

    // Catalogue / values to configure — bottom of nav.
    const catalogueChildren: NavItem[] = [
        ...(can('view-permit-catalogue') ||
        can('create-permit-catalogue') ||
        can('update-permit-catalogue') ||
        can('delete-permit-catalogue')
            ? [
                  {
                      title: 'Permit types',
                      href: settings.permitTypes.index(),
                      icon: IdCard,
                  } satisfies NavItem,
                  {
                      title: 'Crew roles',
                      href: settings.crewRoles.index(),
                      icon: HardHat,
                  } satisfies NavItem,
                  {
                      title: 'Document types',
                      href: settings.workerDocumentTypes.index(),
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
                          settings.permitTypes.index.url(),
                      ),
                      icon: IdCard,
                      items: catalogueChildren,
                  },
              ]
            : [];

    const hardwareChildren: NavItem[] = [
        ...(can('view-devices') ||
        can('create-devices') ||
        can('update-devices') ||
        can('delete-devices')
            ? [
                  {
                      title: 'Assets',
                      href: settings.assets.index(),
                      icon: Boxes,
                  } satisfies NavItem,
                  {
                      title: 'Devices',
                      href: settings.devices.index(),
                      icon: Cpu,
                  } satisfies NavItem,
                  {
                      title: 'Cameras',
                      href: settings.cameras.index(),
                      icon: Camera,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-tracking') || can('create-tags') || can('update-tags')
            ? [
                  {
                      title: 'Tags',
                      href: tracking.tags.index(),
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
                      href: settings.users.index(),
                      icon: UserCog,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-roles')
            ? [
                  {
                      title: 'User roles',
                      href: settings.roles.index(),
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
                      href: reports.index(),
                      icon: FileBarChart,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const settingsChildren: NavItem[] = [
        ...(can('view-settings') ||
        can('update-settings') ||
        can('update-alert-settings') ||
        can('view-gas-thresholds') ||
        can('update-gas-thresholds')
            ? [
                  {
                      title: 'General',
                      href: settings.general.edit(),
                      icon: Settings2,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-settings') || can('update-settings')
            ? [
                  {
                      title: 'Report settings',
                      href: settings.reports.edit(),
                      icon: FileBarChart,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-zones') ||
        can('create-zones') ||
        can('update-zones') ||
        can('delete-zones')
            ? [
                  {
                      title: 'Zones',
                      href: settings.zones.index(),
                      icon: Layers,
                  } satisfies NavItem,
                  {
                      title: 'Repositioning',
                      href: settings.repositioning(),
                      icon: Move,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-gas-thresholds') || can('update-gas-thresholds')
            ? [
                  {
                      title: 'Gas thresholds',
                      href: gas.thresholds.index(),
                      icon: SlidersHorizontal,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('view-audit-log')
            ? [
                  {
                      title: 'Audit Log',
                      href: settings.auditLog.index(),
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
                      href: firstHref(
                          hardwareChildren,
                          settings.assets.index.url(),
                      ),
                      icon: Cpu,
                      items: hardwareChildren,
                  } satisfies NavItem,
              ]
            : []),
        ...(accessChildren.length > 0
            ? [
                  {
                      title: 'Access',
                      href: firstHref(
                          accessChildren,
                          settings.users.index.url(),
                      ),
                      icon: UserCog,
                      items: accessChildren,
                  } satisfies NavItem,
              ]
            : []),
        ...(reportsChildren.length > 0
            ? [
                  {
                      title: 'Reports',
                      href: firstHref(reportsChildren, reports.index.url()),
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
                          settings.general.edit.url(),
                      ),
                      icon: Settings2,
                      items: settingsChildren,
                  } satisfies NavItem,
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" className="p-1">
            <SidebarHeader className="border-b border-sidebar-border p-0">
                <SidebarMenu className="gap-0">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto justify-center gap-0 p-0 group-data-[collapsible=icon]:size-auto! group-data-[collapsible=icon]:p-0!"
                        >
                            <Link
                                href={dashboard()}
                                prefetch
                                className="block leading-none"
                            >
                                <AppLogo iconClassName="h-16 max-w-[240px] group-data-[collapsible=icon]:h-9 group-data-[collapsible=icon]:max-w-[2.25rem]" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-1 py-1">
                <NavMain items={overview} label="Overview" />
                <NavMain items={liveNav} label="Live" />
                <NavMain items={safety} label="Safety" />
                <NavMain items={equipmentNav} label="Equipment" />
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
