// resources/js/pages/reports/store-detail.tsx

import { BackButton } from '@/components/form-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Calendar, DollarSign, Filter, Package, Store, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { useState } from 'react';
import { Area, AreaChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface PurchaseRow {
    product_name: string;
    orders: number;
    quantity: number;
    sales: number;
    cost: number;
    store_profit: number;
}

interface TrendRow {
    period: string;
    orders: number;
    quantity: number;
    sales: number;
    store_profit: number;
}

interface Totals {
    orders: number;
    quantity: number;
    sales: number;
    store_profit: number;
}

interface Props {
    purchases: PurchaseRow[];
    monthlyTrend: TrendRow[];
    dailyTrend: TrendRow[];
    yearlyTrend: TrendRow[];
    totals: Totals;
    totalExpenses: number;
    filters: {
        from: string;
        to: string;
        group_by: 'daily' | 'monthly' | 'yearly';
    };
}

/* ═══════════════════════════════════════════════════════
 |  Helpers
 ═══════════════════════════════════════════════════════ */

const fmt = (v: number): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v || 0);

const fmtShort = (v: number): string => {
    if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000) return `${(v / 1_000).toFixed(1)}K`;
    return String(Math.round(v || 0));
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function StoreDetail({ purchases, monthlyTrend, dailyTrend, yearlyTrend, totals, totalExpenses, filters }: Props) {
    const [form, setForm] = useState({
        from: filters.from,
        to: filters.to,
        group_by: filters.group_by,
    });

    const [activeTab, setActiveTab] = useState<'monthly' | 'daily' | 'yearly'>(
        filters.group_by === 'yearly' ? 'yearly' : filters.group_by === 'daily' ? 'daily' : 'monthly',
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '/reports' },
        { title: 'Profit Report', href: '/reports/profit' },
        { title: 'Store Detail', href: '/reports/profit/store' },
    ];

    const applyFilters = () => {
        router.get(
            '/reports/profit/store',
            {
                from: form.from,
                to: form.to,
                group_by: activeTab,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const setQuickRange = (days: number) => {
        const to = new Date().toISOString().slice(0, 10);
        const from = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
        setForm((prev) => ({ ...prev, from, to }));
    };

    const activeTrend = activeTab === 'yearly' ? yearlyTrend : activeTab === 'daily' ? dailyTrend : monthlyTrend;

    const chartData = [...activeTrend].reverse().map((row) => ({
        date: row.period,
        sales: row.sales,
        store_profit: row.store_profit,
    }));

    const netProfit = totals.store_profit - totalExpenses;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Store Detail Report" />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/reports/profit" label="Back to Report" />
                    <div className="flex items-center gap-3">
                        <Store className="h-6 w-6 text-emerald-600" />
                        <h1 className="text-2xl font-bold tracking-tight">Store-Owned Analysis</h1>
                        <Badge className="bg-emerald-600">🏪 Store</Badge>
                    </div>
                </div>

                <div className="rounded-lg border-2 border-emerald-200 bg-emerald-50/40 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                    <p className="text-sm text-emerald-900 dark:text-emerald-300">
                        <strong>📊 About this report:</strong> Here you can see all goods that are <strong>100% owned by the store</strong> (bought
                        without investor capital). The full profit from these goods stays with the store.
                    </p>
                </div>

                {/* ═══════════ Filters ═══════════ */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="h-4 w-4" /> Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            <span className="text-muted-foreground self-center text-xs font-semibold">Quick:</span>
                            {[
                                { label: 'Last 30 days', days: 30 },
                                { label: 'Last 90 days', days: 90 },
                                { label: 'Last 365 days', days: 365 },
                                { label: 'Last 2 years', days: 730 },
                            ].map((r) => (
                                <Button
                                    key={r.label}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setQuickRange(r.days)}
                                    className="h-7 text-xs"
                                >
                                    {r.label}
                                </Button>
                            ))}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label>From</Label>
                                <Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} />
                            </div>
                            <div className="space-y-2">
                                <Label>To</Label>
                                <Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} />
                            </div>
                            <div className="space-y-2">
                                <Label>View by</Label>
                                <Select value={activeTab} onValueChange={(v) => setActiveTab(v as any)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="daily">Daily</SelectItem>
                                        <SelectItem value="monthly">Monthly</SelectItem>
                                        <SelectItem value="yearly">Yearly</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <Button onClick={applyFilters} className="gap-2">
                            <Filter className="h-4 w-4" /> Apply filters
                        </Button>
                    </CardContent>
                </Card>

                {/* ═══════════ KPI Cards ═══════════ */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-l-4 border-l-slate-500">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1">
                                <DollarSign className="h-3.5 w-3.5" /> Total Sales
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl">{fmt(totals.sales)}</CardTitle>
                            <p className="text-muted-foreground text-xs">
                                {totals.orders} orders · {totals.quantity} pcs
                            </p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-emerald-500 bg-emerald-50/30 dark:bg-emerald-950/20">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1 text-emerald-800 dark:text-emerald-400">
                                <TrendingUp className="h-3.5 w-3.5" /> Store Profit (Gross)
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl text-emerald-800 dark:text-emerald-400">{fmt(totals.store_profit)}</CardTitle>
                            <p className="text-xs text-emerald-700 dark:text-emerald-500">AFN</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-red-500 bg-red-50/30 dark:bg-red-950/20">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1 text-red-800 dark:text-red-400">
                                <TrendingDown className="h-3.5 w-3.5" /> Total Expenses
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl text-red-700 dark:text-red-400">{fmt(totalExpenses)}</CardTitle>
                            <p className="text-xs text-red-700 dark:text-red-500">AFN</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-violet-500 bg-violet-50/30 dark:bg-violet-950/20">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1 text-violet-800 dark:text-violet-400">
                                <Wallet className="h-3.5 w-3.5" /> Net Profit
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl text-violet-700 dark:text-violet-400">{fmt(netProfit)}</CardTitle>
                            <p className="text-xs text-violet-700 dark:text-violet-500">Gross − Expenses</p>
                        </CardHeader>
                    </Card>
                </div>

                {/* ═══════════ Trend Chart ═══════════ */}
                {chartData.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp className="h-5 w-5" />
                                {activeTab === 'yearly' ? 'Yearly' : activeTab === 'daily' ? 'Daily' : 'Monthly'} Trend
                            </CardTitle>
                            <CardDescription>Sales & store profit over time</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={320}>
                                <AreaChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="colorStoreProfit" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="#10b981" stopOpacity={0.35} />
                                            <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                                        </linearGradient>
                                        <linearGradient id="colorSales2" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="#6366f1" stopOpacity={0.25} />
                                            <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                                    <YAxis tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                                    <Tooltip
                                        formatter={(v) => `${fmt(Number(v))} AFN`}
                                        contentStyle={{ borderRadius: 12, border: '1px solid #e5e7eb' }}
                                    />
                                    <Legend />
                                    <Area type="monotone" dataKey="sales" name="Sales" stroke="#6366f1" fill="url(#colorSales2)" strokeWidth={2} />
                                    <Area
                                        type="monotone"
                                        dataKey="store_profit"
                                        name="Store Profit"
                                        stroke="#10b981"
                                        fill="url(#colorStoreProfit)"
                                        strokeWidth={2}
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                )}

                {/* ═══════════ Products Breakdown ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" />
                            Store-Owned Products Sold ({purchases.length})
                        </CardTitle>
                        <CardDescription>Each product's contribution to store profit</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {purchases.length === 0 ? (
                            <div className="text-muted-foreground py-8 text-center text-sm">No store-owned sales in the selected period.</div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>#</TableHead>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-center">Orders</TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Sales</TableHead>
                                            <TableHead className="text-right">Cost</TableHead>
                                            <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">
                                                🏪 Store Profit
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {purchases.map((row, i) => (
                                            <TableRow key={`${row.product_name}-${i}`}>
                                                <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                                                <TableCell className="font-medium">{row.product_name}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.orders}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.quantity}</TableCell>
                                                <TableCell className="text-right font-mono text-xs">{fmt(row.sales)}</TableCell>
                                                <TableCell className="text-muted-foreground text-right font-mono text-xs">{fmt(row.cost)}</TableCell>
                                                <TableCell className="bg-emerald-50 text-right font-mono text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                    {fmt(row.store_profit)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        <TableRow className="bg-slate-100 font-bold dark:bg-slate-800">
                                            <TableCell colSpan={2}>TOTAL</TableCell>
                                            <TableCell className="text-center font-mono">{totals.orders}</TableCell>
                                            <TableCell className="text-center font-mono">{totals.quantity}</TableCell>
                                            <TableCell className="text-right font-mono">{fmt(totals.sales)}</TableCell>
                                            <TableCell />
                                            <TableCell className="bg-emerald-100 text-right font-mono dark:bg-emerald-900/50">
                                                {fmt(totals.store_profit)}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ═══════════ Trend Table ═══════════ */}
                {activeTrend.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calendar className="h-5 w-5" />
                                {activeTab === 'yearly' ? 'Yearly' : activeTab === 'daily' ? 'Daily' : 'Monthly'} Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Period</TableHead>
                                            <TableHead className="text-center">Orders</TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Sales</TableHead>
                                            <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">
                                                🏪 Store Profit
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {activeTrend.map((row, i) => (
                                            <TableRow key={`${row.period}-${i}`}>
                                                <TableCell className="font-mono text-xs font-semibold">{row.period}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.orders}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.quantity}</TableCell>
                                                <TableCell className="text-right font-mono text-xs">{fmt(row.sales)}</TableCell>
                                                <TableCell className="bg-emerald-50 text-right font-mono text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                    {fmt(row.store_profit)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
