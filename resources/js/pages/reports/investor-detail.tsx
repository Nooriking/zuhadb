// resources/js/pages/reports/investor-detail.tsx

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
import { Head, Link } from '@inertiajs/react';
import { Calendar, CheckCircle2, CircleDollarSign, Clock, DollarSign, FileText, Package, TrendingUp, User, Wallet, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Area, AreaChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

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

interface EntryStat {
    count: number;
    total: number;
}

interface PurchaseRow extends MetricRow {
    product_name: string;
    batch_code: string | null;
}

interface TrendRow extends MetricRow {
    period: string;
}

interface Props {
    investor: InvestorInfo;
    capitalInfo: CapitalInfo;
    entries: { paid: EntryStat; pending: EntryStat; cancelled: EntryStat };
    purchases: PurchaseRow[];
    monthlyTrend: TrendRow[];
    dailyTrend: TrendRow[];
    yearlyTrend: TrendRow[];
    totals: MetricRow;
    branches: BranchOption[];
    filters: ReportFilters;
}

type View = 'daily' | 'monthly' | 'yearly';

export default function InvestorDetail({
    investor,
    capitalInfo: c,
    entries,
    purchases,
    monthlyTrend,
    dailyTrend,
    yearlyTrend,
    totals: t,
    branches,
    filters,
}: Props) {
    const [view, setView] = useState<View>(filters.group_by);
    const qs = filterParams(filters);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Profit Report', href: `/reports/profit?${filterParams(filters, { tab: 'investors' })}` },
        { title: investor.full_name, href: `/reports/profit/investor/${investor.id}` },
    ];

    const trend = view === 'yearly' ? yearlyTrend : view === 'daily' ? dailyTrend : monthlyTrend;
    const chartData = [...trend].reverse();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${investor.full_name} — Investor Report`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href={`/reports/profit?${filterParams(filters, { tab: 'investors' })}`} label="Back to Report" />
                    <div className="flex items-center gap-3">
                        <User className="text-primary h-6 w-6" />
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold tracking-tight">{investor.full_name}</h1>
                                <Badge
                                    variant={investor.status === 'active' ? 'default' : 'secondary'}
                                    className={investor.status === 'active' ? 'bg-emerald-600' : ''}
                                >
                                    {investor.status}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-sm" dir="ltr">
                                {[investor.phone, investor.email].filter(Boolean).join(' · ') || 'No contact details'} · {filters.label}
                            </p>
                        </div>
                    </div>
                    <div className="ml-auto flex gap-2">
                        <a href={`/reports/profit/investor/${investor.id}/pdf?${qs}`}>
                            <Button className="gap-2 bg-red-600 hover:bg-red-700">
                                <FileText className="h-4 w-4" /> Download Statement PDF
                            </Button>
                        </a>
                        <Link href={`/investors/${investor.id}`}>
                            <Button variant="outline">Investor Page</Button>
                        </Link>
                    </div>
                </div>

                <ReportPeriodFilter url={`/reports/profit/investor/${investor.id}`} filters={filters} branches={branches} />

                <div>
                    <h2 className="text-muted-foreground mb-3 text-sm font-semibold tracking-wide uppercase">Wallet (current, lifetime)</h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <KpiCard label="Total Invested" value={fmt(c.total_invested)} sub="Money given by investor" icon={Wallet} tone="violet" />
                        <KpiCard
                            label="Capital Balance"
                            value={fmt(c.capital_balance)}
                            sub={c.capital_balance > 0 ? 'Available for use' : 'Fully used'}
                            icon={CircleDollarSign}
                            tone="blue"
                        />
                        <KpiCard label="Lifetime Profit" value={fmt(c.total_profit)} sub="All time" icon={TrendingUp} tone="green" />
                        <KpiCard label="Withdrawn" value={fmt(c.total_withdrawn)} sub="All time" icon={DollarSign} tone="red" />
                        <KpiCard label="Profit Balance" value={fmt(c.profit_balance)} sub="Available to withdraw" icon={Wallet} tone="green" />
                    </div>
                </div>

                <div>
                    <h2 className="text-muted-foreground mb-3 text-sm font-semibold tracking-wide uppercase">Selected period · {filters.label}</h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <KpiCard
                            label="Sales of Investor Goods"
                            value={fmt(t.sales)}
                            sub={`${t.orders} orders · ${t.quantity ?? 0} pcs`}
                            icon={DollarSign}
                        />
                        <KpiCard
                            label="Investor Profit"
                            value={fmt(t.investor_profit)}
                            sub={`Margin ${fmtPct(t.margin)}`}
                            icon={TrendingUp}
                            tone="blue"
                        />
                        <KpiCard label="Store Share" value={fmt(t.store_profit)} sub="Store commission" icon={Package} tone="green" />
                        <KpiCard label="Cost of Goods" value={fmt(t.cost)} sub="Capital returned" icon={CircleDollarSign} />
                        <KpiCard
                            label="Profit Paid"
                            value={fmt(entries.paid.total)}
                            sub={`${entries.paid.count} entries`}
                            icon={CheckCircle2}
                            tone="green"
                        />
                        <KpiCard
                            label="Profit Pending"
                            value={fmt(entries.pending.total)}
                            sub={`${entries.pending.count} entries`}
                            icon={Clock}
                            tone="amber"
                        />
                        <KpiCard
                            label="Cancelled"
                            value={fmt(entries.cancelled.total)}
                            sub={`${entries.cancelled.count} entries`}
                            icon={XCircle}
                            tone="red"
                        />
                    </div>
                </div>

                <Section
                    title="Performance Trend"
                    description="Investor profit and store share over time"
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
                                    <linearGradient id="invArea" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor={CHART_COLORS.investor} stopOpacity={0.35} />
                                        <stop offset="95%" stopColor={CHART_COLORS.investor} stopOpacity={0} />
                                    </linearGradient>
                                    <linearGradient id="storeArea" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor={CHART_COLORS.store} stopOpacity={0.35} />
                                        <stop offset="95%" stopColor={CHART_COLORS.store} stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                <XAxis dataKey="period" tick={{ fontSize: 11 }} />
                                <YAxis tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                                <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                                <Legend />
                                <Area
                                    type="monotone"
                                    dataKey="investor_profit"
                                    name="Investor Profit"
                                    stroke={CHART_COLORS.investor}
                                    fill="url(#invArea)"
                                    strokeWidth={2}
                                />
                                <Area
                                    type="monotone"
                                    dataKey="store_profit"
                                    name="Store Share"
                                    stroke={CHART_COLORS.store}
                                    fill="url(#storeArea)"
                                    strokeWidth={2}
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    )}
                </Section>

                <Section
                    title="Products Sold from This Investor's Capital"
                    description="Each product line with its profit contribution"
                    icon={Package}
                >
                    {purchases.length === 0 ? (
                        <EmptyState>No sales from this investor's goods in the selected period.</EmptyState>
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10">#</TableHead>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Batch</TableHead>
                                        <TableHead className="text-center">Orders</TableHead>
                                        <TableHead className="text-center">Qty</TableHead>
                                        <TableHead className="text-right">Sales</TableHead>
                                        <TableHead className="text-right">Cost</TableHead>
                                        <TableHead className="bg-blue-50 text-right text-blue-800 dark:bg-blue-950/30">Investor</TableHead>
                                        <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">Store</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {purchases.map((r, i) => (
                                        <TableRow key={`${r.product_name}-${r.batch_code}-${i}`}>
                                            <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                                            <TableCell className="font-medium">{r.product_name}</TableCell>
                                            <TableCell className="text-muted-foreground font-mono text-[11px]">{r.batch_code}</TableCell>
                                            <TableCell className="text-center font-mono text-xs">{r.orders}</TableCell>
                                            <TableCell className="text-center font-mono text-xs">{r.quantity}</TableCell>
                                            <TableCell className="text-right font-mono text-xs">{fmt(r.sales)}</TableCell>
                                            <TableCell className="text-muted-foreground text-right font-mono text-xs">{fmt(r.cost)}</TableCell>
                                            <TableCell className="bg-blue-50 text-right font-mono text-xs font-semibold text-blue-800 dark:bg-blue-950/30 dark:text-blue-400">
                                                {fmt(r.investor_profit)}
                                            </TableCell>
                                            <TableCell className="bg-emerald-50 text-right font-mono text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                {fmt(r.store_profit)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow className="bg-muted/60 font-bold">
                                        <TableCell />
                                        <TableCell colSpan={2}>TOTAL</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{t.orders}</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{t.quantity}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(t.sales)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(t.cost)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(t.investor_profit)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(t.store_profit)}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </Section>

                <Section title={`${view === 'yearly' ? 'Yearly (last 5 years)' : view === 'daily' ? 'Daily' : 'Monthly'} Details`} icon={Calendar}>
                    <TrendTable rows={trend} label={view === 'yearly' ? 'Year' : view === 'daily' ? 'Date' : 'Month'} />
                </Section>
            </div>
        </AppLayout>
    );
}

function TrendTable({ rows, label }: { rows: TrendRow[]; label: string }) {
    if (rows.length === 0) return <EmptyState />;

    return (
        <div className="overflow-x-auto rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{label}</TableHead>
                        <TableHead className="text-center">Orders</TableHead>
                        <TableHead className="text-center">Qty</TableHead>
                        <TableHead className="text-right">Sales</TableHead>
                        <TableHead className="text-right">Cost</TableHead>
                        <TableHead className="bg-blue-50 text-right text-blue-800 dark:bg-blue-950/30">Investor</TableHead>
                        <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">Store</TableHead>
                        <TableHead className="text-right">Margin</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((r) => (
                        <TableRow key={r.period}>
                            <TableCell className="font-mono text-xs font-semibold">{r.period}</TableCell>
                            <TableCell className="text-center font-mono text-xs">{r.orders}</TableCell>
                            <TableCell className="text-center font-mono text-xs">{r.quantity}</TableCell>
                            <TableCell className="text-right font-mono text-xs">{fmt(r.sales)}</TableCell>
                            <TableCell className="text-muted-foreground text-right font-mono text-xs">{fmt(r.cost)}</TableCell>
                            <TableCell className="bg-blue-50 text-right font-mono text-xs font-semibold text-blue-800 dark:bg-blue-950/30 dark:text-blue-400">
                                {fmt(r.investor_profit)}
                            </TableCell>
                            <TableCell className="bg-emerald-50 text-right font-mono text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                {fmt(r.store_profit)}
                            </TableCell>
                            <TableCell className="text-right font-mono text-xs">{fmtPct(r.margin)}</TableCell>
                        </TableRow>
                    ))}
                    <TableRow className="bg-muted/60 font-bold">
                        <TableCell>TOTAL</TableCell>
                        <TableCell className="text-center font-mono text-xs">{sum(rows, (r) => r.orders)}</TableCell>
                        <TableCell className="text-center font-mono text-xs">{sum(rows, (r) => r.quantity)}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.sales))}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.cost))}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.investor_profit))}</TableCell>
                        <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.store_profit))}</TableCell>
                        <TableCell />
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    );
}
