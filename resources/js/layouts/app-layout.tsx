import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppLogo } from '@/components/app-logo';
import { AppSidebar } from '@/components/app-sidebar';
import { LayoutNotices } from '@/components/layout-notices';
import { Toaster } from '@/components/toaster';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';

/**
 * The back-office shell: the sidebar, the notices above the page and the page.
 * Persistent across Inertia visits, so the sidebar keeps its scroll position.
 * On a phone the sidebar becomes a drawer, opened from the bar at the top.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
    const { name, auth } = usePage().props;

    return (
        <SidebarProvider>
            <AppSidebar />

            <SidebarInset>
                <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b bg-sidebar px-3 md:hidden">
                    <SidebarTrigger className="-ms-1 size-10" />
                    <div className="flex min-w-0 items-center gap-2">
                        <AppLogo name={auth.user?.tenant?.name ?? name} />
                    </div>
                </header>

                <LayoutNotices />

                {/* SidebarInset is already the page's <main>. */}
                <div className="flex-1 p-4 sm:p-6 lg:p-8">{children}</div>
            </SidebarInset>

            <Toaster />
        </SidebarProvider>
    );
}
