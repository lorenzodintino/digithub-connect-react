import { Link, router, usePage } from '@inertiajs/react';
import {
    BellRing,
    Building2,
    CalendarDays,
    ChartColumn,
    ChevronsUpDown,
    ClipboardList,
    Clock,
    ExternalLink,
    FileText,
    House,
    IdCard,
    Layers,
    LayoutGrid,
    ListOrdered,
    LogOut,
    type LucideIcon,
    Settings,
    Users,
    UsersRound,
} from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { __ } from '@/lib/i18n';
import { dashboard, logout } from '@/routes';
import * as admin from '@/routes/admin';
import * as app from '@/routes/app';
import { edit as editProfile } from '@/routes/profile';
import type { AuthUser } from '@/types';

interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    /** Also current on the pages below it, like a portal's detail under Portals. */
    prefix?: boolean;
}

interface NavGroup {
    heading: string;
    items: NavItem[];
}

/** The groups of the back-office, by role. */
function navigation(user: AuthUser): NavGroup[] {
    const groups: NavGroup[] = [{ heading: __('Overview'), items: [{ label: __('Dashboard'), href: dashboard.url(), icon: House }] }];

    if (user.role === 'admin') {
        groups.push({
            heading: __('Platform'),
            items: [
                { label: __('Portals'), href: admin.portals.url(), icon: Building2, prefix: true },
                { label: __('Accounts'), href: admin.users.url(), icon: Users },
                { label: __('Plans'), href: admin.plans.url(), icon: Layers },
                { label: __('Legal documents'), href: admin.legal.url(), icon: FileText },
                { label: __('Register'), href: admin.activity.url(), icon: ClipboardList },
                { label: __('Queue'), href: admin.queue.url(), icon: ListOrdered },
            ],
        });
    }

    if (user.role === 'manager') {
        groups.push({
            heading: __('Portal'),
            items: [
                { label: __('Bookings'), href: app.bookings.url(), icon: CalendarDays },
                { label: __('Services'), href: app.services.url(), icon: LayoutGrid },
                { label: __('Resources'), href: app.resources.url(), icon: Layers },
                { label: __('Customers'), href: app.customers.url(), icon: IdCard },
                { label: __('Waiting list'), href: app.waitingList.url(), icon: BellRing },
                { label: __('Staff'), href: app.staff.url(), icon: UsersRound },
                { label: __('Reports'), href: app.reports.url(), icon: ChartColumn },
                { label: __('Availability'), href: app.availability.url(), icon: Clock },
                { label: __('Register'), href: app.activity.url(), icon: ClipboardList },
                { label: __('Portal settings'), href: app.portal.url(), icon: Settings },
            ],
        });
    }

    if (user.role === 'default' && user.tenant !== null) {
        groups.push({
            heading: __('My work'),
            items: [
                { label: __('Bookings'), href: app.bookings.url(), icon: CalendarDays },
                { label: __('Reports'), href: app.reports.url(), icon: ChartColumn },
                { label: __('Availability'), href: app.availability.url(), icon: Clock },
            ],
        });
    }

    return groups;
}

function isCurrent(currentUrl: string, item: NavItem): boolean {
    const path = currentUrl.split('?')[0];

    return path === item.href || (item.prefix === true && path.startsWith(`${item.href}/`));
}

function UserAvatar({ user }: { user: AuthUser }) {
    return (
        <Avatar className="size-8 rounded-lg">
            {user.avatar && <AvatarImage src={user.avatar} alt={user.name} />}
            <AvatarFallback className="rounded-lg">{user.initials}</AvatarFallback>
        </Avatar>
    );
}

function UserMenu({ user }: { user: AuthUser }) {
    const { isMobile } = useSidebar();

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="lg" className="data-[state=open]:bg-sidebar-accent" data-test="sidebar-menu-button">
                            <UserAvatar user={user} />
                            <span className="truncate font-medium">{user.name}</span>
                            <ChevronsUpDown className="ms-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent className="min-w-56 rounded-lg" side={isMobile ? 'bottom' : 'top'} align="start">
                        <DropdownMenuLabel className="flex items-center gap-2 font-normal">
                            <UserAvatar user={user} />
                            <div className="grid flex-1 leading-tight">
                                <span className="truncate font-medium">{user.name}</span>
                                <span className="truncate text-xs text-muted-foreground">{user.email}</span>
                            </div>
                        </DropdownMenuLabel>

                        <DropdownMenuSeparator />

                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link href={editProfile()} className="w-full">
                                    <Settings />
                                    {__('Settings')}
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>

                        <DropdownMenuSeparator />

                        <DropdownMenuItem asChild>
                            <Link href={logout()} as="button" className="w-full" onClick={() => router.flushAll()} data-test="logout-button">
                                <LogOut />
                                {__('Log out')}
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

export function AppSidebar() {
    const page = usePage();
    const { auth, name } = page.props;
    const user = auth.user;

    if (user === null) {
        return null;
    }

    return (
        <Sidebar collapsible="offcanvas">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()}>
                                <AppLogo name={name} />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {navigation(user).map((group) => (
                    <SidebarGroup key={group.heading}>
                        <SidebarGroupLabel>{group.heading}</SidebarGroupLabel>
                        <SidebarMenu>
                            {group.items.map((item) => (
                                <SidebarMenuItem key={item.href}>
                                    <SidebarMenuButton asChild isActive={isCurrent(page.url, item)}>
                                        <Link href={item.href}>
                                            <item.icon />
                                            <span>{item.label}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter>
                {user.tenant && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <a href={user.tenant.public_url} target="_blank" rel="noopener">
                                    <ExternalLink />
                                    <span>{__('View public page')}</span>
                                </a>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}

                <LocaleSwitcher />

                <UserMenu user={user} />
            </SidebarFooter>
        </Sidebar>
    );
}
