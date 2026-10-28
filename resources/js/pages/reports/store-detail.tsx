// resources/js/pages/reports/store-detail.tsx

import { BackButton } from '@/components/form-page';
import { EmptyState, KpiCard, Section } from '@/components/reports/report-blocks';
import { ReportPeriodFilter } from '@/components/reports/report-period-filter';
import {
    type BranchOption,
    CHART_COLORS,
    type MetricRow,
    type ReportFilters,
    filterParams,
    fmt,
    fmtPct,
    fmtShort,
    sum,
    tooltipStyle,
} from '@/components/reports/report-utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Calendar, DollarSign, FileText, Package, Store, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { useState } from 'react';
import { Area, AreaChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface PurchaseRow extends MetricRow {
    product_name: string;
}

interface TrendRow extends MetricRow {
    period: string;
}

interface Props {
    purchases: PurchaseRow[];
    monthlyTrend: TrendRow[];
    dailyTrend: TrendRow[];
    yearlyTrend: TrendRow[];
    totals: MetricRow;
    totalExpenses: number;
    branches: BranchOption[];
    filters: ReportFilters;
}

type View = 'daily' | 'monthly' | 'yearly';

export default function StoreDetail({ purchases, monthlyTrend, dailyTrend, yearlyTrend, totals: t, totalExpenses, branches, filters }: Props) {
    const [view, setView] = useState<View>(filters.group_by);
    const backHref = `/reports/profit?${filterParams(filters, { tab: 'investors' })}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Profit Report', href: backHref },
        { title: 'Store Detail', href: '/reports/profit/store' },
    ];

    const trend = view === 'yearly' ? yearlyTrend : view === 'daily' ? dailyTrend : monthlyTrend;
    const chartData = [...trend].reverse();
    const netProfit = t.store_profit - totalExpenses;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Store Detail Report" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href={backHref} label="Back to Report" />
                    <div className="flex items-center gap-3">
                        <Store className="h-6 w-6 text-emerald-600" />
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold tracking-tight">Store-Owned Analysis</h1>
                                <Badge className="bg-emerald-600">Store</Badge>
                            </div>
                            <p className="text-muted-foreground text-sm">Goods bought without investor capital · {filters.label}</p>
                        </div>
                    </div>
                    <a href={`/reports/profit/store/pdf?${filterParams(filters)}`} className="ml-auto">
                        <Button className="gap-2 bg-red-600 hover:bg-red-700">
                            <FileText className="h-4 w-4" /> Download PDF
                        </Button>
                    </a>
                </div>

                <ReportPeriodFilter url="/reports/profit/store" filters={filters} branches={branches} />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard label="Total Sales" value={fmt(t.sales)} sub={`${t.orders} orders · ${t.quantity ?? 0} pcs`} icon={DollarSign} />
                    <KpiCard label="Store Profit" value={fmt(t.store_profit)} sub={`Margin ${fmtPct(t.margin)}`} icon={TrendingUp} tone="green" />
                    <KpiCard label="All Expenses" value={fmt(totalExpenses)} sub="Operating expenses in period" icon={TrendingDown} tone="red" />
                    <KpiCard
                        label="Net After Expenses"
                        value={fmt(netProfit)}
                        sub="Store profit − expenses"
                        icon={Wallet}
                        tone={netProfit < 0 ? 'red' : 'violet'}
                    />
                </div>

                <Section
                    title="Performance Trend"
                    description="Sales and store profit over time"
                    icon={TrendingUp}
                    action={
                        <div className="bg-muted inline-flex rounded-lg p-1">
                            {(['daily', 'monthly', 'yearly'] as View[]).map((v) => (
                                <button
                                    key={v}
                                    type="button"
                                    onClick={() => setView(v)}
                                    className={`rounded-md px-3 py-1 text-xs font-medium capitalize ${view === v ? 'bg-background shadow-sm' : 'text-muted-foreground'}`}
                                >
                                    {v}
                                </button>
                            ))}
                        </div>
                    }
                >
                    {chartData.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <ResponsiveContainer width="100%" height={300}>
                            <AreaChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="stSales" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor={CHART_COLORS.sales} stopOpacity={0.3} />
                                        <stop offset="95%" stopColor={CHART_COLORS.sales} stopOpacity={0} />
                                    </linearGradient>
                                    <linearGradient id="stProfit" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor={CHART_COLORS.store} stopOpacity={0.35} />
                                        <stop offset="95%" stopColor={CHART_COLORS.store} stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                <XAxis dataKey="period" tick={{ fontSize: 11 }} />
                                <YAxis tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                                <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                                <Legend />
                                <Area type="monotone" dataKey="sales" name="Sales" stroke={CHART_COLORS.sales} fill="url(#stSales)" strokeWidth={2} />
                                <Area
                                    type="monotone"
                                    dataKey="store_profit"
                                    name="Store Profit"
                                    stroke={CHART_COLORS.store}
                                    fill="url(#stProfit)"
                                    strokeWidth={2}
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    )}
                </Section>

                <Section title="Products Sold" description="Store-owned products and their profit" icon={Package}>
                    {purchases.length === 0 ? (
                        <EmptyState>No store-owned goods sold in this period.</EmptyState>
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10">#</TableHead>
                                        <TableHead>Product</TableHead>
                                        <TableHead className="text-center">Orders</TableHead>
                                        <TableHead className="text-center">Qty</TableHead>
                                        <TableHead className="text-right">Sales</TableHead>
                                        <TableHead className="text-right">Cost</TableHead>
                                        <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">Profit</TableHead>
                                        <TableHead className="text-right">Margin</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {purchases.map((r, i) => (
                                        <TableRow key={`${r.product_name}-${i}`}>
                                            <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                                            <TableCell className="font-medium">{r.product_name}</TableCell>
                                            <TableCell className="text-center font-mono text-xs">{r.orders}</TableCell>
                                            <TableCell className="text-center font-mono text-xs">{r.quantity}</TableCell>
                                            <TableCell className="text-right font-mono text-xs">{fmt(r.sales)}</TableCell>
                                            <TableCell className="text-muted-foreground text-right font-mono text-xs">{fmt(r.cost)}</TableCell>
                                            <TableCell className="bg-emerald-50 text-right font-mono text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                {fmt(r.store_profit)}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs">{fmtPct(r.margin)}</TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow className="bg-muted/60 font-bold">
                                        <TableCell />
                                        <TableCell>TOTAL</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{t.orders}</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{t.quantity}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(t.sales)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(t.cost)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(t.store_profit)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmtPct(t.margin)}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </Section>

                <Section title={`${view === 'yearly' ? 'Yearly (last 5 years)' : view === 'daily' ? 'Daily' : 'Monthly'} Details`} icon={Calendar}>
                    {trend.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{view === 'yearly' ? 'Year' : view === 'daily' ? 'Date' : 'Month'}</TableHead>
                                        <TableHead className="text-center">Orders</TableHead>
                                        <TableHead className="text-center">Qty</TableHead>
                                        <TableHead className="text-right">Sales</TableHead>
                                        <TableHead className="text-right">Cost</TableHead>
                                        <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">
                                            Store Profit
                                        </TableHead>
                                        <TableHead className="text-right">Margin</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {trend.map((r) => (
                                        <TableRow key={r.period}>
                                            <TableCell className="font-mono text-xs font-semibold">{r.period}</TableCell>
                                            <TableCell className="text-center font-mono text-xs">{r.orders}</TableCell>
                                            <TableCell className="text-center font-mono text-xs">{r.quantity}</TableCell>
                                            <TableCell className="text-right font-mono text-xs">{fmt(r.sales)}</TableCell>
                                            <TableCell className="text-muted-foreground text-right font-mono text-xs">{fmt(r.cost)}</TableCell>
                                            <TableCell className="bg-emerald-50 text-right font-mono text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                {fmt(r.store_profit)}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-xs">{fmtPct(r.margin)}</TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow className="bg-muted/60 font-bold">
                                        <TableCell>TOTAL</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{sum(trend, (r) => r.orders)}</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{sum(trend, (r) => r.quantity)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(sum(trend, (r) => r.sales))}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(sum(trend, (r) => r.cost))}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(sum(trend, (r) => r.store_profit))}</TableCell>
                                        <TableCell />
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </Section>
            </div>
        </AppLayout>
    );
}
