import { HideManageActions } from '@/components/hide-manage-actions';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    const role = (usePage().props as { auth?: { user?: { role?: string } } }).auth?.user?.role;

    if (role === 'investor') {
        return <div className="bg-background min-h-screen">{children}</div>;
    }

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <HideManageActions />
                {children}
            </AppContent>
        </AppShell>
    );
}
