// resources/js/pages/reports/investor-detail.tsx

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
import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, Calendar, CircleDollarSign, DollarSign, Filter, Package, TrendingUp, User, Wallet } from 'lucide-react';
import { useState } from 'react';
import { Area, AreaChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface InvestorInfo {
    id: number;
    full_name: string;
    phone: string | null;
    email: string | null;
    status: string;
}

interface CapitalInfo {
    total_invested: number;
    capital_balance: number;
    profit_balance: number;
    total_profit: number;
    total_withdrawn: number;
}

interface PurchaseRow {
    product_name: string;
    batch_code: string;
    orders: number;
    quantity: number;
    sales: number;
    cost: number;
    investor_profit: number;
    store_profit: number;
}

interface TrendRow {
    period?: string;
    month?: string;
    date?: string;
    year?: number;
    orders: number;
    quantity: number;
    sales: number;
    investor_profit: number;
    store_profit: number;
}

interface Totals {
    orders: number;
    quantity: number;
    sales: number;
    investor_profit: number;
    store_profit: number;
}

interface Props {
    investor: InvestorInfo;
    capitalInfo: CapitalInfo;
    purchases: PurchaseRow[];
    monthlyTrend: TrendRow[];
    dailyTrend: TrendRow[];
    yearlyTrend: TrendRow[];
    totals: Totals;
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

const COLORS = {
    investor: '#3b82f6',
    store: '#10b981',
    sales: '#6366f1',
    profit: '#10b981',
};

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function InvestorDetail({ investor, capitalInfo, purchases, monthlyTrend, dailyTrend, yearlyTrend, totals, filters }: Props) {
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
        { title: investor.full_name, href: `/reports/profit/investor/${investor.id}` },
    ];

    const applyFilters = () => {
        router.get(
            `/reports/profit/investor/${investor.id}`,
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

    // انتخاب دیتای نمودار بر اساس تب فعال
    const activeTrend = activeTab === 'yearly' ? yearlyTrend : activeTab === 'daily' ? dailyTrend : monthlyTrend;

    const chartData = [...activeTrend].reverse().map((row) => ({
        date: row.period ?? row.month ?? row.date ?? String(row.year ?? ''),
        sales: row.sales,
        investor_profit: row.investor_profit,
        store_profit: row.store_profit,
    }));

    // محاسبه ارزش کل پول این سرمایه‌گذار
    const totalValue = capitalInfo.capital_balance + capitalInfo.profit_balance;
    const hasActiveCapital = capitalInfo.capital_balance > 0;
    const hasProfit = capitalInfo.profit_balance > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${investor.full_name} — Investor Report`} />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/reports/profit" label="Back to Report" />
                    <div className="flex items-center gap-3">
                        <User className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">{investor.full_name}</h1>
                        <Badge
                            variant={investor.status === 'active' ? 'default' : 'secondary'}
                            className={investor.status === 'active' ? 'bg-emerald-600' : ''}
                        >
                            {investor.status}
                        </Badge>
                    </div>
                    {investor.phone && (
                        <span className="text-muted-foreground font-mono text-sm" dir="ltr">
                            {investor.phone}
                        </span>
                    )}
                    {investor.email && (
                        <span className="text-muted-foreground text-sm" dir="ltr">
                            {investor.email}
                        </span>
                    )}
                    <Link href={`/investors/${investor.id}`} className="ml-auto">
                        <Button variant="outline" className="gap-2">
                            Open Investor Page
                        </Button>
                    </Link>
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

                {/* ═══════════ Capital Info ═══════════ */}
                <div>
                    <h2 className="mb-3 text-sm font-semibold tracking-wide uppercase">💰 Capital Overview</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card className="border-l-4 border-l-indigo-500">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1">
                                    <Wallet className="h-3.5 w-3.5" /> Total Invested
                                </CardDescription>
                                <CardTitle className="font-mono text-2xl text-indigo-700 dark:text-indigo-400">
                                    {fmt(capitalInfo.total_invested)}
                                </CardTitle>
                                <p className="text-muted-foreground text-xs">Money given by investor</p>
                            </CardHeader>
                        </Card>

                        <Card className="border-l-4 border-l-blue-500 bg-blue-50/30 dark:bg-blue-950/20">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1 text-blue-800 dark:text-blue-400">
                                    <CircleDollarSign className="h-3.5 w-3.5" /> Capital Balance
                                </CardDescription>
                                <CardTitle className="font-mono text-2xl text-blue-800 dark:text-blue-400">
                                    {fmt(capitalInfo.capital_balance)}
                                </CardTitle>
                                <p className="text-xs text-blue-700 dark:text-blue-500">{hasActiveCapital ? 'Available for use' : 'Fully used'}</p>
                            </CardHeader>
                        </Card>

                        <Card className="border-l-4 border-l-emerald-500 bg-emerald-50/30 dark:bg-emerald-950/20">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1 text-emerald-800 dark:text-emerald-400">
                                    <TrendingUp className="h-3.5 w-3.5" /> Profit Balance
                                </CardDescription>
                                <CardTitle className="font-mono text-2xl text-emerald-800 dark:text-emerald-400">
                                    {fmt(capitalInfo.profit_balance)}
                                </CardTitle>
                                <p className="text-xs text-emerald-700 dark:text-emerald-500">
                                    {hasProfit ? 'Available to withdraw' : 'No profit yet'}
                                </p>
                            </CardHeader>
                        </Card>

                        <Card className="border-l-4 border-l-violet-500 bg-gradient-to-br from-violet-50 to-purple-50 dark:from-violet-950/30 dark:to-purple-950/30">
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-1 text-violet-800 dark:text-violet-400">
                                    <DollarSign className="h-3.5 w-3.5" /> Total Value
                                </CardDescription>
                                <CardTitle className="font-mono text-2xl text-violet-700 dark:text-violet-400">{fmt(totalValue)}</CardTitle>
                                <p className="text-xs text-violet-700 dark:text-violet-500">Capital + Profit balance</p>
                            </CardHeader>
                        </Card>
                    </div>
                </div>

                {/* ═══════════ Lifetime Profit Info ═══════════ */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card className="border-l-4 border-l-emerald-500">
                        <CardHeader className="pb-2">
                            <CardDescription>Total Profit Earned (Lifetime)</CardDescription>
                            <CardTitle className="font-mono text-2xl text-emerald-700">{fmt(capitalInfo.total_profit)}</CardTitle>
                            <p className="text-muted-foreground text-xs">AFN</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-rose-500">
                        <CardHeader className="pb-2">
                            <CardDescription>Total Withdrawn</CardDescription>
                            <CardTitle className="font-mono text-2xl text-rose-700">{fmt(capitalInfo.total_withdrawn)}</CardTitle>
                            <p className="text-muted-foreground text-xs">AFN</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-amber-500">
                        <CardHeader className="pb-2">
                            <CardDescription>Period Profit (Selected Range)</CardDescription>
                            <CardTitle className="font-mono text-2xl text-amber-700">{fmt(totals.investor_profit)}</CardTitle>
                            <p className="text-muted-foreground text-xs">
                                {totals.orders} orders · {totals.quantity} pcs
                            </p>
                        </CardHeader>
                    </Card>
                </div>

                {/* ═══════════ Period Summary ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <BarChart3 className="h-5 w-5" />
                            Period Summary: {filters.from} → {filters.to}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div className="rounded-lg border bg-slate-50 p-4 dark:bg-slate-900/50">
                                <p className="text-muted-foreground text-xs uppercase">Total Sales</p>
                                <p className="mt-1 font-mono text-xl font-bold">{fmt(totals.sales)}</p>
                                <p className="text-muted-foreground text-xs">AFN</p>
                            </div>
                            <div className="rounded-lg border bg-blue-50 p-4 dark:bg-blue-950/30">
                                <p className="text-xs text-blue-800 uppercase dark:text-blue-400">Investor Profit</p>
                                <p className="mt-1 font-mono text-xl font-bold text-blue-800 dark:text-blue-400">{fmt(totals.investor_profit)}</p>
                                <p className="text-xs text-blue-700 dark:text-blue-500">AFN</p>
                            </div>
                            <div className="rounded-lg border bg-emerald-50 p-4 dark:bg-emerald-950/30">
                                <p className="text-xs text-emerald-800 uppercase dark:text-emerald-400">Store Profit (from this investor)</p>
                                <p className="mt-1 font-mono text-xl font-bold text-emerald-800 dark:text-emerald-400">{fmt(totals.store_profit)}</p>
                                <p className="text-xs text-emerald-700 dark:text-emerald-500">AFN</p>
                            </div>
                            <div className="rounded-lg border bg-violet-50 p-4 dark:bg-violet-950/30">
                                <p className="text-xs text-violet-800 uppercase dark:text-violet-400">Total Profit</p>
                                <p className="mt-1 font-mono text-xl font-bold text-violet-800 dark:text-violet-400">
                                    {fmt(totals.investor_profit + totals.store_profit)}
                                </p>
                                <p className="text-xs text-violet-700 dark:text-violet-500">AFN</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ═══════════ Trend Chart ═══════════ */}
                {chartData.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp className="h-5 w-5" />
                                {activeTab === 'yearly' ? 'Yearly' : activeTab === 'daily' ? 'Daily' : 'Monthly'} Trend
                            </CardTitle>
                            <CardDescription>Sales & profit over time</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={320}>
                                <AreaChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="colorInvestor" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor={COLORS.investor} stopOpacity={0.35} />
                                            <stop offset="95%" stopColor={COLORS.investor} stopOpacity={0} />
                                        </linearGradient>
                                        <linearGradient id="colorStore" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor={COLORS.store} stopOpacity={0.35} />
                                            <stop offset="95%" stopColor={COLORS.store} stopOpacity={0} />
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
                                    <Area
                                        type="monotone"
                                        dataKey="investor_profit"
                                        name="Investor Profit"
                                        stroke={COLORS.investor}
                                        fill="url(#colorInvestor)"
                                        strokeWidth={2}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="store_profit"
                                        name="Store Profit"
                                        stroke={COLORS.store}
                                        fill="url(#colorStore)"
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
                            Products Sold (from this investor's capital)
                        </CardTitle>
                        <CardDescription>Each product line with its profit contribution</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {purchases.length === 0 ? (
                            <div className="text-muted-foreground py-8 text-center text-sm">
                                No sales from this investor's goods in the selected period.
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>#</TableHead>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="font-mono text-[10px]">Batch</TableHead>
                                            <TableHead className="text-center">Orders</TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Sales</TableHead>
                                            <TableHead className="text-right">Cost</TableHead>
                                            <TableHead className="bg-blue-50 text-right text-blue-800 dark:bg-blue-950/30">💰 Investor</TableHead>
                                            <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">
                                                🏪 Store
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {purchases.map((row, i) => (
                                            <TableRow key={`${row.product_name}-${row.batch_code}-${i}`}>
                                                <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                                                <TableCell className="font-medium">{row.product_name}</TableCell>
                                                <TableCell className="text-muted-foreground font-mono text-[10px]">{row.batch_code}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.orders}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.quantity}</TableCell>
                                                <TableCell className="text-right font-mono text-xs">{fmt(row.sales)}</TableCell>
                                                <TableCell className="text-muted-foreground text-right font-mono text-xs">{fmt(row.cost)}</TableCell>
                                                <TableCell className="bg-blue-50 text-right font-mono text-xs font-semibold text-blue-800 dark:bg-blue-950/30 dark:text-blue-400">
                                                    {fmt(row.investor_profit)}
                                                </TableCell>
                                                <TableCell className="bg-emerald-50 text-right font-mono text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                    {fmt(row.store_profit)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        {/* Total row */}
                                        <TableRow className="bg-slate-100 font-bold dark:bg-slate-800">
                                            <TableCell colSpan={3}>TOTAL</TableCell>
                                            <TableCell className="text-center font-mono">{totals.orders}</TableCell>
                                            <TableCell className="text-center font-mono">{totals.quantity}</TableCell>
                                            <TableCell className="text-right font-mono">{fmt(totals.sales)}</TableCell>
                                            <TableCell />
                                            <TableCell className="bg-blue-100 text-right font-mono dark:bg-blue-900/50">
                                                {fmt(totals.investor_profit)}
                                            </TableCell>
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
                                            <TableHead>{activeTab === 'yearly' ? 'Year' : 'Period'}</TableHead>
                                            <TableHead className="text-center">Orders</TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Sales</TableHead>
                                            <TableHead className="bg-blue-50 text-right text-blue-800 dark:bg-blue-950/30">💰 Investor</TableHead>
                                            <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">
                                                🏪 Store
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {activeTrend.map((row, i) => (
                                            <TableRow key={row.period ?? row.month ?? row.date ?? row.year ?? i}>
                                                <TableCell className="font-mono text-xs font-semibold">
                                                    {row.period ?? row.month ?? row.date ?? row.year}
                                                </TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.orders}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.quantity}</TableCell>
                                                <TableCell className="text-right font-mono text-xs">{fmt(row.sales)}</TableCell>
                                                <TableCell className="bg-blue-50 text-right font-mono text-xs font-semibold text-blue-800 dark:bg-blue-950/30 dark:text-blue-400">
                                                    {fmt(row.investor_profit)}
                                                </TableCell>
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
