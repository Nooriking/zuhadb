// resources/js/pages/dashboard.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Boxes, DollarSign, Package, ShoppingBag, ShoppingCart, TrendingUp, UserCircle, Users, Wallet } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';

import { LazyInView } from '@/components/lazy-in-view';
import KpiCard from '@/components/dashboard/kpi-card';
import LowStock from '@/components/dashboard/low-stock';
import RecentSales from '@/components/dashboard/recent-sales';

const SalesChart = lazy(() => import('@/components/dashboard/sales-chart'));
const BrandPie = lazy(() => import('@/components/dashboard/brand-pie'));
const PaymentPie = lazy(() => import('@/components/dashboard/payment-pie'));
const BranchBar = lazy(() => import('@/components/dashboard/branch-bar'));

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Kpis {
    today_sales: number;
    today_collected: number;
    today_profit: number;
    today_orders: number;
    month_sales: number;
    month_profit: number;
    month_expenses: number;
    month_net: number;
    sales_growth: number | null;
    outstanding_due: number;
}

interface Counters {
    products: number;
    variants: number;
    customers: number;
    suppliers: number;
    investors: number;
    users: number;
    total_stock: number;
    low_stock: number;
    out_of_stock: number;
}

interface SalesTrendPoint {
    date: string;
    sales: number;
    profit: number;
    count: number;
}

interface BrandSale {
    name: string;
    value: number;
}

interface TopProduct {
    id: number;
    name: string;
    brand: string | null;
    total_qty: number;
    total_sales: number;
    total_profit: number;
}

interface BranchStat {
    id: number;
    name: string;
    code: string;
    sales: number;
    profit: number;
    orders: number;
}

interface PaymentMethod {
    name: string;
    value: number;
}

interface ActivityItem {
    id: number;
    section: string;
    title: string;
    body: string | null;
    url: string;
    created_at: string | null;
}

interface RecentSale {
    id: number;
    invoice_number: string;
    total_afn: number;
    payment_status: string;
    status: string;
    sale_date: string | null;
    branch: string | null;
    customer: string;
}

interface LowStockItem {
    id: number;
    branch: string | null;
    product: string;
    brand: string | null;
    volume_ml: number | null;
    quantity: number;
    min_stock: number;
}

interface Props {
    days: number;
    kpis: Kpis;
    salesTrend: SalesTrendPoint[];
    brandSales: BrandSale[];
    topProducts: TopProduct[];
    branchStats: BranchStat[];
    paymentMethods: PaymentMethod[];
    counters: Counters;
    recentSales: RecentSale[];
    recentActivity?: ActivityItem[];
    lowStockItems: LowStockItem[];
    dueCustomers: { id: number; name: string; phone: string | null; current_due_afn: number; due_date: string | null }[];
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const formatPrice = (v: number) =>
    new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v);

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function Dashboard({
    days,
    kpis,
    salesTrend,
    brandSales,
    topProducts,
    branchStats,
    paymentMethods,
    counters,
    recentSales,
    recentActivity = [],
    lowStockItems,
    dueCustomers = [],
}: Props) {
    const [range, setRange] = useState(days);

    const changeRange = (newDays: number) => {
        setRange(newDays);
        router.get('/dashboard', { days: newDays }, { preserveScroll: true, preserveState: false });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="dash-luxe flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                        <p className="text-muted-foreground text-sm">Overview of your perfume business</p>
                    </div>

                    <div className="bg-card flex gap-1 rounded-md border p-1">
                        {[7, 30, 90].map((d) => (
                            <button
                                key={d}
                                onClick={() => changeRange(d)}
                                className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                    range === d
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                                }`}
                            >
                                {d} Days
                            </button>
                        ))}
                    </div>
                </div>

                {/* ═══════════ KPI Cards Row 1 ═══════════ */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        title="Today Sales"
                        value={`${formatPrice(kpis.today_sales)} AFN`}
                        subtitle={`${kpis.today_orders} completed orders`}
                        icon={ShoppingBag}
                        color="blue"
                    />
                    <KpiCard
                        title="Collected Today"
                        value={`${formatPrice(kpis.today_collected)} AFN`}
                        subtitle="Payments received today"
                        icon={Wallet}
                        color="green"
                    />
                    <KpiCard
                        title="Today Profit"
                        value={`${formatPrice(kpis.today_profit)} AFN`}
                        subtitle="Store profit after investor share"
                        icon={TrendingUp}
                        color="green"
                    />
                    <KpiCard
                        title="Customer Due"
                        value={`${formatPrice(kpis.outstanding_due)} AFN`}
                        subtitle="Money customers still owe"
                        icon={AlertTriangle}
                        color={kpis.outstanding_due > 0 ? 'red' : 'green'}
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        title="Month Sales"
                        value={`${formatPrice(kpis.month_sales)} AFN`}
                        subtitle="Completed sales this month"
                        icon={DollarSign}
                        color="purple"
                        trend={
                            kpis.sales_growth === null
                                ? undefined
                                : {
                                      value: kpis.sales_growth,
                                      isPositive: kpis.sales_growth >= 0,
                                  }
                        }
                    />
                    <KpiCard
                        title="Month Profit"
                        value={`${formatPrice(kpis.month_profit)} AFN`}
                        subtitle="Store profit this month"
                        icon={TrendingUp}
                        color="green"
                    />
                    <KpiCard
                        title="Month Expenses"
                        value={`${formatPrice(kpis.month_expenses)} AFN`}
                        subtitle="Recorded expenses"
                        icon={ShoppingCart}
                        color="orange"
                    />
                    <KpiCard
                        title="Month Net"
                        value={`${formatPrice(kpis.month_net)} AFN`}
                        subtitle="Store profit − expenses"
                        icon={Wallet}
                        color={kpis.month_net >= 0 ? 'green' : 'red'}
                    />
                </div>

                {/* ═══════════ KPI Cards Row 2 — Counters ═══════════ */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <KpiCard title="Products" value={counters.products} icon={Package} color="blue" />
                    <KpiCard title="Variants" value={counters.variants} icon={Boxes} color="gray" />
                    <KpiCard title="Customers" value={counters.customers} icon={UserCircle} color="orange" />
                    <KpiCard title="Suppliers" value={counters.suppliers} icon={ShoppingCart} color="purple" />
                    <KpiCard title="Investors" value={counters.investors} icon={Users} color="green" />
                    <KpiCard title="Users" value={counters.users} icon={Users} color="gray" />
                </div>

                {/* ═══════════ Stock Counters ═══════════ */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="bg-card rounded-lg border p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-muted-foreground text-xs font-medium uppercase">Total Stock</p>
                                <p className="mt-2 text-2xl font-bold">{formatPrice(counters.total_stock)}</p>
                                <p className="text-muted-foreground mt-1 text-xs">Units in inventory</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-full bg-blue-100 ring-2 ring-blue-200 dark:bg-blue-950 dark:ring-blue-900">
                                <Boxes className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>
                    </div>

                    <div className="bg-card rounded-lg border p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-muted-foreground text-xs font-medium uppercase">Low Stock</p>
                                <p className="mt-2 text-2xl font-bold text-yellow-600 dark:text-yellow-500">{counters.low_stock}</p>
                                <p className="text-muted-foreground mt-1 text-xs">At or below variant minimum</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-full bg-yellow-100 ring-2 ring-yellow-200 dark:bg-yellow-950 dark:ring-yellow-900">
                                <AlertTriangle className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                            </div>
                        </div>
                    </div>

                    <div className="bg-card rounded-lg border p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-muted-foreground text-xs font-medium uppercase">Out of Stock</p>
                                <p className="mt-2 text-2xl font-bold text-red-600 dark:text-red-500">{counters.out_of_stock}</p>
                                <p className="text-muted-foreground mt-1 text-xs">Items unavailable</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-full bg-red-100 ring-2 ring-red-200 dark:bg-red-950 dark:ring-red-900">
                                <Package className="h-5 w-5 text-red-600 dark:text-red-400" />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-card rounded-lg border p-6 shadow-sm">
                    <h3 className="text-lg font-semibold">Customer dues</h3>
                    <p className="text-muted-foreground mb-4 text-sm">Who owes money, and the date they should pay the rest</p>
                    {dueCustomers.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No open customer dues.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left">
                                    <tr>
                                        <th className="py-2">Customer</th>
                                        <th className="py-2 text-right">Due</th>
                                        <th className="py-2">Pay by</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {dueCustomers.map((customer) => (
                                        <tr key={customer.id} className="border-t">
                                            <td className="py-2">
                                                <Link href={`/customers/${customer.id}#pay-due`} className="font-medium underline">
                                                    {customer.name}
                                                </Link>
                                                {customer.phone && <div className="text-muted-foreground text-xs">{customer.phone}</div>}
                                            </td>
                                            <td className="py-2 text-right font-mono">{formatPrice(customer.current_due_afn)} AFN</td>
                                            <td className="py-2">{customer.due_date || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* ═══════════ Sales Trend Chart ═══════════ */}
                <div className="bg-card rounded-lg border p-6 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-semibold">Sales & Profit Trend</h3>
                            <p className="text-muted-foreground text-sm">Completed sales and store profit for the last {days} days</p>
                        </div>
                    </div>
                    <LazyInView minHeight={280}>
                        <Suspense fallback={<div className="bg-muted/40 h-[280px] animate-pulse rounded-xl" />}>
                            <SalesChart data={salesTrend} />
                        </Suspense>
                    </LazyInView>
                </div>

                {/* ═══════════ Charts Row: Brand + Payment ═══════════ */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="bg-card rounded-lg border p-6 shadow-sm">
                        <h3 className="mb-4 text-lg font-semibold">Sales by Brand</h3>
                        <LazyInView minHeight={280}>
                            <Suspense fallback={<div className="bg-muted/40 h-[280px] animate-pulse rounded-xl" />}>
                                <BrandPie data={brandSales} />
                            </Suspense>
                        </LazyInView>
                    </div>
                    <div className="bg-card rounded-lg border p-6 shadow-sm">
                        <h3 className="text-lg font-semibold">Payments & Due</h3>
                        <p className="text-muted-foreground mb-4 text-sm">Collected by method, plus remaining due</p>
                        <LazyInView minHeight={280}>
                            <Suspense fallback={<div className="bg-muted/40 h-[280px] animate-pulse rounded-xl" />}>
                                <PaymentPie data={paymentMethods} />
                            </Suspense>
                        </LazyInView>
                    </div>
                </div>

                {/* ═══════════ Branch Bar Chart ═══════════ */}
                <div className="bg-card rounded-lg border p-6 shadow-sm">
                    <h3 className="mb-4 text-lg font-semibold">Branch Performance</h3>
                    <LazyInView minHeight={280}>
                        <Suspense fallback={<div className="bg-muted/40 h-[280px] animate-pulse rounded-xl" />}>
                            <BranchBar data={branchStats} />
                        </Suspense>
                    </LazyInView>
                </div>

                <LowStock items={lowStockItems} />

                {/* ═══════════ Top Products ═══════════ */}
                <div className="bg-card overflow-hidden rounded-lg border shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h3 className="text-lg font-semibold">Top Selling Products</h3>
                        <p className="text-muted-foreground text-sm">Top 10 in last {days} days</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="text-muted-foreground px-6 py-3 text-left text-xs font-semibold uppercase">#</th>
                                    <th className="text-muted-foreground px-6 py-3 text-left text-xs font-semibold uppercase">Product</th>
                                    <th className="text-muted-foreground px-6 py-3 text-right text-xs font-semibold uppercase">Sold Qty</th>
                                    <th className="text-muted-foreground px-6 py-3 text-right text-xs font-semibold uppercase">Total Sales</th>
                                    <th className="text-muted-foreground px-6 py-3 text-right text-xs font-semibold uppercase">Store Profit</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {topProducts.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground px-6 py-6 text-center text-sm">
                                            No sales data.
                                        </td>
                                    </tr>
                                ) : (
                                    topProducts.map((p, i) => (
                                        <tr key={p.id} className="hover:bg-muted/50">
                                            <td className="text-muted-foreground px-6 py-3">{i + 1}</td>
                                            <td className="px-6 py-3">
                                                <div className="font-medium">{p.name}</div>
                                                {p.brand && <div className="text-muted-foreground text-xs">{p.brand}</div>}
                                            </td>
                                            <td className="px-6 py-3 text-right font-mono">{p.total_qty}</td>
                                            <td className="px-6 py-3 text-right font-mono font-semibold">{formatPrice(p.total_sales)} AFN</td>
                                            <td className="px-6 py-3 text-right font-mono">{formatPrice(p.total_profit)} AFN</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="bg-card rounded-lg border shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h3 className="text-lg font-semibold">Recent activity</h3>
                        <p className="text-muted-foreground text-sm">Latest sales and shop events</p>
                    </div>
                    <div className="grid grid-cols-1 lg:grid-cols-2">
                        <div className="border-b lg:border-r lg:border-b-0">
                            <div className="flex items-center justify-between px-6 pt-4">
                                <h4 className="text-sm font-semibold">Sales</h4>
                                <Link href="/sales" className="text-xs font-semibold text-blue-600 hover:underline">
                                    View all
                                </Link>
                            </div>
                            <RecentSales sales={recentSales} embedded />
                        </div>
                        <div>
                            <div className="flex items-center justify-between px-6 pt-4">
                                <h4 className="text-sm font-semibold">Events</h4>
                                <Link href="/notifications" className="text-xs font-semibold text-blue-600 hover:underline">
                                    View all
                                </Link>
                            </div>
                            {recentActivity.length === 0 ? (
                                <p className="text-muted-foreground p-6 text-sm">No recent activity yet.</p>
                            ) : (
                                <div className="divide-y">
                                    {recentActivity.map((item) => (
                                        <Link key={item.id} href={item.url} className="hover:bg-muted/50 flex items-start justify-between gap-4 px-6 py-3">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium">{item.title}</p>
                                                {item.body && <p className="text-muted-foreground truncate text-xs">{item.body}</p>}
                                            </div>
                                            <span className="text-muted-foreground shrink-0 text-xs">{item.created_at}</span>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
