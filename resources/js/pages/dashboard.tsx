// resources/js/pages/dashboard.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Boxes, DollarSign, Package, ShoppingBag, ShoppingCart, Store, TrendingUp, UserCircle, Users, Wallet } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';

import CurrencyNote, { type DashboardReporting } from '@/components/dashboard/currency-note';
import KpiCard from '@/components/dashboard/kpi-card';
import LowStock from '@/components/dashboard/low-stock';
import RecentSales from '@/components/dashboard/recent-sales';
import { LazyInView } from '@/components/lazy-in-view';
import { formatMoney } from '@/lib/money';

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
    total_sales_ledger?: number;
    total_profit_ledger?: number;
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

interface PaymentCurrencyTotal {
    code: string;
    symbol?: string | null;
    total: number;
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
    total_ledger?: number;
    ledger_currency_code?: string | null;
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

interface StoreAssetsSummary {
    total_items: number;
    total_qty: number;
    total_remaining_qty: number;
    total_cost_afn: number;
    remaining_cost_afn: number;
    expected_profit_afn: number;
    realized_sales_afn: number;
    realized_qty: number;
    realized_profit_afn: number;
    total_profit_afn: number;
}

interface StoreAssetItem {
    batch_id: number;
    batch_code: string;
    variant_id: number;
    name: string;
    brand: string | null;
    quantity: number;
    remaining: number;
    unit_cost_afn: number;
    sale_price_afn: number;
    total_cost_afn: number;
    remaining_cost_afn: number;
    expected_profit_afn: number;
    received_date: string | null;
    branch_id: number | null;
}

interface StoreAssets {
    summary: StoreAssetsSummary;
    items: StoreAssetItem[];
}

interface Props {
    days: number;
    kpis: Kpis;
    salesTrend: SalesTrendPoint[];
    brandSales: BrandSale[];
    topProducts: TopProduct[];
    branchStats: BranchStat[];
    paymentMethods: PaymentMethod[];
    paymentCurrencyTotals?: PaymentCurrencyTotal[];
    counters: Counters;
    recentSales: RecentSale[];
    recentActivity?: ActivityItem[];
    lowStockItems: LowStockItem[];
    dueCustomers: {
        id: number;
        name: string;
        phone: string | null;
        current_due_afn: number;
        current_due_ledger?: number;
        due_date: string | null;
    }[];
    storeAssets?: StoreAssets;
    reporting: DashboardReporting;
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const FALLBACK_REPORTING: DashboardReporting = {
    currency: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    ledger: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    conversion_available: true,
    converts_from_ledger: false,
};

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
    paymentCurrencyTotals = [],
    counters,
    recentSales,
    recentActivity = [],
    lowStockItems,
    dueCustomers = [],
    storeAssets,
    reporting = FALLBACK_REPORTING,
}: Props) {
    const [range, setRange] = useState(days);
    const currency = reporting.currency;
    const currencyCode = currency.code;
    const money = (value: number) => formatMoney(value, currency);
    const showPaymentBuckets = paymentCurrencyTotals.length > 1 || (!reporting.conversion_available && paymentCurrencyTotals.length > 0);

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
                        <div className="mt-2">
                            <CurrencyNote reporting={reporting} originalCurrencyTotals={paymentCurrencyTotals} />
                        </div>
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
                        value={money(kpis.today_sales)}
                        subtitle={`${kpis.today_orders} completed orders`}
                        icon={ShoppingBag}
                        color="blue"
                    />
                    <KpiCard
                        title="Collected Today"
                        value={money(kpis.today_collected)}
                        subtitle="Payments received today"
                        icon={Wallet}
                        color="green"
                    />
                    <KpiCard
                        title="Today Profit"
                        value={money(kpis.today_profit)}
                        subtitle="Store profit after investor share"
                        icon={TrendingUp}
                        color="green"
                    />
                    <KpiCard
                        title="Customer Due"
                        value={money(kpis.outstanding_due)}
                        subtitle="Money customers still owe"
                        icon={AlertTriangle}
                        color={kpis.outstanding_due > 0 ? 'red' : 'green'}
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        title="Month Sales"
                        value={money(kpis.month_sales)}
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
                        value={money(kpis.month_profit)}
                        subtitle="Store profit this month"
                        icon={TrendingUp}
                        color="green"
                    />
                    <KpiCard
                        title="Month Expenses"
                        value={money(kpis.month_expenses)}
                        subtitle="Recorded expenses"
                        icon={ShoppingCart}
                        color="orange"
                    />
                    <KpiCard
                        title="Month Net"
                        value={money(kpis.month_net)}
                        subtitle="Store profit − expenses"
                        icon={Wallet}
                        color={kpis.month_net >= 0 ? 'green' : 'red'}
                    />
                </div>

                {showPaymentBuckets && (
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <h3 className="text-sm font-semibold">Collected by original payment currency</h3>
                        <p className="text-muted-foreground mb-3 text-xs">
                            Tender amounts from payment snapshots for the selected {days}-day period (not recalculated with today&apos;s rate)
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {paymentCurrencyTotals.map((row) => (
                                <div key={row.code} className="rounded-md border px-3 py-2 text-sm">
                                    <span className="font-mono font-semibold">
                                        {row.total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </span>{' '}
                                    <span className="text-muted-foreground">{row.symbol || row.code}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

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
                                <p className="mt-2 text-2xl font-bold">{counters.total_stock.toLocaleString()}</p>
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

                {/* ═══════════ Store Assets ═══════════ */}
                {storeAssets && (
                    <div className="bg-card rounded-lg border shadow-sm">
                        <div className="border-b px-6 py-4">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 className="flex items-center gap-2 text-lg font-semibold">
                                        <Store className="h-5 w-5 text-purple-600" />
                                        Store-Owned Assets
                                    </h3>
                                    <p className="text-muted-foreground text-sm">
                                        Goods bought by the shop (not investor-owned) — 100% of profit stays with the store
                                    </p>
                                </div>
                                <Link href="/products?owner=store" className="text-xs font-semibold text-blue-600 hover:underline">
                                    View all →
                                </Link>
                            </div>
                        </div>

                        <div className="p-6">
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                <div className="rounded-lg border bg-gradient-to-br from-blue-50 to-blue-100/50 p-4 dark:from-blue-950/40 dark:to-blue-900/20">
                                    <div className="flex items-center gap-2 text-xs font-medium text-blue-800 uppercase dark:text-blue-400">
                                        <Package className="h-3.5 w-3.5" /> Items
                                    </div>
                                    <p className="mt-2 text-2xl font-bold text-blue-900 dark:text-blue-300">{storeAssets.summary.total_items}</p>
                                    <p className="text-xs text-blue-700 dark:text-blue-500">
                                        {storeAssets.summary.total_remaining_qty} / {storeAssets.summary.total_qty} pcs left
                                    </p>
                                </div>

                                <div className="rounded-lg border bg-gradient-to-br from-slate-50 to-slate-100/50 p-4 dark:from-slate-950/40 dark:to-slate-900/20">
                                    <div className="flex items-center gap-2 text-xs font-medium text-slate-700 uppercase dark:text-slate-400">
                                        <DollarSign className="h-3.5 w-3.5" /> Stock Value
                                    </div>
                                    <p className="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-300">
                                        {money(storeAssets.summary.remaining_cost_afn)}
                                    </p>
                                    <p className="text-xs text-slate-600 dark:text-slate-500">At cost</p>
                                </div>

                                <div className="rounded-lg border bg-gradient-to-br from-emerald-50 to-emerald-100/50 p-4 dark:from-emerald-950/40 dark:to-emerald-900/20">
                                    <div className="flex items-center gap-2 text-xs font-medium text-emerald-800 uppercase dark:text-emerald-400">
                                        <TrendingUp className="h-3.5 w-3.5" /> Expected
                                    </div>
                                    <p className="mt-2 text-2xl font-bold text-emerald-900 dark:text-emerald-300">
                                        {money(storeAssets.summary.expected_profit_afn)}
                                    </p>
                                    <p className="text-xs text-emerald-700 dark:text-emerald-500">If all stock sells</p>
                                </div>

                                <div className="rounded-lg border bg-gradient-to-br from-amber-50 to-amber-100/50 p-4 dark:from-amber-950/40 dark:to-amber-900/20">
                                    <div className="flex items-center gap-2 text-xs font-medium text-amber-800 uppercase dark:text-amber-400">
                                        <ShoppingCart className="h-3.5 w-3.5" /> Realized
                                    </div>
                                    <p className="mt-2 text-2xl font-bold text-amber-900 dark:text-amber-300">
                                        {money(storeAssets.summary.realized_profit_afn)}
                                    </p>
                                    <p className="text-xs text-amber-700 dark:text-amber-500">{storeAssets.summary.realized_qty} pcs sold</p>
                                </div>

                                <div className="rounded-lg border bg-gradient-to-br from-violet-50 to-violet-100/50 p-4 dark:from-violet-950/40 dark:to-violet-900/20">
                                    <div className="flex items-center gap-2 text-xs font-medium text-violet-800 uppercase dark:text-violet-400">
                                        <Wallet className="h-3.5 w-3.5" /> Total Profit
                                    </div>
                                    <p className="mt-2 text-2xl font-bold text-violet-900 dark:text-violet-300">
                                        {money(storeAssets.summary.total_profit_afn)}
                                    </p>
                                    <p className="text-xs text-violet-700 dark:text-violet-500">Realized + expected</p>
                                </div>
                            </div>

                            {storeAssets.items.length === 0 ? (
                                <div className="text-muted-foreground mt-6 py-8 text-center text-sm">
                                    <Package className="mx-auto mb-2 h-8 w-8 opacity-40" />
                                    No store-owned stock in inventory yet.
                                </div>
                            ) : (
                                <div className="mt-6 overflow-x-auto rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50">
                                            <tr>
                                                <th className="text-muted-foreground px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                                <th className="text-muted-foreground px-4 py-3 text-left text-xs font-semibold uppercase">Product</th>
                                                <th className="text-muted-foreground px-4 py-3 text-right text-xs font-semibold uppercase">Cost</th>
                                                <th className="text-muted-foreground px-4 py-3 text-right text-xs font-semibold uppercase">Sale</th>
                                                <th className="text-muted-foreground px-4 py-3 text-right text-xs font-semibold uppercase">
                                                    Remaining
                                                </th>
                                                <th className="text-muted-foreground px-4 py-3 text-right text-xs font-semibold uppercase">
                                                    Stock Value
                                                </th>
                                                <th className="text-muted-foreground px-4 py-3 text-right text-xs font-semibold uppercase">
                                                    Exp. Profit
                                                </th>
                                                <th className="text-muted-foreground px-4 py-3 text-left text-xs font-semibold uppercase">
                                                    Received
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-border divide-y">
                                            {storeAssets.items.slice(0, 10).map((item, i) => (
                                                <tr key={item.batch_id} className="hover:bg-muted/40">
                                                    <td className="text-muted-foreground px-4 py-3">{i + 1}</td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium">{item.name}</div>
                                                        <div className="text-muted-foreground font-mono text-xs">{item.batch_code}</div>
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono text-xs">{money(item.unit_cost_afn)}</td>
                                                    <td className="px-4 py-3 text-right font-mono text-xs text-green-700">
                                                        {money(item.sale_price_afn)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono">
                                                        <span className="font-semibold">{item.remaining}</span>
                                                        <span className="text-muted-foreground text-xs"> / {item.quantity}</span>
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono font-semibold">
                                                        {money(item.remaining_cost_afn)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono font-semibold text-emerald-700">
                                                        {money(item.expected_profit_afn)}
                                                    </td>
                                                    <td className="text-muted-foreground px-4 py-3 text-xs">{item.received_date || '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>

                                    {storeAssets.items.length > 10 && (
                                        <div className="border-t px-4 py-3 text-center">
                                            <Link href="/products?owner=store" className="text-xs font-semibold text-blue-600 hover:underline">
                                                + {storeAssets.items.length - 10} more items →
                                            </Link>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* ═══════════ Customer Dues ═══════════ */}
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
                                            <td className="py-2 text-right font-mono">
                                                <div>{money(customer.current_due_afn)}</div>
                                                {customer.current_due_ledger !== undefined &&
                                                    reporting.converts_from_ledger &&
                                                    reporting.conversion_available && (
                                                        <div className="text-muted-foreground text-xs">
                                                            {formatMoney(customer.current_due_ledger, reporting.ledger)}
                                                        </div>
                                                    )}
                                            </td>
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
                            <p className="text-muted-foreground text-sm">
                                Completed sales and store profit for the last {days} days ({currencyCode})
                            </p>
                        </div>
                    </div>
                    <LazyInView minHeight={280}>
                        <Suspense fallback={<div className="bg-muted/40 h-[280px] animate-pulse rounded-xl" />}>
                            <SalesChart data={salesTrend} currencyCode={currencyCode} />
                        </Suspense>
                    </LazyInView>
                </div>

                {/* ═══════════ Charts Row: Brand + Payment ═══════════ */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="bg-card rounded-lg border p-6 shadow-sm">
                        <h3 className="mb-1 text-lg font-semibold">Sales by Brand</h3>
                        <p className="text-muted-foreground mb-4 text-sm">Reporting currency: {currencyCode}</p>
                        <LazyInView minHeight={280}>
                            <Suspense fallback={<div className="bg-muted/40 h-[280px] animate-pulse rounded-xl" />}>
                                <BrandPie data={brandSales} currencyCode={currencyCode} />
                            </Suspense>
                        </LazyInView>
                    </div>
                    <div className="bg-card rounded-lg border p-6 shadow-sm">
                        <h3 className="text-lg font-semibold">Payments & Due</h3>
                        <p className="text-muted-foreground mb-4 text-sm">
                            Collected by method, plus remaining due ({currencyCode})
                        </p>
                        <LazyInView minHeight={280}>
                            <Suspense fallback={<div className="bg-muted/40 h-[280px] animate-pulse rounded-xl" />}>
                                <PaymentPie data={paymentMethods} currencyCode={currencyCode} />
                            </Suspense>
                        </LazyInView>
                    </div>
                </div>

                {/* ═══════════ Branch Bar Chart ═══════════ */}
                <div className="bg-card rounded-lg border p-6 shadow-sm">
                    <h3 className="mb-1 text-lg font-semibold">Branch Performance</h3>
                    <p className="text-muted-foreground mb-4 text-sm">Reporting currency: {currencyCode}</p>
                    <LazyInView minHeight={280}>
                        <Suspense fallback={<div className="bg-muted/40 h-[280px] animate-pulse rounded-xl" />}>
                            <BranchBar data={branchStats} currencyCode={currencyCode} />
                        </Suspense>
                    </LazyInView>
                </div>

                <LowStock items={lowStockItems} />

                {/* ═══════════ Top Products ═══════════ */}
                <div className="bg-card overflow-hidden rounded-lg border shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h3 className="text-lg font-semibold">Top Selling Products</h3>
                        <p className="text-muted-foreground text-sm">Top 10 in last {days} days ({currencyCode})</p>
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
                                            <td className="px-6 py-3 text-right font-mono font-semibold">{money(p.total_sales)}</td>
                                            <td className="px-6 py-3 text-right font-mono">{money(p.total_profit)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* ═══════════ Recent Activity ═══════════ */}
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
                            <RecentSales sales={recentSales} embedded currency={currency} />
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
                                        <Link
                                            key={item.id}
                                            href={item.url}
                                            className="hover:bg-muted/50 flex items-start justify-between gap-4 px-6 py-3"
                                        >
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
