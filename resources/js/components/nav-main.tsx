import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';

export function isItemActive(itemUrl: string, current: string) {
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

    // Keep list routes from stealing active state from dedicated report/dashboard URLs.
    if (path === '/sales') {
        return (
            currentPath === '/sales' ||
            (currentPath.startsWith('/sales/') && currentPath !== '/sales/report')
        );
    }

    if (path === '/investors') {
        return (
            currentPath === '/investors' ||
            (currentPath.startsWith('/investors/') && currentPath !== '/investors/dashboard')
        );
    }

    return currentPath === path || currentPath.startsWith(`${path}/`);
}

function hasActiveDescendant(item: NavItem, current: string): boolean {
    if (item.url && isItemActive(item.url, current)) {
        return true;
    }
    return (item.children ?? []).some((child) => hasActiveDescendant(child, current));
}

function NavBranch({ item, depth = 0 }: { item: NavItem; depth?: number }) {
    const page = usePage();
    const current = page.url;
    const children = item.children ?? [];
    const hasChildren = children.length > 0;
    const selfActive = item.url ? isItemActive(item.url, current) : false;
    const descendantActive = hasChildren && children.some((child) => hasActiveDescendant(child, current));
    const groupActive = selfActive || descendantActive;
    const [open, setOpen] = useState(groupActive);

    useEffect(() => {
        if (groupActive) {
            setOpen(true);
        }
    }, [groupActive]);

    if (!hasChildren) {
        if (!item.url) {
            return null;
        }

        if (depth === 0) {
            return (
                <SidebarMenuItem>
                    <SidebarMenuButton asChild isActive={selfActive} tooltip={item.title}>
                        <Link href={item.url}>
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            );
        }

        return (
            <SidebarMenuSubItem>
                <SidebarMenuSubButton asChild isActive={selfActive} size="sm">
                    <Link href={item.url}>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                    </Link>
                </SidebarMenuSubButton>
            </SidebarMenuSubItem>
        );
    }

    const chevron = (
        <ChevronRight
            className={cn(
                'ms-auto size-4 shrink-0 transition-transform duration-200',
                'rtl:-scale-x-100',
                open && 'rotate-90',
            )}
            aria-hidden
        />
    );

    if (depth === 0) {
        return (
            <SidebarMenuItem>
                <Collapsible open={open} onOpenChange={setOpen} className="group/collapsible w-full">
                    <CollapsibleTrigger asChild>
                        <SidebarMenuButton type="button" isActive={groupActive} tooltip={item.title} aria-expanded={open}>
                            {item.icon && <item.icon />}
                            <span className="flex-1 text-start">{item.title}</span>
                            {chevron}
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub className="ms-3.5 me-0 border-s border-sidebar-border px-2.5 translate-x-0">
                            {children.map((child) => (
                                <NavBranch key={child.title} item={child} depth={depth + 1} />
                            ))}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </Collapsible>
            </SidebarMenuItem>
        );
    }

    return (
        <SidebarMenuSubItem>
            <Collapsible open={open} onOpenChange={setOpen} className="group/collapsible w-full">
                <CollapsibleTrigger asChild>
                    <button
                        type="button"
                        data-active={groupActive}
                        aria-expanded={open}
                        className={cn(
                            'flex h-7 w-full min-w-0 items-center gap-2 overflow-hidden rounded-md px-2 text-start text-xs outline-hidden ring-sidebar-ring',
                            'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2',
                            'active:bg-sidebar-accent active:text-sidebar-accent-foreground',
                            'data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground',
                            '[&>span:last-of-type]:truncate [&>svg]:size-4 [&>svg]:shrink-0',
                        )}
                    >
                        {item.icon && <item.icon />}
                        <span className="flex-1 truncate text-start">{item.title}</span>
                        {chevron}
                    </button>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub className="mx-0 ms-2 border-s border-sidebar-border px-2 translate-x-0">
                        {children.map((child) => (
                            <NavBranch key={child.title} item={child} depth={depth + 1} />
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </Collapsible>
        </SidebarMenuSubItem>
    );
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu>
                {items.map((item) => (
                    <NavBranch key={item.title} item={item} />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
