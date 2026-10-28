// resources/js/components/app-header.tsx

import { Breadcrumbs } from '@/components/breadcrumbs';
import { Icon } from '@/components/icon';
import { ProductSearchBar } from '@/components/product-search-bar';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { NavigationMenu, NavigationMenuItem, NavigationMenuList, navigationMenuTriggerStyle } from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type NavItem, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Menu, Moon, Search, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogo from './app-logo';
import AppLogoIcon from './app-logo-icon';

/* =========================================================
 |  Navigation Items
 ========================================================= */

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
];

const rightNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

const activeItemStyles = 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';

/* =========================================================
 |  ⭐ Theme Toggle Button
 ========================================================= */

function ThemeToggle() {
    const { appearance, updateAppearance } = useAppearance();

    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        const checkDark = () => {
            const dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            setIsDark(dark);
        };
        checkDark();
    }, [appearance]);

    const toggle = () => {
        updateAppearance(isDark ? 'light' : 'dark');
    };

    return (
        <TooltipProvider delayDuration={0}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button variant="ghost" size="icon" onClick={toggle} className="h-9 w-9 cursor-pointer" aria-label="Toggle theme">
                        {isDark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{isDark ? 'Light mode' : 'Dark mode'}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

/* =========================================================
 |  ⭐ Simple Search Dialog (صفحه‌ای)
 ========================================================= */

interface SearchItem {
    title: string;
    url: string;
    category: string;
}

const SEARCH_ITEMS: SearchItem[] = [
    { title: 'Dashboard', url: '/dashboard', category: 'Main' },
    { title: 'Branches', url: '/branches', category: 'Main' },
    { title: 'Users', url: '/users', category: 'Main' },
    { title: 'Settings', url: '/settings', category: 'Main' },
    { title: 'Products', url: '/products', category: 'Products' },
    { title: 'Brands', url: '/brands', category: 'Products' },
    { title: 'Categories', url: '/categories', category: 'Products' },
    { title: 'Product Batches', url: '/batches', category: 'Products' },
    { title: 'Inventory', url: '/inventory', category: 'Products' },
    { title: 'Stock Transfers', url: '/stock-transfers', category: 'Products' },
    { title: 'Sales', url: '/sales', category: 'Sales' },
    { title: 'Sale Returns', url: '/sale-returns', category: 'Sales' },
    { title: 'Purchases', url: '/purchases', category: 'Sales' },
    { title: 'Suppliers', url: '/suppliers', category: 'Sales' },
    { title: 'Customers', url: '/customers', category: 'Sales' },
    { title: 'Investors', url: '/investors', category: 'Investments' },
    { title: 'Investments', url: '/investments', category: 'Investments' },
    { title: 'Profit Entries', url: '/investor-profit-entries', category: 'Investments' },
    { title: 'Payments', url: '/payments', category: 'Financial' },
    { title: 'Currencies', url: '/currencies', category: 'Financial' },
    { title: 'Expenses', url: '/expenses', category: 'Financial' },
];

function SearchDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const [query, setQuery] = useState('');

    const filtered = query.trim()
        ? SEARCH_ITEMS.filter(
              (item) => item.title.toLowerCase().includes(query.toLowerCase()) || item.category.toLowerCase().includes(query.toLowerCase()),
          )
        : SEARCH_ITEMS.slice(0, 8);

    const handleSelect = (url: string) => {
        onClose();
        router.get(url);
        setQuery('');
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-20" onClick={onClose}>
            <div
                className="w-full max-w-lg overflow-hidden rounded-lg border bg-white shadow-xl dark:bg-neutral-900"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center gap-3 border-b px-4 py-3">
                    <Search className="h-5 w-5 text-gray-400" />
                    <input
                        autoFocus
                        type="text"
                        placeholder="Search pages..."
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        className="flex-1 bg-transparent text-sm focus:outline-none"
                    />
                    <kbd className="rounded border px-1.5 py-0.5 text-xs text-gray-500">Esc</kbd>
                </div>

                <div className="max-h-96 overflow-y-auto py-2">
                    {filtered.length === 0 ? (
                        <div className="px-4 py-8 text-center text-sm text-gray-500">No results for "{query}"</div>
                    ) : (
                        filtered.map((item) => (
                            <button
                                key={item.url}
                                onClick={() => handleSelect(item.url)}
                                className="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-neutral-800"
                            >
                                <span>{item.title}</span>
                                <span className="text-xs text-gray-400">{item.category}</span>
                            </button>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}

/* =========================================================
 |  ⭐ App Header — with Gold accent + User Dropdown + Theme Toggle
 ========================================================= */

interface AppHeaderProps {
    breadcrumbs?: BreadcrumbItem[];
}

export function AppHeader({ breadcrumbs = [] }: AppHeaderProps) {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const getInitials = useInitials();
    const [searchOpen, setSearchOpen] = useState(false);

    // Keyboard shortcut Ctrl+K / Cmd+K
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                setSearchOpen(true);
            }
            if (e.key === 'Escape' && searchOpen) {
                setSearchOpen(false);
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [searchOpen]);

    return (
        <>
            {/* ═══════════════════════════════════════════
                GOLD ACCENT HEADER
                ═══════════════════════════════════════════ */}
            <div className="border-b border-amber-200 bg-amber-50/70 dark:border-amber-900/40 dark:bg-amber-950/30">
                <div className="mx-auto flex h-16 items-center gap-3 px-4 md:max-w-7xl">
                    {/* Mobile Menu */}
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="ghost" size="icon" className="mr-2 h-[34px] w-[34px]">
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="flex h-full w-64 flex-col items-stretch justify-between">
                                <SheetTitle className="sr-only">Navigation Menu</SheetTitle>
                                <SheetHeader className="flex justify-start text-left">
                                    <AppLogoIcon className="h-6 w-6 fill-current text-amber-600" />
                                </SheetHeader>
                                <div className="mt-6 flex h-full flex-1 flex-col space-y-4">
                                    <div className="flex flex-col space-y-4">
                                        {mainNavItems.map((item) => (
                                            <Link key={item.title} href={item.url} className="flex items-center space-x-2 font-medium">
                                                {item.icon && <Icon iconNode={item.icon} className="h-5 w-5" />}
                                                <span>{item.title}</span>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link href="/dashboard" className="flex items-center space-x-2">
                        <AppLogo />
                    </Link>

                    {/* Desktop Navigation */}
                    <div className="ml-6 hidden h-full items-center space-x-6 lg:flex">
                        <NavigationMenu className="flex h-full items-stretch">
                            <NavigationMenuList className="flex h-full items-stretch space-x-2">
                                {mainNavItems.map((item, index) => (
                                    <NavigationMenuItem key={index} className="relative flex h-full items-center">
                                        <Link
                                            href={item.url}
                                            className={cn(
                                                navigationMenuTriggerStyle(),
                                                page.url === item.url && activeItemStyles,
                                                'h-9 cursor-pointer px-3',
                                            )}
                                        >
                                            {item.icon && <Icon iconNode={item.icon} className="mr-2 h-4 w-4" />}
                                            {item.title}
                                        </Link>
                                        {page.url === item.url && <div className="absolute bottom-0 left-0 h-0.5 w-full bg-amber-500" />}
                                    </NavigationMenuItem>
                                ))}
                            </NavigationMenuList>
                        </NavigationMenu>
                    </div>

                    {/* ⭐ Right Side */}
                    <div className="ml-auto flex items-center gap-2">
                        {/* ⭐ Product Search Bar — دسکتاپ */}
                        <div className="hidden w-72 md:block lg:w-80">
                            <ProductSearchBar />
                        </div>

                        {/* ⭐ Search Button — Search Dialog صفحه‌ای */}
                        <TooltipProvider delayDuration={0}>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="icon" onClick={() => setSearchOpen(true)} className="h-9 w-9 cursor-pointer">
                                        <Search className="h-5 w-5 opacity-80" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Search pages (Ctrl+K)</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        {/* Theme Toggle */}
                        <ThemeToggle />

                        {/* External Links */}
                        <div className="hidden lg:flex">
                            {rightNavItems.map((item) => (
                                <TooltipProvider key={item.title} delayDuration={0}>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <a
                                                href={item.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-md text-sm font-medium hover:bg-black/5 dark:hover:bg-white/5"
                                            >
                                                <span className="sr-only">{item.title}</span>
                                                {item.icon && <Icon iconNode={item.icon} className="h-5 w-5 opacity-80" />}
                                            </a>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{item.title}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            ))}
                        </div>

                        {/* User Dropdown */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" className="h-10 w-10 rounded-full p-1">
                                    <Avatar className="h-8 w-8 overflow-hidden rounded-full ring-2 ring-amber-400/50">
                                        <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                                        <AvatarFallback className="rounded-lg bg-amber-100 text-black dark:bg-amber-900/50 dark:text-amber-100">
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end" sideOffset={8}>
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            {/* Breadcrumbs Row */}
            {breadcrumbs.length > 1 && (
                <div className="border-b border-amber-100 dark:border-amber-900/20">
                    <div className="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}

            {/* Search Dialog */}
            <SearchDialog open={searchOpen} onClose={() => setSearchOpen(false)} />
        </>
    );
}
