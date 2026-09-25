import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    Building2,
    CalendarClock,
    CalendarPlus,
    FolderGit2,
    BookOpenCheck,
    GraduationCap,
    House,
    LayoutGrid,
    Plane,
    Plus,
    Settings,
} from 'lucide-react';
import BookingController from '@/actions/App/Http/Controllers/Tenant/BookingController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import { dashboard } from '@/routes';
import { dashboard as tenantDashboard } from '@/routes/tenant';
import { edit as tenantSettings } from '@/routes/tenant/settings';
import { edit as tenantAvailability } from '@/routes/tenant/availability';
import { index as tenantLessons } from '@/routes/tenant/lessons';
import { index as tenantSubjects } from '@/routes/tenant/subjects';
import { index as tenantTimeOff } from '@/routes/tenant/time-off';
import { teaches } from '@/lib/roles';
import { create as createTenant } from '@/routes/tenants';
import type { NavItem, TenantSummary } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { tenants, currentTenant } = usePage().props;

    // Pages of the workspace being viewed; Settings only for users allowed to change it.
    const currentTenantItems: NavItem[] = currentTenant
        ? [
              {
                  title: 'Overview',
                  href: tenantDashboard(currentTenant.slug),
                  icon: House,
              },
              {
                  title: 'Lessons',
                  href: tenantLessons(currentTenant.slug),
                  icon: BookOpenCheck,
              },
              // Students' main action in a workspace.
              ...(!currentTenant.can.teach
                  ? [
                        {
                            title: 'Book a lesson',
                            href: BookingController.create(currentTenant.slug),
                            icon: CalendarPlus,
                        },
                    ]
                  : []),
              {
                  title: 'Subjects',
                  href: tenantSubjects(currentTenant.slug),
                  icon: GraduationCap,
              },
              ...(currentTenant.can.teach
                  ? [
                        {
                            title: 'My availability',
                            href: tenantAvailability(currentTenant.slug),
                            icon: CalendarClock,
                        },
                        {
                            title: 'Time off',
                            href: tenantTimeOff(currentTenant.slug),
                            icon: Plane,
                        },
                    ]
                  : []),
              ...(currentTenant.can.update
                  ? [
                        {
                            title: 'Settings',
                            href: tenantSettings(currentTenant.slug),
                            icon: Settings,
                        },
                    ]
                  : []),
          ]
        : [];

    // Workspaces split by the user's role: where they teach, and where they study.
    const toItem = (tenant: TenantSummary, icon: LucideIcon): NavItem => ({
        title: tenant.name,
        href: tenantDashboard(tenant.slug),
        icon,
    });

    const teachingItems: NavItem[] = [
        ...tenants
            .filter((tenant) => teaches(tenant.role))
            .map((tenant) => toItem(tenant, Building2)),
        { title: 'Create a workspace', href: createTenant(), icon: Plus },
    ];

    const studyingItems: NavItem[] = tenants
        .filter((tenant) => !teaches(tenant.role))
        .map((tenant) => toItem(tenant, GraduationCap));

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
                {currentTenant && (
                    <NavMain
                        label={currentTenant.name}
                        items={currentTenantItems}
                    />
                )}
                {studyingItems.length > 0 && (
                    <NavMain label="My tutors" items={studyingItems} />
                )}
                <NavMain label="Teaching" items={teachingItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
