import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link } from '@inertiajs/react';
import { CircleDollarSign, Clock, ExternalLink, FileText, PiggyBank, Store, TrendingUp, User, Users, Wallet } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { type InvestorsSection } from './profit-types';
import { EmptyState, KpiCard, MatrixTable, Section, ShareBar } from './report-blocks';
import { CHART_COLORS, type ReportFilters, filterParams, fmt, fmtShort, sum, tooltipStyle } from './report-utils';

interface Props {
    investors: InvestorsSection;
    filters: ReportFilters;
}

export function ProfitInvestorsTab({ investors, filters }: Props) {
    const { rows, store, matrix, stats } = investors;
    const qs = filterParams(filters);
    const chart = rows
        .filter((r) => r.sales > 0)
        .slice(0, 12)
        .map((r) => ({ name: r.name, investor: r.investor_profit, store: r.store_profit }));
    const matrixUnit = filters.group_by === 'yearly' ? 'Year' : 'Month';

    return (
        <div className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Investors"
                    value={`${stats.active_investors} / ${stats.total_investors}`}
                    sub="Active in period / total"
                    icon={Users}
                />
                <KpiCard label="Investor Profit" value={fmt(stats.investor_profit)} sub="Earned in period" icon={TrendingUp} tone="blue" />
                <KpiCard label="Profit Paid" value={fmt(stats.entries_paid)} sub="Entries marked paid" icon={Wallet} tone="green" />
                <KpiCard label="Profit Pending" value={fmt(stats.entries_pending)} sub="Waiting for payout" icon={Clock} tone="amber" />
                <KpiCard label="Total Invested" value={fmt(stats.total_invested)} sub="Lifetime capital" icon={PiggyBank} tone="violet" />
                <KpiCard label="Capital Balance" value={fmt(stats.capital_balance)} sub="Unused capital now" icon={CircleDollarSign} />
                <KpiCard label="Profit Balance" value={fmt(stats.profit_balance)} sub="Withdrawable now" icon={Wallet} tone="green" />
                <KpiCard
                    label="Store Own Goods"
                    value={fmt(store.store_profit)}
                    sub={`${fmt(store.sales)} sales without investor`}
                    icon={Store}
                    tone="green"
                />
            </div>

            {chart.length > 0 && (
                <Section title="Profit by Investor" description="Investor share vs store share from each investor's goods" icon={Users}>
                    <ResponsiveContainer width="100%" height={280}>
                        <BarChart data={chart} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                            <YAxis tickFormatter={fmtShort} tick={{ fontSize: 11 }} />
                            <Tooltip formatter={(v) => `${fmt(Number(v))} AFN`} contentStyle={tooltipStyle} />
                            <Legend />
                            <Bar dataKey="investor" name="Investor Profit" stackId="a" fill={CHART_COLORS.investor} />
                            <Bar dataKey="store" name="Store Share" stackId="a" fill={CHART_COLORS.store} radius={[6, 6, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                </Section>
            )}

            <Section title="Profit per Investor" description={`Click an investor for the full statement · ${filters.label}`} icon={User}>
                {rows.length === 0 ? (
                    <EmptyState>No investors registered.</EmptyState>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">#</TableHead>
                                    <TableHead>Investor</TableHead>
                                    <TableHead className="text-center">Orders</TableHead>
                                    <TableHead className="text-center">Qty</TableHead>
                                    <TableHead className="text-right">Sales</TableHead>
                                    <TableHead className="bg-blue-50 text-right text-blue-800 dark:bg-blue-950/30">Investor Profit</TableHead>
                                    <TableHead className="bg-emerald-50 text-right text-emerald-800 dark:bg-emerald-950/30">Store Share</TableHead>
                                    <TableHead>Share</TableHead>
                                    <TableHead className="text-right">Paid</TableHead>
                                    <TableHead className="text-right">Pending</TableHead>
                                    <TableHead className="text-center">Statement</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((r, i) => (
                                    <TableRow key={String(r.id)}>
                                        <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                                        <TableCell>
                                            <Link
                                                href={`/reports/profit/investor/${r.id}?${qs}`}
                                                className="flex items-center gap-2 font-medium text-blue-700 hover:underline dark:text-blue-400"
                                            >
                                                <User className="h-3.5 w-3.5" /> {r.name}
                                            </Link>
                                            <div className="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs">
                                                {r.phone ?? '—'}
                                                <Badge
                                                    variant={r.status === 'active' ? 'default' : 'secondary'}
                                                    className={`h-4 px-1.5 text-[10px] ${r.status === 'active' ? 'bg-emerald-600' : ''}`}
                                                >
                                                    {r.status}
                                                </Badge>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center font-mono text-xs">{r.orders}</TableCell>
                                        <TableCell className="text-center font-mono text-xs">{r.quantity}</TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(r.sales)}</TableCell>
                                        <TableCell className="bg-blue-50 text-right font-mono text-xs font-semibold text-blue-800 dark:bg-blue-950/30 dark:text-blue-400">
                                            {fmt(r.investor_profit)}
                                        </TableCell>
                                        <TableCell className="bg-emerald-50 text-right font-mono text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">
                                            {fmt(r.store_profit)}
                                        </TableCell>
                                        <TableCell>
                                            <ShareBar value={r.share} color="bg-blue-500" />
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs text-emerald-700">{fmt(r.entries_paid)}</TableCell>
                                        <TableCell
                                            className={`text-right font-mono text-xs ${r.entries_pending > 0 ? 'font-semibold text-amber-700' : 'text-muted-foreground'}`}
                                        >
                                            {fmt(r.entries_pending)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-center gap-1">
                                                <Link href={`/reports/profit/investor/${r.id}?${qs}`}>
                                                    <Button size="sm" variant="outline" className="h-7 gap-1 px-2 text-xs">
                                                        View <ExternalLink className="h-3 w-3" />
                                                    </Button>
                                                </Link>
                                                <a href={`/reports/profit/investor/${r.id}/pdf?${qs}`}>
                                                    <Button size="sm" variant="outline" className="h-7 gap-1 px-2 text-xs text-red-700">
                                                        <FileText className="h-3 w-3" /> PDF
                                                    </Button>
                                                </a>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                <TableRow className="bg-emerald-50/50 dark:bg-emerald-950/20">
                                    <TableCell className="text-muted-foreground text-xs">—</TableCell>
                                    <TableCell>
                                        <Link
                                            href={`/reports/profit/store?${qs}`}
                                            className="flex items-center gap-2 font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                                        >
                                            <Store className="h-3.5 w-3.5" /> Store (own goods)
                                        </Link>
                                        <div className="text-muted-foreground mt-0.5 text-xs">No investor capital</div>
                                    </TableCell>
                                    <TableCell className="text-center font-mono text-xs">{store.orders}</TableCell>
                                    <TableCell className="text-center font-mono text-xs">{store.quantity}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(store.sales)}</TableCell>
                                    <TableCell className="text-muted-foreground text-right font-mono text-xs">—</TableCell>
                                    <TableCell className="text-right font-mono text-xs text-emerald-800 dark:text-emerald-400">
                                        {fmt(store.store_profit)}
                                    </TableCell>
                                    <TableCell />
                                    <TableCell className="text-muted-foreground text-right text-xs">—</TableCell>
                                    <TableCell className="text-muted-foreground text-right text-xs">—</TableCell>
                                    <TableCell>
                                        <div className="flex justify-center gap-1">
                                            <Link href={`/reports/profit/store?${qs}`}>
                                                <Button size="sm" variant="outline" className="h-7 gap-1 px-2 text-xs">
                                                    View <ExternalLink className="h-3 w-3" />
                                                </Button>
                                            </Link>
                                            <a href={`/reports/profit/store/pdf?${qs}`}>
                                                <Button size="sm" variant="outline" className="h-7 gap-1 px-2 text-xs text-red-700">
                                                    <FileText className="h-3 w-3" /> PDF
                                                </Button>
                                            </a>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow className="bg-muted/60 font-bold">
                                    <TableCell />
                                    <TableCell>TOTAL</TableCell>
                                    <TableCell className="text-center font-mono text-xs">—</TableCell>
                                    <TableCell className="text-center font-mono text-xs">
                                        {sum(rows, (r) => r.quantity) + (store.quantity ?? 0)}
                                    </TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.sales) + store.sales)}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(stats.investor_profit)}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">
                                        {fmt(sum(rows, (r) => r.store_profit) + store.store_profit)}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">100%</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(stats.entries_paid)}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(stats.entries_pending)}</TableCell>
                                    <TableCell />
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                )}
            </Section>

            {matrix.periods.length > 1 && (
                <Section title={`Investor Profit per ${matrixUnit}`} description="Each investor's profit side by side (AFN)" icon={TrendingUp}>
                    <MatrixTable
                        matrix={matrix}
                        nameLabel="Investor"
                        renderName={(row) => (
                            <Link href={`/reports/profit/investor/${row.id}?${qs}`} className="text-blue-700 hover:underline dark:text-blue-400">
                                {row.name}
                            </Link>
                        )}
                    />
                </Section>
            )}

            {rows.length > 0 && (
                <Section title="Investor Wallets" description="Current balances (lifetime, not limited to the selected period)" icon={Wallet}>
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">#</TableHead>
                                    <TableHead>Investor</TableHead>
                                    <TableHead className="text-right">Total Invested</TableHead>
                                    <TableHead className="text-right">Capital Balance</TableHead>
                                    <TableHead className="text-right">Lifetime Profit</TableHead>
                                    <TableHead className="text-right">Withdrawn</TableHead>
                                    <TableHead className="text-right">Profit Balance</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((r, i) => (
                                    <TableRow key={String(r.id)}>
                                        <TableCell className="text-muted-foreground text-xs">{i + 1}</TableCell>
                                        <TableCell className="font-medium">{r.name}</TableCell>
                                        <TableCell className="text-right font-mono text-xs text-violet-700 dark:text-violet-400">
                                            {fmt(r.total_invested)}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs">{fmt(r.capital_balance)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs text-emerald-700">{fmt(r.lifetime_profit)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs text-red-700">{fmt(r.total_withdrawn)}</TableCell>
                                        <TableCell className="text-right font-mono text-xs font-bold text-emerald-700">
                                            {fmt(r.profit_balance)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                <TableRow className="bg-muted/60 font-bold">
                                    <TableCell />
                                    <TableCell>TOTAL</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(stats.total_invested)}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(stats.capital_balance)}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.lifetime_profit))}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(sum(rows, (r) => r.total_withdrawn))}</TableCell>
                                    <TableCell className="text-right font-mono text-xs">{fmt(stats.profit_balance)}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </Section>
            )}
        </div>
    );
}
