import { Breadcrumbs } from '@/components/breadcrumbs';
import { HeaderSearch } from '@/components/header-search';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { NotificationBell } from '@/components/notification-bell';
import { LayoutGrid, Moon, PackagePlus, ShoppingBag, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

function ThemeToggle() {
    const { appearance, updateAppearance } = useAppearance();
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        const dark =
            appearance === 'dark' ||
            (appearance === 'system' && typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        setIsDark(dark);
    }, [appearance]);

    return (
        <Button
            variant="ghost"
            size="icon"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            className="h-9 w-9"
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
        >
            {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
        </Button>
    );
}

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const getInitials = useInitials();
    const onDashboard = page.url === '/dashboard' || page.url.startsWith('/dashboard?');
    const modules = ((page.props as { access?: { modules?: string[] } }).access?.modules) ?? [];
    const allowed = (module: string) => modules.includes('*') || modules.includes(module);

    return (
        <header className="bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b px-3 backdrop-blur md:gap-3 md:px-4">
            <SidebarTrigger className="-ml-1" />

            <Button variant={onDashboard ? 'default' : 'outline'} size="sm" className="h-9 shrink-0 gap-1.5 px-2.5" asChild>
                <Link href="/dashboard">
                    <LayoutGrid className="h-4 w-4" />
                    <span className="hidden sm:inline">Dashboard</span>
                </Link>
            </Button>

            {allowed('products') && (
                <Button variant="outline" size="sm" className="h-9 shrink-0 gap-1.5 px-2.5" asChild>
                    <Link href="/products/create">
                        <PackagePlus className="h-4 w-4" />
                        <span className="hidden md:inline">Add product</span>
                    </Link>
                </Button>
            )}
            {allowed('sales') && (
                <Button size="sm" className="h-9 shrink-0 gap-1.5 px-2.5" asChild>
                    <Link href="/sales/create">
                        <ShoppingBag className="h-4 w-4" />
                        <span className="hidden md:inline">Sell</span>
                    </Link>
                </Button>
            )}

            <div className="min-w-0 flex-1 md:max-w-xl">
                <HeaderSearch />
            </div>

            <div className="ml-auto hidden min-w-0 items-center lg:flex">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className={cn('flex shrink-0 items-center gap-1', breadcrumbs.length === 0 && 'ml-auto lg:ml-0')}>
                <NotificationBell />
                <ThemeToggle />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-9 w-9 rounded-full p-0">
                            <Avatar className="h-8 w-8">
                                <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                                <AvatarFallback>{getInitials(auth.user.name)}</AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-56" align="end" sideOffset={8}>
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
