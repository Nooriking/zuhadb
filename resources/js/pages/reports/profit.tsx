// resources/js/pages/reports/profit.tsx

import { BackButton } from '@/components/form-page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    BarChart3,
    Calendar,
    CircleDollarSign,
    DollarSign,
    Download,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    Filter,
    Receipt,
    Store,
    TrendingDown,
    TrendingUp,
    User,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

/* ═══════════════════════════════════════════════════════
 |  Types
 ═══════════════════════════════════════════════════════ */

interface Summary {
    from: string;
    to: string;
    total_sales: number;
    total_cost: number;
    total_profit: number;
    store_profit: number;
    investor_profit: number;
    total_expenses: number;
    net_profit: number;
    total_quantity: number;
    total_orders: number;
}

interface BreakdownRow {
    date: string;
    orders: number;
    quantity: number;
    sales: number;
    cost: number;
    store_profit: number;
    investor_profit: number;
    total_profit: number;
}

interface PerInvestorRow {
    investor_id: number | null;
    investor_name: string;
    orders: number;
    quantity: number;
    sales: number;
    investor_profit: number;
    store_profit: number;
}

interface Investor {
    id: number;
    full_name: string;
}

interface InvestorStats {
    total_investors: number;
    active_investors: number;
    period: { from: string; to: string };
}

interface ExchangeRate {
    month: string;
    avg_rate: number;
    min_rate: number;
    max_rate: number;
    days: number;
}

interface ExpenseCategory {
    category: string;
    count: number;
    total: number;
}

interface ExpenseDaily {
    date: string;
    total: number;
}

interface ExpenseMonthly {
    month: string;
    total: number;
}

interface ExpensesData {
    total: number;
    by_category: ExpenseCategory[];
    daily: ExpenseDaily[];
    monthly: ExpenseMonthly[];
}

interface Props {
    summary: Summary;
    breakdown: BreakdownRow[];
    perInvestor: PerInvestorRow[];
    investors: Investor[];
    investorStats: InvestorStats;
    exchangeRates: ExchangeRate[];
    expenses: ExpensesData;
    filters: {
        from: string;
        to: string;
        group_by: 'daily' | 'monthly';
        investor_id: number | null;
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

const fmtRate = (v: number): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0);

const COLORS = {
    investor: '#3b82f6',
    store: '#10b981',
    sales: '#6366f1',
    expenses: '#ef4444',
    net: '#8b5cf6',
    profit: '#10b981',
};

const PIE_COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
    { title: 'Profit Report', href: '/reports/profit' },
];

/* ═══════════════════════════════════════════════════════
 |  Component
 ═══════════════════════════════════════════════════════ */

export default function ProfitReport({ summary, breakdown, perInvestor, investors, investorStats, exchangeRates, expenses, filters }: Props) {
    const [form, setForm] = useState({
        from: filters.from,
        to: filters.to,
        group_by: filters.group_by,
        investor_id: filters.investor_id ? String(filters.investor_id) : 'all',
    });

    /* ═══════════ ✅ محافظت‌های امنیتی ═══════════ */

    const safeExpenses: ExpensesData = expenses ?? {
        total: 0,
        by_category: [],
        daily: [],
        monthly: [],
    };

    const safeBreakdown = breakdown ?? [];
    const safePerInvestor = perInvestor ?? [];
    const safeExchangeRates = exchangeRates ?? [];
    const safeInvestors = investors ?? [];
    const safeInvestorStats = investorStats ?? {
        total_investors: 0,
        active_investors: 0,
        period: { from: '', to: '' },
    };

    /* ═══════════ Actions ═══════════ */

    const applyFilters = () => {
        router.get(
            '/reports/profit',
            {
                from: form.from,
                to: form.to,
                group_by: form.group_by,
                investor_id: form.investor_id === 'all' ? undefined : form.investor_id,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const downloadFile = (type: 'full' | 'summary' | 'breakdown' | 'per-investor' | 'exchange-rates') => {
        const params = new URLSearchParams({
            from: form.from,
            to: form.to,
            group_by: form.group_by,
        });
        const routes = {
            full: '/export',
            summary: '/export-summary',
            breakdown: '/export-breakdown',
            'per-investor': '/export-per-investor',
            'exchange-rates': '/export-exchange-rates',
        };
        window.location.href = `/reports/profit${routes[type]}?${params.toString()}`;
    };

    /* ✅ PDF download */
    const downloadPdf = () => {
        const params = new URLSearchParams({
            from: form.from,
            to: form.to,
            group_by: form.group_by,
        });
        window.location.href = `/reports/profit/pdf?${params.toString()}`;
    };

    const setQuickRange = (days: number) => {
        const to = new Date().toISOString().slice(0, 10);
        const from = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
        setForm((prev) => ({ ...prev, from, to }));
    };

    const setMonthRange = (monthsAgo: number) => {
        const now = new Date();
        const target = new Date(now.getFullYear(), now.getMonth() - monthsAgo, 1);
        const from = target.toISOString().slice(0, 10);
        const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0);
        const to = lastDay.toISOString().slice(0, 10);
        setForm((prev) => ({ ...prev, from, to }));
    };

    /* ═══════════ Chart Data Preparation ═══════════ */

    const chartData = [...safeBreakdown].reverse().map((row) => {
        let dayExpenses = 0;
        if (form.group_by === 'monthly') {
            dayExpenses = (safeExpenses.monthly ?? []).find((e) => e.month === row.date)?.total ?? 0;
        } else {
            dayExpenses = (safeExpenses.daily ?? []).find((e) => e.date === row.date)?.total ?? 0;
        }
        return {
            date: row.date,
            sales: row.sales,
            investor_profit: row.investor_profit,
            store_profit: row.store_profit,
            gross_profit: row.total_profit,
            expenses: dayExpenses,
            net_profit: row.total_profit - dayExpenses,
        };
    });

    const expensePieData = (safeExpenses.by_category ?? []).map((e) => ({
        name: e.category,
        value: e.total,
    }));

    const latestRate = safeExchangeRates.length > 0 ? safeExchangeRates[0] : null;

    /* ═══════════ Query string برای لینک‌ها ═══════════ */
    const queryString = `?from=${form.from}&to=${form.to}&group_by=${form.group_by}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profit Report" />

            <div className="flex flex-col gap-6 p-6">
                {/* ═══════════ Header ═══════════ */}
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/reports" label="Back" />
                    <div className="flex items-center gap-3">
                        <BarChart3 className="text-primary h-6 w-6" />
                        <h1 className="text-2xl font-bold tracking-tight">Profit Report</h1>
                    </div>
                </div>

                {/* ═══════════ Download Menu ═══════════ */}
                <Card className="border-2 border-emerald-200 bg-emerald-50/30 dark:border-emerald-900 dark:bg-emerald-950/20">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileSpreadsheet className="h-4 w-4 text-emerald-600" />
                            Download Reports
                        </CardTitle>
                        <CardDescription>Choose format and section</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* ✅ PDF Button — Big & prominent */}
                        <Button
                            onClick={downloadPdf}
                            className="h-auto w-full justify-start gap-3 bg-gradient-to-r from-red-600 to-rose-600 py-4 hover:from-red-700 hover:to-rose-700"
                            variant="default"
                        >
                            <FileText className="h-7 w-7 shrink-0" />
                            <div className="text-left">
                                <div className="text-base font-bold">Download PDF Report</div>
                                <div className="text-xs opacity-90">Full styled report with tables & KPIs — best for printing</div>
                            </div>
                        </Button>

                        {/* ✅ CSV Buttons */}
                        <div>
                            <p className="text-muted-foreground mb-2 text-xs font-semibold">CSV Exports (Excel):</p>
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
                                {[
                                    { type: 'full', title: 'Full Report', sub: 'All sections' },
                                    { type: 'summary', title: 'Summary', sub: 'Just totals' },
                                    { type: 'breakdown', title: form.group_by === 'monthly' ? 'Monthly' : 'Daily', sub: 'Breakdown' },
                                    { type: 'per-investor', title: 'Per Investor', sub: 'Each investor' },
                                    { type: 'exchange-rates', title: 'Exchange Rates', sub: 'Monthly USD' },
                                ].map((btn) => (
                                    <Button
                                        key={btn.type}
                                        onClick={() => downloadFile(btn.type as any)}
                                        className="h-auto justify-start gap-2 py-3"
                                        variant="outline"
                                    >
                                        <Download className="h-4 w-4 shrink-0" />
                                        <div className="text-left">
                                            <div className="text-sm font-semibold">{btn.title}</div>
                                            <div className="text-[10px] opacity-70">{btn.sub}</div>
                                        </div>
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

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
                                { label: 'Today', days: 0 },
                                { label: '7 days', days: 7 },
                                { label: '30 days', days: 30 },
                                { label: '90 days', days: 90 },
                                { label: '365 days', days: 365 },
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
                            <span className="text-muted-foreground ml-4 self-center text-xs font-semibold">Month:</span>
                            {[0, 1, 2].map((m) => (
                                <Button key={m} type="button" size="sm" variant="outline" onClick={() => setMonthRange(m)} className="h-7 text-xs">
                                    {m === 0 ? 'This' : m === 1 ? 'Last' : `${m} ago`}
                                </Button>
                            ))}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div className="space-y-2">
                                <Label>From</Label>
                                <Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} />
                            </div>
                            <div className="space-y-2">
                                <Label>To</Label>
                                <Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} />
                            </div>
                            <div className="space-y-2">
                                <Label>Group by</Label>
                                <Select value={form.group_by} onValueChange={(v) => setForm({ ...form, group_by: v as 'daily' | 'monthly' })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="daily">Daily</SelectItem>
                                        <SelectItem value="monthly">Monthly</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Investor</Label>
                                <Select value={form.investor_id} onValueChange={(v) => setForm({ ...form, investor_id: v })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All investors</SelectItem>
                                        {safeInvestors.map((inv) => (
                                            <SelectItem key={inv.id} value={String(inv.id)}>
                                                {inv.full_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <Button onClick={applyFilters} className="gap-2">
                            <Filter className="h-4 w-4" /> Apply filters
                        </Button>
                    </CardContent>
                </Card>

                {/* ═══════════ Top Stats ═══════════ */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card className="border-l-4 border-l-blue-500">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1">
                                <Users className="h-3.5 w-3.5" /> Total Investors
                            </CardDescription>
                            <CardTitle className="font-mono text-3xl">{safeInvestorStats.total_investors}</CardTitle>
                            <p className="text-muted-foreground text-xs">Registered in system</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-emerald-500">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1">
                                <Users className="h-3.5 w-3.5" /> Active in Period
                            </CardDescription>
                            <CardTitle className="font-mono text-3xl text-emerald-700">{safeInvestorStats.active_investors}</CardTitle>
                            <p className="text-muted-foreground text-xs">Sold goods during this period</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-amber-500">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1">
                                <CircleDollarSign className="h-3.5 w-3.5" /> Latest Exchange Rate
                            </CardDescription>
                            <CardTitle className="font-mono text-3xl text-amber-700">{latestRate ? fmtRate(latestRate.avg_rate) : '—'}</CardTitle>
                            <p className="text-muted-foreground text-xs">{latestRate ? `AFN/USD · avg for ${latestRate.month}` : 'No rates'}</p>
                        </CardHeader>
                    </Card>
                </div>

                {/* ✅ Store Detail Card */}
                <Card className="border-2 border-purple-300 bg-gradient-to-r from-purple-50 via-violet-50 to-purple-50 dark:border-purple-800 dark:from-purple-950/30 dark:via-violet-950/30 dark:to-purple-950/30">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex flex-wrap items-center justify-between gap-3 text-base">
                            <div className="flex items-center gap-2">
                                <Store className="h-5 w-5 text-purple-700 dark:text-purple-400" />
                                <span className="text-purple-800 dark:text-purple-300">🏪 Store-Owned Analysis</span>
                            </div>
                            <Link href={`/reports/profit/store${queryString}`}>
                                <Button variant="default" className="gap-2 bg-purple-600 hover:bg-purple-700">
                                    Open Store Report <ExternalLink className="h-3.5 w-3.5" />
                                </Button>
                            </Link>
                        </CardTitle>
                        <CardDescription>
                            View the complete breakdown of profit from goods that are 100% owned by the store (without investor capital)
                        </CardDescription>
                    </CardHeader>
                </Card>

                {/* ═══════════ Summary Cards ═══════════ */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card className="border-l-4 border-l-slate-500">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1">
                                <DollarSign className="h-3.5 w-3.5" /> Total Sales
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl">{fmt(summary.total_sales)}</CardTitle>
                            <p className="text-muted-foreground text-xs">
                                {summary.total_orders} orders · {summary.total_quantity} pcs
                            </p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-blue-500 bg-blue-50/30 dark:bg-blue-950/20">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1 text-blue-800 dark:text-blue-400">
                                <Wallet className="h-3.5 w-3.5" /> Investor Profit
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl text-blue-800 dark:text-blue-400">{fmt(summary.investor_profit)}</CardTitle>
                            <p className="text-xs text-blue-700 dark:text-blue-500">AFN</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-emerald-500 bg-emerald-50/30 dark:bg-emerald-950/20">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1 text-emerald-800 dark:text-emerald-400">
                                <Store className="h-3.5 w-3.5" /> Store Profit (Gross)
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl text-emerald-800 dark:text-emerald-400">{fmt(summary.store_profit)}</CardTitle>
                            <p className="text-xs text-emerald-700 dark:text-emerald-500">AFN</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-red-500 bg-red-50/30 dark:bg-red-950/20">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1 text-red-800 dark:text-red-400">
                                <TrendingDown className="h-3.5 w-3.5" /> Total Expenses
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl text-red-700 dark:text-red-400">{fmt(summary.total_expenses)}</CardTitle>
                            <p className="text-xs text-red-700 dark:text-red-500">{(safeExpenses.by_category ?? []).length} categories</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-violet-500 bg-violet-50/30 dark:bg-violet-950/20">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1 text-violet-800 dark:text-violet-400">
                                <TrendingUp className="h-3.5 w-3.5" /> Net Profit
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl text-violet-700 dark:text-violet-400">{fmt(summary.net_profit)}</CardTitle>
                            <p className="text-xs text-violet-700 dark:text-violet-500">Gross Profit − Expenses</p>
                        </CardHeader>
                    </Card>

                    <Card className="border-l-4 border-l-slate-500">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1">
                                <TrendingUp className="h-3.5 w-3.5" /> Gross Profit
                            </CardDescription>
                            <CardTitle className="font-mono text-2xl">{fmt(summary.total_profit)}</CardTitle>
                            <p className="text-muted-foreground text-xs">Investor + Store</p>
                        </CardHeader>
                    </Card>
                </div>

                {/* ═══════════ GRAPH 1: Trend ═══════════ */}
                {chartData.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BarChart3 className="h-5 w-5" />
                                Trend: Sales, Profit & Expenses
                            </CardTitle>
                            <CardDescription>
                                {form.group_by === 'monthly' ? 'Monthly' : 'Daily'} view of sales, profits, and expenses
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={340}>
                                <AreaChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="colorSales" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor={COLORS.sales} stopOpacity={0.35} />
                                            <stop offset="95%" stopColor={COLORS.sales} stopOpacity={0} />
                                        </linearGradient>
                                        <linearGradient id="colorProfit" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor={COLORS.profit} stopOpacity={0.35} />
                                            <stop offset="95%" stopColor={COLORS.profit} stopOpacity={0} />
                                        </linearGradient>
                                        <linearGradient id="colorNet" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor={COLORS.net} stopOpacity={0.35} />
                                            <stop offset="95%" stopColor={COLORS.net} stopOpacity={0} />
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
                                        dataKey="sales"
                                        name="Sales"
                                        stroke={COLORS.sales}
                                        fill="url(#colorSales)"
                                        strokeWidth={2}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="gross_profit"
                                        name="Gross Profit"
                                        stroke={COLORS.profit}
                                        fill="url(#colorProfit)"
                                        strokeWidth={2}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="expenses"
                                        name="Expenses"
                                        stroke={COLORS.expenses}
                                        fill="none"
                                        strokeWidth={2}
                                        strokeDasharray="5 5"
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="net_profit"
                                        name="Net Profit"
                                        stroke={COLORS.net}
                                        fill="url(#colorNet)"
                                        strokeWidth={2}
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                )}

                {/* ═══════════ GRAPH 2: Profit Split + Expenses Pie ═══════════ */}
                {chartData.length > 0 && (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Wallet className="h-5 w-5" />
                                    Investor vs Store Profit
                                </CardTitle>
                                <CardDescription>Stacked view of profit split over time</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ResponsiveContainer width="100%" height={300}>
                                    <BarChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                                        <YAxis tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                                        <Tooltip
                                            formatter={(v) => `${fmt(Number(v))} AFN`}
                                            contentStyle={{ borderRadius: 12, border: '1px solid #e5e7eb' }}
                                        />
                                        <Legend />
                                        <Bar
                                            dataKey="investor_profit"
                                            name="Investor Profit"
                                            stackId="a"
                                            fill={COLORS.investor}
                                            radius={[0, 0, 0, 0]}
                                        />
                                        <Bar dataKey="store_profit" name="Store Profit" stackId="a" fill={COLORS.store} radius={[6, 6, 0, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Receipt className="h-5 w-5 text-red-600" />
                                    Expenses by Category
                                </CardTitle>
                                <CardDescription>Where the money goes</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {expensePieData.length === 0 ? (
                                    <div className="text-muted-foreground py-12 text-center text-sm">No expenses in this period</div>
                                ) : (
                                    <ResponsiveContainer width="100%" height={300}>
                                        <PieChart>
                                            <Pie
                                                data={expensePieData}
                                                dataKey="value"
                                                nameKey="name"
                                                cx="50%"
                                                cy="50%"
                                                outerRadius={100}
                                                label={(entry: any) => `${entry.name}: ${fmtShort(Number(entry.value))}`}
                                            >
                                                {expensePieData.map((_, i) => (
                                                    <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                                                ))}
                                            </Pie>
                                            <Tooltip
                                                formatter={(v) => `${fmt(Number(v))} AFN`}
                                                contentStyle={{ borderRadius: 12, border: '1px solid #e5e7eb' }}
                                            />
                                        </PieChart>
                                    </ResponsiveContainer>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* ═══════════ GRAPH 3: Exchange Rate Trend ═══════════ */}
                {safeExchangeRates.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CircleDollarSign className="h-5 w-5 text-amber-600" />
                                Exchange Rate Trend (USD → AFN)
                            </CardTitle>
                            <CardDescription>Average, min, and max dollar rate per month</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={280}>
                                <LineChart data={[...safeExchangeRates].reverse()} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                                    <YAxis tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                                    <Tooltip
                                        formatter={(v) => `${fmtRate(Number(v))} AFN`}
                                        contentStyle={{ borderRadius: 12, border: '1px solid #e5e7eb' }}
                                    />
                                    <Legend />
                                    <Line type="monotone" dataKey="avg_rate" name="Avg Rate" stroke="#f59e0b" strokeWidth={3} dot={{ r: 4 }} />
                                    <Line
                                        type="monotone"
                                        dataKey="min_rate"
                                        name="Min"
                                        stroke="#94a3b8"
                                        strokeWidth={1.5}
                                        strokeDasharray="3 3"
                                        dot={false}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="max_rate"
                                        name="Max"
                                        stroke="#ef4444"
                                        strokeWidth={1.5}
                                        strokeDasharray="3 3"
                                        dot={false}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                )}

                {/* ═══════════ Breakdown Table ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5" />
                            {form.group_by === 'monthly' ? 'Monthly' : 'Daily'} Breakdown ({safeBreakdown.length} rows)
                        </CardTitle>
                        <CardDescription>
                            From {summary.from} to {summary.to}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {safeBreakdown.length === 0 ? (
                            <div className="text-muted-foreground py-8 text-center text-sm">No sales data for this period.</div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{form.group_by === 'monthly' ? 'Month' : 'Date'}</TableHead>
                                            <TableHead className="text-center">Orders</TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Sales</TableHead>
                                            <TableHead className="bg-blue-50 text-right text-blue-800 dark:bg-blue-950/30">💰 Investor</TableHead>
                                            <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">
                                                🏪 Store
                                            </TableHead>
                                            <TableHead className="text-right">Gross Profit</TableHead>
                                            <TableHead className="bg-red-50 text-right text-red-800 dark:bg-red-950/30">💸 Expenses</TableHead>
                                            <TableHead className="bg-violet-50 text-right text-violet-800 dark:bg-violet-950/30">
                                                📈 Net Profit
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {safeBreakdown.map((row) => {
                                            const dayExpenses =
                                                form.group_by === 'monthly'
                                                    ? ((safeExpenses.monthly ?? []).find((e) => e.month === row.date)?.total ?? 0)
                                                    : ((safeExpenses.daily ?? []).find((e) => e.date === row.date)?.total ?? 0);
                                            const netProfit = row.total_profit - dayExpenses;

                                            return (
                                                <TableRow key={row.date}>
                                                    <TableCell className="font-mono text-xs">{row.date}</TableCell>
                                                    <TableCell className="text-center font-mono text-xs">{row.orders}</TableCell>
                                                    <TableCell className="text-center font-mono text-xs">{row.quantity}</TableCell>
                                                    <TableCell className="text-right font-mono text-xs">{fmt(row.sales)}</TableCell>
                                                    <TableCell className="bg-blue-50 text-right font-mono text-xs font-semibold text-blue-800 dark:bg-blue-950/30 dark:text-blue-400">
                                                        {fmt(row.investor_profit)}
                                                    </TableCell>
                                                    <TableCell className="bg-emerald-50 text-right font-mono text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                        {fmt(row.store_profit)}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-xs font-bold">{fmt(row.total_profit)}</TableCell>
                                                    <TableCell className="bg-red-50 text-right font-mono text-xs font-semibold text-red-700 dark:bg-red-950/30 dark:text-red-400">
                                                        {dayExpenses > 0 ? fmt(dayExpenses) : '—'}
                                                    </TableCell>
                                                    <TableCell
                                                        className={`bg-violet-50 text-right font-mono text-xs font-bold dark:bg-violet-950/30 ${
                                                            netProfit >= 0 ? 'text-violet-700 dark:text-violet-400' : 'text-red-700'
                                                        }`}
                                                    >
                                                        {fmt(netProfit)}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ═══════════ Expenses by Category ═══════════ */}
                {(safeExpenses.by_category ?? []).length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Receipt className="h-5 w-5 text-red-600" />
                                Expenses by Category ({(safeExpenses.by_category ?? []).length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Category</TableHead>
                                            <TableHead className="text-center">Count</TableHead>
                                            <TableHead className="text-right">Total (AFN)</TableHead>
                                            <TableHead className="text-right">% of Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(safeExpenses.by_category ?? []).map((e) => (
                                            <TableRow key={e.category}>
                                                <TableCell className="font-medium">{e.category}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{e.count}</TableCell>
                                                <TableCell className="text-right font-mono text-sm font-bold text-red-700">{fmt(e.total)}</TableCell>
                                                <TableCell className="text-muted-foreground text-right font-mono text-xs">
                                                    {safeExpenses.total > 0 ? ((e.total / safeExpenses.total) * 100).toFixed(1) : '0'}%
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        <TableRow className="bg-red-50/50 dark:bg-red-950/20">
                                            <TableCell className="font-bold">TOTAL</TableCell>
                                            <TableCell className="text-center font-mono text-xs font-bold">
                                                {(safeExpenses.by_category ?? []).reduce((sum, e) => sum + e.count, 0)}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-sm font-bold text-red-700">
                                                {fmt(safeExpenses.total)}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs font-bold">100%</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* ═══════════ Per-Investor Breakdown ═══════════ */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <User className="h-5 w-5" /> Per-Investor Breakdown
                        </CardTitle>
                        <CardDescription>Click on any investor to see their full detail report</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {safePerInvestor.length === 0 ? (
                            <div className="text-muted-foreground py-8 text-center text-sm">No data.</div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>#</TableHead>
                                            <TableHead>Investor</TableHead>
                                            <TableHead className="text-center">Orders</TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Sales</TableHead>
                                            <TableHead className="bg-blue-50 text-right text-blue-800 dark:bg-blue-950/30">
                                                💰 Investor Profit
                                            </TableHead>
                                            <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">
                                                🏪 Store Profit
                                            </TableHead>
                                            <TableHead className="text-center">Detail</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {safePerInvestor.map((row, i) => (
                                            <TableRow key={`${row.investor_id ?? 'store'}-${i}`}>
                                                <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                                                <TableCell>
                                                    {row.investor_id ? (
                                                        <Link
                                                            href={`/reports/profit/investor/${row.investor_id}${queryString}`}
                                                            className="group flex items-center gap-2 font-medium text-blue-700 hover:underline dark:text-blue-400"
                                                        >
                                                            <User className="h-3.5 w-3.5" />
                                                            {row.investor_name}
                                                            <ExternalLink className="h-3 w-3 opacity-0 transition-opacity group-hover:opacity-100" />
                                                        </Link>
                                                    ) : (
                                                        <Link
                                                            href={`/reports/profit/store${queryString}`}
                                                            className="group flex items-center gap-2 font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                                                        >
                                                            <Store className="h-3.5 w-3.5" />
                                                            {row.investor_name}
                                                            <ExternalLink className="h-3 w-3 opacity-0 transition-opacity group-hover:opacity-100" />
                                                        </Link>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.orders}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">{row.quantity}</TableCell>
                                                <TableCell className="text-right font-mono text-xs">{fmt(row.sales)}</TableCell>
                                                <TableCell className="bg-blue-50 text-right font-mono text-xs font-semibold text-blue-800 dark:bg-blue-950/30 dark:text-blue-400">
                                                    {row.investor_profit > 0 ? fmt(row.investor_profit) : '—'}
                                                </TableCell>
                                                <TableCell className="bg-emerald-50 text-right font-mono text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                    {fmt(row.store_profit)}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {row.investor_id ? (
                                                        <Link href={`/reports/profit/investor/${row.investor_id}${queryString}`}>
                                                            <Button size="sm" variant="outline" className="gap-1 text-xs">
                                                                View <ExternalLink className="h-3 w-3" />
                                                            </Button>
                                                        </Link>
                                                    ) : (
                                                        <Link href={`/reports/profit/store${queryString}`}>
                                                            <Button size="sm" variant="outline" className="gap-1 text-xs">
                                                                View <ExternalLink className="h-3 w-3" />
                                                            </Button>
                                                        </Link>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
