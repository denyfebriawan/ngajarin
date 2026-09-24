import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FolderGit2,
    GraduationCap,
    House,
    LayoutGrid,
    Plus,
    Settings,
} from 'lucide-react';
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
import { index as tenantSubjects } from '@/routes/tenant/subjects';
import { create as createTenant } from '@/routes/tenants';
import type { NavItem } from '@/types';

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
                  title: 'Subjects',
                  href: tenantSubjects(currentTenant.slug),
                  icon: GraduationCap,
              },
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

    const workspaceItems: NavItem[] = [
        ...tenants.map((tenant) => ({
            title: tenant.name,
            href: tenantDashboard(tenant.slug),
            icon: Building2,
        })),
        { title: 'New workspace', href: createTenant(), icon: Plus },
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
                {currentTenant && (
                    <NavMain
                        label={currentTenant.name}
                        items={currentTenantItems}
                    />
                )}
                <NavMain label="Workspaces" items={workspaceItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
