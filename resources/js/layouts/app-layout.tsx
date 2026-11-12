import type { ReactNode } from 'react';
import { AppSidebar } from '@/components/app-sidebar';
import { LayoutNotices } from '@/components/layout-notices';
import { Toaster } from '@/components/toaster';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';

/**
 * The back-office shell: the sidebar, the notices above the page and the page.
 * Persistent across Inertia visits, so the sidebar keeps its scroll position.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
    return (
        <SidebarProvider>
            <AppSidebar />

            <SidebarInset>
                <header className="flex h-14 items-center border-b px-4 md:hidden">
                    <SidebarTrigger className="-ms-1" />
                </header>

                <LayoutNotices />

                <main className="flex-1 p-6 lg:p-8">{children}</main>
            </SidebarInset>

            <Toaster />
        </SidebarProvider>
    );
}
