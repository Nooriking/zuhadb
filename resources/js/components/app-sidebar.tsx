// resources/js/components/app-sidebar.tsx

import { NavMain } from '@/components/nav-main';
import { Sidebar, SidebarContent, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Banknote,
    BookOpen,
    DatabaseBackup,
    FileClock,
    HandCoins,
    LayoutGrid,
    Package,
    Receipt,
    RotateCcw,
    Settings,
    ShoppingBag,
    Store,
    Tag,
    TrendingUp,
    Truck,
    UserCircle,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-react';
import AppLogo from './app-logo';

/* =========================================================
 |  MAIN — Dashboard, Branches, Users
 ========================================================= */

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
        module: 'dashboard',
    },
    {
        title: 'Branches',
        url: '/branches',
        icon: Store,
        module: 'branches',
    },
    {
        title: 'Users',
        url: '/users',
        icon: Users,
        module: 'users',
    },
];

/* =========================================================
 |  PRODUCT MANAGEMENT
 ========================================================= */

const productNavItems: NavItem[] = [
    {
        title: 'Products',
        url: '/products',
        icon: Package,
        module: 'products',
    },
    {
        title: 'Brands',
        url: '/brands',
        icon: Tag,
        module: 'brands',
    },
    {
        title: 'Inventory',
        url: '/inventory',
        icon: Warehouse,
        module: 'inventory',
    },
    {
        title: 'Stock Transfers',
        url: '/stock-transfers',
        icon: ArrowLeftRight,
        module: 'stock-transfers',
    },
    {
        title: 'Price History',
        url: '/product-price-histories',
        icon: FileClock,
        module: 'products',
    },
];

/* =========================================================
 |  SALES & PURCHASES
 ========================================================= */

const transactionNavItems: NavItem[] = [
    {
        title: 'Sales',
        url: '/sales',
        icon: ShoppingBag,
        module: 'sales',
    },
    {
        title: 'Sale Returns',
        url: '/sale-returns',
        icon: RotateCcw,
        module: 'sale-returns',
    },
    {
        title: 'Payment Receipts',
        url: '/receipts?receipt_type=payment_receipt',
        icon: Receipt,
        module: 'sales',
    },
    {
        title: 'Suppliers',
        url: '/suppliers',
        icon: Truck,
        module: 'suppliers',
    },
];

/* =========================================================
 |  CUSTOMERS  ⭐ NEW SECTION
 ========================================================= */

const customerNavItems: NavItem[] = [
    {
        title: 'Customers',
        url: '/customers',
        icon: UserCircle,
        module: 'customers',
    },
    {
        title: 'Customer Ledger',
        url: '/customer-transactions',
        icon: BookOpen,
        module: 'customers',
    },
];

/* =========================================================
 |  INVESTMENT MANAGEMENT
 ========================================================= */

const investmentNavItems: NavItem[] = [
    {
        title: 'Investors',
        url: '/investments',
        icon: HandCoins,
        module: 'investments',
    },
    {
        title: 'Withdrawals',
        url: '/investor-withdrawals',
        icon: Banknote,
        module: 'investor-withdrawals',
    },
    {
        title: 'Profit',
        url: '/investor-profit-entries',
        icon: TrendingUp,
        module: 'investor-profit-entries',
    },
    {
        title: 'Investor Ledger',
        url: '/investor-transactions',
        icon: Wallet,
        module: 'investor-transactions',
    },
];

/* =========================================================
 |  FINANCIAL MANAGEMENT
 ========================================================= */

const financialNavItems: NavItem[] = [
    {
        title: 'Expenses',
        url: '/expenses',
        icon: Receipt,
        module: 'expenses',
    },
];

/* =========================================================
 |  SETTINGS
 ========================================================= */

const settingsNavItems: NavItem[] = [
    {
        title: 'App Settings',
        url: '/app-settings',
        icon: Settings,
        module: 'settings',
    },
    {
        title: 'Backups',
        url: '/backups',
        icon: DatabaseBackup,
        module: 'settings',
    },
];

/* =========================================================
 |  SIDEBAR COMPONENT
 ========================================================= */

function allowedItems(items: NavItem[], modules: string[], showAll: boolean) {
    if (showAll || modules.includes('*')) {
        return items;
    }
    return items.filter((item) => !item.module || modules.includes(item.module));
}

export function AppSidebar() {
    const page = usePage().props as {
        access?: { is_super_admin?: boolean; modules?: string[] };
        shop?: { name?: string; phone?: string; address?: string };
    };
    const access = page.access;
    const shop = page.shop;
    const modules = access?.modules ?? [];
    const showAll = Boolean(access?.is_super_admin);
    const main = allowedItems(mainNavItems, modules, showAll);
    const products = allowedItems(productNavItems, modules, showAll);
    const transactions = allowedItems(transactionNavItems, modules, showAll);
    const customers = allowedItems(customerNavItems, modules, showAll);
    const investments = allowedItems(investmentNavItems, modules, showAll);
    const financial = allowedItems(financialNavItems, modules, showAll);
    const settings = allowedItems(settingsNavItems, modules, showAll);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {(shop?.phone || shop?.address) && (
                    <div className="text-muted-foreground space-y-0.5 px-4 pb-2 text-[11px] leading-snug group-data-[collapsible=icon]:hidden">
                        {shop.phone && <p className="truncate">{shop.phone}</p>}
                        {shop.address && <p className="line-clamp-2">{shop.address}</p>}
                    </div>
                )}
            </SidebarHeader>

            <SidebarContent>
                {main.length > 0 && <NavMain items={main} />}

                {products.length > 0 && (
                    <div className="mt-4">
                        <div className="px-3 py-2">
                            <h3 className="text-muted-foreground mb-1 text-[11px] font-semibold tracking-wider uppercase">Product Management</h3>
                        </div>
                        <NavMain items={products} />
                    </div>
                )}

                {transactions.length > 0 && (
                    <div className="mt-4">
                        <div className="px-3 py-2">
                            <h3 className="text-muted-foreground mb-1 text-[11px] font-semibold tracking-wider uppercase">Sales & Purchases</h3>
                        </div>
                        <NavMain items={transactions} />
                    </div>
                )}

                {customers.length > 0 && (
                    <div className="mt-4">
                        <div className="px-3 py-2">
                            <h3 className="text-muted-foreground mb-1 text-[11px] font-semibold tracking-wider uppercase">Customers</h3>
                        </div>
                        <NavMain items={customers} />
                    </div>
                )}

                {investments.length > 0 && (
                    <div className="mt-4">
                        <div className="px-3 py-2">
                            <h3 className="text-muted-foreground mb-1 text-[11px] font-semibold tracking-wider uppercase">Investment Management</h3>
                        </div>
                        <NavMain items={investments} />
                    </div>
                )}

                {financial.length > 0 && (
                    <div className="mt-4">
                        <div className="px-3 py-2">
                            <h3 className="text-muted-foreground mb-1 text-[11px] font-semibold tracking-wider uppercase">Financial Management</h3>
                        </div>
                        <NavMain items={financial} />
                    </div>
                )}

                {settings.length > 0 && (
                    <div className="mt-4">
                        <div className="px-3 py-2">
                            <h3 className="text-muted-foreground mb-1 text-[11px] font-semibold tracking-wider uppercase">Settings</h3>
                        </div>
                        <NavMain items={settings} />
                    </div>
                )}
            </SidebarContent>
        </Sidebar>
    );
}
