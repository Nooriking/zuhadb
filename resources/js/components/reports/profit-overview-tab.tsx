import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { BarChart3, Calendar, CircleDollarSign, DollarSign, Receipt, RotateCcw, Store, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
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
import { type BreakdownRow, type ExchangeRate, type ExpensesData, type ProfitSummary } from './profit-types';
import { EmptyState, KpiCard, Section, ShareBar } from './report-blocks';
import { CHART_COLORS, PIE_COLORS, type ReportFilters, fmt, fmtPct, fmtShort, sum, tooltipStyle } from './report-utils';

interface Props {
    summary: ProfitSummary;
    breakdown: BreakdownRow[];
    expenses: ExpensesData;
    exchangeRates: ExchangeRate[];
    filters: ReportFilters;
}

const PERIOD_LABEL = { daily: 'Date', monthly: 'Month', yearly: 'Year' } as const;
const GROUP_TITLE = { daily: 'Daily', monthly: 'Monthly', yearly: 'Yearly' } as const;

export function ProfitOverviewTab({ summary: s, breakdown, expenses, exchangeRates, filters }: Props) {
    const chartData = [...breakdown].reverse();
    const expensePie = expenses.by_category.map((e) => ({ name: e.category, value: e.total }));

    return (
        <div className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Total Sales"
                    value={fmt(s.total_sales)}
                    sub={`${s.total_orders} orders · ${s.total_quantity} pcs`}
                    icon={DollarSign}
                />
                <KpiCard label="Gross Profit" value={fmt(s.total_profit)} sub={`Margin ${fmtPct(s.margin)}`} icon={TrendingUp} tone="green" />
                <KpiCard label="Investor Profit" value={fmt(s.investor_profit)} sub="Payable to investors" icon={Wallet} tone="blue" />
                <KpiCard label="Store Profit" value={fmt(s.store_profit)} sub="Before expenses" icon={Store} tone="green" />
                <KpiCard
                    label="Expenses"
                    value={fmt(s.total_expenses)}
                    sub={`${expenses.by_category.length} categories`}
                    icon={TrendingDown}
                    tone="red"
                />
                <KpiCard
                    label="Store Net Profit"
                    value={fmt(s.store_net_profit)}
                    sub="Store profit − expenses"
                    icon={TrendingUp}
                    tone={s.store_net_profit < 0 ? 'red' : 'violet'}
                />
                <KpiCard label="Returns" value={fmt(s.total_returns)} sub={`${s.returns_count} completed returns`} icon={RotateCcw} tone="amber" />
                <KpiCard label="Average Order" value={fmt(s.avg_order)} sub={`${s.customers} registered customers`} icon={Receipt} />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Section title="Profit Statement" description="How sales turn into net profit (AFN)" icon={BarChart3}>
                    <div className="space-y-1 text-sm">
                        {[
                            { label: 'Sales revenue', value: s.total_sales, className: 'font-semibold' },
                            { label: 'Less: cost of goods', value: -s.total_cost, className: 'text-red-600' },
                            { label: 'Gross profit', value: s.total_profit, className: 'font-bold', total: true },
                            { label: "Investors' share", value: -s.investor_profit, className: 'pl-3 text-blue-600' },
                            { label: 'Store share', value: s.store_profit, className: 'pl-3 text-emerald-600' },
                            { label: 'Less: expenses', value: -s.total_expenses, className: 'text-red-600' },
                            { label: 'Store net profit', value: s.store_net_profit, className: 'font-bold', total: true },
                        ].map((line) => (
                            <div
                                key={line.label}
                                className={`flex justify-between rounded px-2 py-1.5 ${line.total ? 'border-y border-amber-600/60 bg-amber-50 dark:bg-amber-950/30' : ''}`}
                            >
                                <span className={line.className}>{line.label}</span>
                                <span className={`font-mono ${line.className}`}>{line.value < 0 ? `(${fmt(-line.value)})` : fmt(line.value)}</span>
                            </div>
                        ))}
                        <p className="text-muted-foreground pt-2 text-xs">
                            Discounts given: {fmt(s.total_discount)} AFN. Returns are already deducted from sales.
                        </p>
                    </div>
                </Section>

                <div className="lg:col-span-2">
                    <Section
                        title="Trend: Sales, Profit & Expenses"
                        description={`${GROUP_TITLE[filters.group_by]} view · ${filters.label}`}
                        icon={TrendingUp}
                    >
                        {chartData.length === 0 ? (
                            <EmptyState />
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <AreaChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="ovSales" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor={CHART_COLORS.sales} stopOpacity={0.35} />
                                            <stop offset="95%" stopColor={CHART_COLORS.sales} stopOpacity={0} />
                                        </linearGradient>
                                        <linearGradient id="ovProfit" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor={CHART_COLORS.profit} stopOpacity={0.35} />
                                            <stop offset="95%" stopColor={CHART_COLORS.profit} stopOpacity={0} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="period" tick={{ fontSize: 11 }} />
                                    <YAxis tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                                    <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                                    <Legend />
                                    <Area
                                        type="monotone"
                                        dataKey="sales"
                                        name="Sales"
                                        stroke={CHART_COLORS.sales}
                                        fill="url(#ovSales)"
                                        strokeWidth={2}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="total_profit"
                                        name="Gross Profit"
                                        stroke={CHART_COLORS.profit}
                                        fill="url(#ovProfit)"
                                        strokeWidth={2}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="expenses"
                                        name="Expenses"
                                        stroke={CHART_COLORS.expenses}
                                        fill="none"
                                        strokeWidth={2}
                                        strokeDasharray="5 5"
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="store_net"
                                        name="Store Net"
                                        stroke={CHART_COLORS.net}
                                        fill="none"
                                        strokeWidth={2}
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        )}
                    </Section>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Section title="Investor vs Store Profit" description="Profit split over time" icon={Wallet}>
                    {chartData.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <BarChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                <XAxis dataKey="period" tick={{ fontSize: 11 }} />
                                <YAxis tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                                <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                                <Legend />
                                <Bar dataKey="investor_profit" name="Investor Profit" stackId="a" fill={CHART_COLORS.investor} />
                                <Bar dataKey="store_profit" name="Store Profit" stackId="a" fill={CHART_COLORS.store} radius={[6, 6, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </Section>

                <Section title="Expenses by Category" description={`Total ${fmt(expenses.total)} AFN`} icon={Receipt}>
                    {expensePie.length === 0 ? (
                        <EmptyState>No expenses in this period.</EmptyState>
                    ) : (
                        <div className="grid grid-cols-1 items-center gap-4 md:grid-cols-2">
                            <ResponsiveContainer width="100%" height={220}>
                                <PieChart>
                                    <Pie data={expensePie} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={50} outerRadius={90}>
                                        {expensePie.map((_, i) => (
                                            <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                                </PieChart>
                            </ResponsiveContainer>
                            <div className="space-y-2">
                                {expenses.by_category.map((e, i) => (
                                    <div key={e.category} className="flex items-center justify-between gap-2 text-sm">
                                        <span className="flex items-center gap-2">
                                            <span className="h-2.5 w-2.5 rounded-full" style={{ background: PIE_COLORS[i % PIE_COLORS.length] }} />
                                            {e.category} <span className="text-muted-foreground text-xs">({e.count})</span>
                                        </span>
                                        <span className="font-mono text-xs font-semibold text-red-700">{fmt(e.total)}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </Section>
            </div>

            <Section
                title={`${GROUP_TITLE[filters.group_by]} Breakdown`}
                description={`${breakdown.length} rows · ${filters.from} → ${filters.to}`}
                icon={Calendar}
            >
                {breakdown.length === 0 ? (
                    <EmptyState>No sales data for this period.</EmptyState>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{PERIOD_LABEL[filters.group_by]}</TableHead>
                                    <TableHead className="text-center">Orders</TableHead>
                                    <TableHead className="text-center">Qty</TableHead>
                                    <TableHead className="text-right">Sales</TableHead>
                                    <TableHead className="text-right">Cost</TableHead>
                                    <TableHead className="bg-blue-50 text-right text-blue-800 dark:bg-blue-950/30">Investor</TableHead>
                                    <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">Store</TableHead>
                                    <TableHead className="text-right">Gross</TableHead>
                                    <TableHead className="bg-red-50 text-right text-red-800 dark:bg-red-950/30">Expenses</TableHead>
                                    <TableHead className="bg-violet-50 text-right text-violet-800 dark:bg-violet-950/30">Store Net</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {breakdown.map((r) => (
                                    <TableRow key={r.period}>
                                        <TableCell className="font-mono text-xs font-semibold">{r.period}</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{r.orders}</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{r.quantity}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(r.sales)}</TableCell>
                                        <TableCell className="text-muted-foreground text-right font-mono text-xs">{fmt(r.cost)}</TableCell>
                                        <TableCell className="bg-blue-50 text-right font-mono text-xs font-semibold text-blue-800 dark:bg-blue-950/30 dark:text-blue-400">
                                            {fmt(r.investor_profit)}
                                        </TableCell>
                                        <TableCell className="bg-emerald-50 text-right font-mono text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                            {fmt(r.store_profit)}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs font-bold">{fmt(r.total_profit)}</TableCell>
                                        <TableCell className="bg-red-50 text-right font-mono text-xs text-red-700 dark:bg-red-950/30 dark:text-red-400">
                                            {r.expenses > 0 ? fmt(r.expenses) : '—'}
                                        </TableCell>
                                        <TableCell
                                            className={`bg-violet-50 text-right font-mono text-xs font-bold dark:bg-violet-950/30 ${r.store_net < 0 ? 'text-red-700' : 'text-violet-700 dark:text-violet-400'}`}
                                        >
                                            {fmt(r.store_net)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                <TableRow className="bg-muted/60 font-bold">
                                    <TableCell>TOTAL</TableCell>
                                    <TableCell className="text-center font-mono text-xs">{sum(breakdown, (r) => r.orders)}</TableCell>
                                    <TableCell className="text-center font-mono text-xs">{sum(breakdown, (r) => r.quantity)}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(breakdown, (r) => r.sales))}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(breakdown, (r) => r.cost))}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(breakdown, (r) => r.investor_profit))}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(breakdown, (r) => r.store_profit))}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(breakdown, (r) => r.total_profit))}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(breakdown, (r) => r.expenses))}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(breakdown, (r) => r.store_net))}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                )}
            </Section>

            {expenses.by_category.length > 0 && (
                <Section title="Expense Details" icon={Receipt}>
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Category</TableHead>
                                    <TableHead className="text-center">Count</TableHead>
                                    <TableHead className="text-right">Total (AFN)</TableHead>
                                    <TableHead>Share</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {expenses.by_category.map((e) => (
                                    <TableRow key={e.category}>
                                        <TableCell className="font-medium">{e.category}</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{e.count}</TableCell>
                                        <TableCell className="text-right font-mono text-sm font-bold text-red-700">{fmt(e.total)}</TableCell>
                                        <TableCell>
                                            <ShareBar value={expenses.total > 0 ? (e.total / expenses.total) * 100 : 0} color="bg-red-500" />
                                        </TableCell>
                                    </TableRow>
                                ))}
                                <TableRow className="bg-muted/60 font-bold">
                                    <TableCell>TOTAL</TableCell>
                                    <TableCell className="text-center font-mono text-xs">{sum(expenses.by_category, (e) => e.count)}</TableCell>
                                    <TableCell className="text-right font-mono text-sm text-red-700">{fmt(expenses.total)}</TableCell>
                                    <TableCell className="font-mono text-xs">100%</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </Section>
            )}

            {exchangeRates.length > 0 && (
                <Section title="Exchange Rate Trend (USD → AFN)" description="Average, min and max dollar rate per month" icon={CircleDollarSign}>
                    <ResponsiveContainer width="100%" height={260}>
                        <LineChart data={[...exchangeRates].reverse()} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                            <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                            <YAxis domain={['auto', 'auto']} tick={{ fontSize: 11 }} />
                            <Tooltip formatter={(v) => `${Number(v).toFixed(2)} AFN`} contentStyle={tooltipStyle} />
                            <Legend />
                            <Line type="monotone" dataKey="avg_rate" name="Average" stroke="#f59e0b" strokeWidth={3} dot={{ r: 4 }} />
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
                </Section>
            )}
        </div>
    );
}
