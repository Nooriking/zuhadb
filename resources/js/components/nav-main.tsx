import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

function isItemActive(itemUrl: string, current: string) {
    const [path, query] = itemUrl.split('?');
    const [currentPath, currentQuery = ''] = current.split('?');

    if (query) {
        if (currentPath !== path) return false;
        const wanted = new URLSearchParams(query);
        const actual = new URLSearchParams(currentQuery);
        for (const [key, value] of wanted) {
            if (actual.get(key) !== value) return false;
        }
        return true;
    }

    if (path === '/dashboard') {
        return currentPath === '/dashboard' || currentPath.startsWith('/dashboard/');
    }

    if (path === '/receipts') {
        return currentPath.startsWith('/receipts') && !currentQuery.includes('receipt_type=');
    }

    return currentPath === path || currentPath.startsWith(`${path}/`);
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton asChild isActive={isItemActive(item.url, page.url)}>
                            <Link href={item.url}>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
