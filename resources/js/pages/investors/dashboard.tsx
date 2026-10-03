import { LazyInView } from '@/components/lazy-in-view';
import { Head, Link, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertCircle,
    ArrowDownToLine,
    ArrowUpFromLine,
    Banknote,
    Calendar,
    Package,
    PiggyBank,
    ShoppingCart,
    TrendingDown,
    TrendingUp,
    Trophy,
    Users,
    Wallet,
} from 'lucide-react';
import { lazy, Suspense, useState } from 'react';

const InvestorDashboardCharts = lazy(() => import('./dashboard-charts'));

/* =========================================================
 |  Types
 ========================================================= */

interface Kpis {
    total_capital: number;
    available_capital: number;
    invested_capital: number;
    total_profit: number;
    pending_profit: number;
    paid_profit: number;
    withdrawn_profit: number;
    withdrawn_capital: number;
    remaining_profit: number;
    capital_utilization: number;
    profit_margin: number;
    stock_value_afn?: number;
    stock_value_usd?: number;
}

interface StockItem {
    name: string;
    quantity: number;
    remaining: number;
    rate: number;
    value_usd: number;
    profit_usd: number;
}

interface CapitalTrendPoint {
    date: string;
    capital: number;
    allocated: number;
    profit: number;
    count: number;
}

interface NameValue {
    name: string;
    value: number;
    count?: number;
}

interface Counters {
    total_investors: number;
    active_investors: number;
    total_investments: number;
    active_investments: number;
    total_allocations: number;
    total_funded_qty: number;
    total_sold_qty: number;
    total_remaining_qty: number;
    pending_withdrawals: number;
    pending_profit_rows: number;
}

interface RecentInvestment {
    id: number;
    investment_number: string;
    investor: string | null;
    investor_phone: string | null;
    capital_amount_afn: number;
    available_afn: number;
    allocated_afn: number;
    profit_afn: number;
    progress_percent: number;
    status: string;
    status_label: string;
    investment_date: string;
    currency: string | null;
}

interface RecentProfitEntry {
    id: number;
    investor: string | null;
    sale_invoice: string | null;
    quantity: number;
    profit_amount_afn: number;
    profit_amount_curr: number;
    currency: string | null;
    entry_type: string;
    entry_type_label: string;
    status: string;
    status_label: string;
    earned_at: string;
}

interface RecentWithdrawal {
    id: number;
    withdrawal_number: string;
    investor: string | null;
    type: string;
    amount_afn: number;
    amount_currency: number;
    currency: string | null;
    status: string;
    withdrawal_date: string;
}

interface RecentTransaction {
    id: number;
    investor: string | null;
    type: string;
    type_label: string;
    amount_afn: number;
    balance_type: string;
    balance_after: number;
    currency: string | null;
    description: string | null;
    date: string;
}

interface TopInvestor {
    id: number;
    full_name: string;
    phone: string;
    status: string;
    investments_count: number;
    total_capital_afn: number;
    total_allocated: number;
    capital_balance: number;
    profit_balance: number;
    total_invested: number;
    total_profit: number;
    total_withdrawn: number;
}

interface TopProfitableInvestor {
    id: number;
    full_name: string;
    phone: string;
    total_profit_afn: number;
}

interface Props {
    days: number;
    kpis: Kpis;
    capitalTrend: CapitalTrendPoint[];
    profitByInvestor: NameValue[];
    withdrawalBreakdown: NameValue[];
    investmentStatus: NameValue[];
    counters: Counters;
    recentInvestments: RecentInvestment[];
    recentProfitEntries: RecentProfitEntry[];
    recentWithdrawals: RecentWithdrawal[];
    recentTransactions: RecentTransaction[];
    topInvestors: TopInvestor[];
    topProfitableInvestors: TopProfitableInvestor[];
    own?: boolean;
    stockItems?: StockItem[];
}

/* =========================================================
 |  Helpers
 ========================================================= */

const fmt = (n: number): string => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(n || 0);

const statusColor = (status: string): string => {
    switch (status) {
        case 'active':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
        case 'partially_used':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        case 'fully_used':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'withdrawn':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        case 'closed':
            return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
        case 'pending':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        case 'paid':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
        case 'approved':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'rejected':
        case 'cancelled':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
    }
};

const balanceTypeColor = (type: string): string => {
    switch (type) {
        case 'capital':
            return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400';
        case 'profit':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
    }
};

/* =========================================================
 |  Sub-components
 ========================================================= */

interface KpiCardProps {
    label: string;
    value: string;
    sub?: string;
    icon: LucideIcon;
    accent: string;
}

function KpiCard({ label, value, sub, icon: Icon, accent }: KpiCardProps) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-start justify-between">
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">{label}</p>
                    <p className="mt-2 truncate text-2xl font-bold text-gray-900 dark:text-white">{value}</p>
                    {sub && <p className="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{sub}</p>}
                </div>
                <div className={`shrink-0 rounded-xl p-2.5 ${accent}`}>
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}

interface SectionCardProps {
    title: string;
    children: React.ReactNode;
    action?: React.ReactNode;
}

function SectionCard({ title, children, action }: SectionCardProps) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-white">{title}</h3>
                {action}
            </div>
            <div className="p-5">{children}</div>
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    return <div className="flex items-center justify-center py-8 text-sm text-gray-500 dark:text-gray-400">{message}</div>;
}

function OwnInvestorDashboard({ kpis, recentProfitEntries, stockItems = [] }: Pick<Props, 'kpis' | 'recentProfitEntries' | 'stockItems'>) {
    const logout = () => {
        router.post(
            '/logout',
            {},
            {
                onFinish: () => {
                    window.location.href = '/login';
                },
            },
        );
    };

    return (
        <div className="bg-background min-h-screen">
            <Head title="My dashboard" />
            <div className="mx-auto flex max-w-5xl flex-col gap-6 p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">My dashboard</h1>
                        <p className="text-muted-foreground text-sm">Your capital, profit, and shop stock</p>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/settings/profile" className="rounded-lg border px-3 py-2 text-sm">
                            Change name
                        </Link>
                        <Link href="/settings/password" className="rounded-lg border px-3 py-2 text-sm">
                            Change password
                        </Link>
                        <button type="button" onClick={logout} className="rounded-lg border px-3 py-2 text-sm">
                            Log out
                        </button>
                    </div>
                </div>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold tracking-wide uppercase">Capital</h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <KpiCard
                            label="Capital given"
                            value={`${fmt(kpis.total_capital)} USD`}
                            sub="Money you invested"
                            icon={Wallet}
                            accent="bg-indigo-100 text-indigo-600"
                        />
                        <KpiCard
                            label="Still available"
                            value={`${fmt(kpis.available_capital)} USD`}
                            sub="Not yet spent on stock"
                            icon={PiggyBank}
                            accent="bg-emerald-100 text-emerald-600"
                        />
                        <KpiCard
                            label="Used on stock"
                            value={`${fmt(kpis.invested_capital)} USD`}
                            sub="Allocated to purchases"
                            icon={ShoppingCart}
                            accent="bg-amber-100 text-amber-600"
                        />
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold tracking-wide uppercase">Profit</h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <KpiCard
                            label="Profit earned"
                            value={`${fmt(kpis.total_profit)} USD`}
                            sub={`${fmt(kpis.profit_margin)}% of capital`}
                            icon={TrendingUp}
                            accent="bg-emerald-100 text-emerald-600"
                        />
                        <KpiCard
                            label="Profit waiting"
                            value={`${fmt(kpis.pending_profit)} USD`}
                            sub="Not paid out yet"
                            icon={Banknote}
                            accent="bg-amber-100 text-amber-600"
                        />
                        <KpiCard
                            label="Profit paid"
                            value={`${fmt(kpis.paid_profit)} USD`}
                            sub={`${fmt(kpis.withdrawn_profit)} USD withdrawn`}
                            icon={ArrowUpFromLine}
                            accent="bg-blue-100 text-blue-600"
                        />
                    </div>
                    <SectionCard title="Profit entries">
                        {recentProfitEntries.length === 0 ? (
                            <EmptyState message="No profit yet." />
                        ) : (
                            <div className="space-y-2">
                                {recentProfitEntries.map((entry) => (
                                    <div key={entry.id} className="flex items-center justify-between text-sm">
                                        <span>{entry.sale_invoice || entry.entry_type_label}</span>
                                        <span className="font-medium">{fmt(entry.profit_amount_afn)} USD</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </SectionCard>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold tracking-wide uppercase">Goods bought with your dollars</h2>
                    <KpiCard
                        label="Purchases in dollars"
                        value={`${fmt(kpis.stock_value_usd || 0)} USD`}
                        sub="AFN purchase cost divided by that day's dollar rate"
                        icon={Package}
                        accent="bg-violet-100 text-violet-600"
                    />
                    <SectionCard title="Products">
                        {stockItems.length === 0 ? (
                            <EmptyState message="No goods have been bought with your capital yet." />
                        ) : (
                            <div className="space-y-2">
                                {stockItems.map((item, index) => (
                                    <div key={`${item.name}-${index}`} className="flex items-center justify-between text-sm">
                                        <span>
                                            {item.name} · {item.quantity} pcs
                                            {item.remaining !== item.quantity ? ` · ${item.remaining} left` : ''}
                                        </span>
                                        <span className="font-medium">
                                            {item.rate > 0 ? `${fmt(item.value_usd)} USD` : 'Rate missing'}
                                            {item.profit_usd > 0 ? ` · profit ${fmt(item.profit_usd)} USD` : ''}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </SectionCard>
                </section>
            </div>
        </div>
    );
}

/* =========================================================
 |  Main Component
 ========================================================= */

export default function InvestorDashboard({
    days,
    kpis,
    capitalTrend,
    profitByInvestor,
    withdrawalBreakdown,
    investmentStatus,
    counters,
    recentInvestments,
    recentProfitEntries,
    recentWithdrawals,
    recentTransactions,
    topInvestors,
    topProfitableInvestors,
    own = false,
    stockItems = [],
}: Props) {
    const [range, setRange] = useState<number>(days);

    if (own) {
        return <OwnInvestorDashboard kpis={kpis} recentProfitEntries={recentProfitEntries} stockItems={stockItems} />;
    }

    const handleRangeChange = (newDays: number) => {
        setRange(newDays);
        router.get('/investors/dashboard', { days: newDays }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Investor Dashboard" />

            <div className="bg-background min-h-screen">
                <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    {/* â”€â”€â”€â”€â”€â”€â”€â”€â”€ Header â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
                    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Investor Dashboard</h1>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Overview of capital, investments, and profits</p>
                        </div>

                        <div className="flex items-center gap-2">
                            <Calendar className="h-4 w-4 text-gray-400" />
                            {[7, 30, 90, 365].map((d) => (
                                <button
                                    key={d}
                                    onClick={() => handleRangeChange(d)}
                                    className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                                        range === d
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    {d === 365 ? '1Y' : `${d}D`}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* â”€â”€â”€â”€â”€â”€â”€â”€â”€ KPI Row 1 â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <KpiCard
                            label="Total Capital"
                            value={`${fmt(kpis.total_capital)} USD`}
                            sub={`${kpis.capital_utilization}% utilized`}
                            icon={Wallet}
                            accent="bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                        />
                        <KpiCard
                            label="Available Capital"
                            value={`${fmt(kpis.available_capital)} USD`}
                            sub="Ready to allocate"
                            icon={PiggyBank}
                            accent="bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400"
                        />
                        <KpiCard
                            label="Invested Capital"
                            value={`${fmt(kpis.invested_capital)} USD`}
                            sub="Allocated to purchases"
                            icon={TrendingUp}
                            accent="bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
                        />
                        <KpiCard
                            label="Total Profit"
                            value={`${fmt(kpis.total_profit)} USD`}
                            sub={`${fmt(kpis.pending_profit)} pending • ${kpis.profit_margin}% margin`}
                            icon={Banknote}
                            accent="bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
                        />
                    </div>

                    {/* â”€â”€â”€â”€â”€â”€â”€â”€â”€ KPI Row 2 â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <KpiCard
                            label="Paid Profit"
                            value={`${fmt(kpis.paid_profit)} USD`}
                            icon={ArrowUpFromLine}
                            accent="bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400"
                        />
                        <KpiCard
                            label="Withdrawn Profit"
                            value={`${fmt(kpis.withdrawn_profit)} USD`}
                            icon={ArrowDownToLine}
                            accent="bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400"
                        />
                        <KpiCard
                            label="Remaining Profit"
                            value={`${fmt(kpis.remaining_profit)} USD`}
                            sub="Available for withdrawal"
                            icon={TrendingDown}
                            accent="bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400"
                        />
                        <KpiCard
                            label="Withdrawn Capital"
                            value={`${fmt(kpis.withdrawn_capital)} USD`}
                            icon={Wallet}
                            accent="bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                        />
                    </div>

                    {/* Charts (lazy + in-view) */}
                    <LazyInView minHeight={620} className="mt-6">
                        <Suspense fallback={<div className="bg-muted/40 h-[620px] animate-pulse rounded-xl" />}>
                            <InvestorDashboardCharts
                                capitalTrend={capitalTrend}
                                profitByInvestor={profitByInvestor}
                                withdrawalBreakdown={withdrawalBreakdown}
                                investmentStatus={investmentStatus}
                            />
                        </Suspense>
                    </LazyInView>

                    {/* Counters */}
                    <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                        {[
                            { label: 'Total Investors', value: counters.total_investors, icon: Users },
                            { label: 'Active Investors', value: counters.active_investors, icon: Users },
                            { label: 'Investments', value: counters.total_investments, icon: TrendingUp },
                            { label: 'Funded Qty', value: counters.total_funded_qty, icon: Package },
                            { label: 'Sold Qty', value: counters.total_sold_qty, icon: ShoppingCart },
                            { label: 'Pending WD', value: counters.pending_withdrawals, icon: AlertCircle },
                        ].map((c, i) => (
                            <div key={i} className="rounded-xl border border-gray-200 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                                <c.icon className="mx-auto h-4 w-4 text-gray-400" />
                                <p className="mt-2 text-xl font-bold text-gray-900 dark:text-white">{fmt(c.value)}</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">{c.label}</p>
                            </div>
                        ))}
                    </div>

                    {/* â”€â”€â”€â”€â”€â”€â”€â”€â”€ Top Investors + Top Profitable â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
                    <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div className="lg:col-span-2">
                            <SectionCard
                                title="Top Investors by Capital"
                                action={
                                    <Link
                                        href="/investors"
                                        className="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                    >
                                        View all â†’
                                    </Link>
                                }
                            >
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-gray-100 text-left text-xs text-gray-500 uppercase dark:border-gray-800 dark:text-gray-400">
                                                <th className="pr-3 pb-2">
                                                    <Trophy className="inline h-3.5 w-3.5" /> #
                                                </th>
                                                <th className="pr-3 pb-2">Investor</th>
                                                <th className="pr-3 pb-2 text-right">Capital</th>
                                                <th className="pr-3 pb-2 text-right">Allocated</th>
                                                <th className="pr-3 pb-2 text-right">Profit</th>
                                                <th className="pr-3 pb-2 text-center">Inv.</th>
                                                <th className="pb-2 text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {topInvestors.map((inv, i) => (
                                                <tr
                                                    key={inv.id}
                                                    className="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800/50 dark:hover:bg-gray-800/30"
                                                >
                                                    <td className="py-3 pr-3 font-semibold text-gray-500">{i + 1}</td>
                                                    <td className="py-3 pr-3">
                                                        <Link
                                                            href={`/investors/${inv.id}`}
                                                            className="font-medium text-gray-900 hover:text-indigo-600 dark:text-white"
                                                        >
                                                            {inv.full_name}
                                                        </Link>
                                                        {inv.phone && <p className="text-xs text-gray-500">{inv.phone}</p>}
                                                    </td>
                                                    <td className="py-3 pr-3 text-right font-medium">{fmt(inv.total_capital_afn)}</td>
                                                    <td className="py-3 pr-3 text-right text-gray-600 dark:text-gray-400">
                                                        {fmt(inv.total_allocated)}
                                                    </td>
                                                    <td className="py-3 pr-3 text-right font-medium text-emerald-600 dark:text-emerald-400">
                                                        {fmt(inv.total_profit)}
                                                    </td>
                                                    <td className="py-3 pr-3 text-center">
                                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium dark:bg-gray-800">
                                                            {inv.investments_count}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 text-center">
                                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(inv.status)}`}>
                                                            {inv.status}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    {topInvestors.length === 0 && <EmptyState message="No investors yet" />}
                                </div>
                            </SectionCard>
                        </div>

                        <SectionCard title="Top by Profit">
                            <div className="space-y-2">
                                {topProfitableInvestors.map((inv, i) => (
                                    <div
                                        key={inv.id}
                                        className="flex items-center justify-between rounded-lg border border-gray-100 p-3 dark:border-gray-800"
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span
                                                className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                                    i === 0
                                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                                        : i === 1
                                                          ? 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                                          : i === 2
                                                            ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                                                }`}
                                            >
                                                {i + 1}
                                            </span>
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-gray-900 dark:text-white">{inv.full_name}</p>
                                                {inv.phone && <p className="truncate text-xs text-gray-500">{inv.phone}</p>}
                                            </div>
                                        </div>
                                        <p className="ml-3 shrink-0 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                            {fmt(inv.total_profit_afn)}
                                        </p>
                                    </div>
                                ))}
                                {topProfitableInvestors.length === 0 && <EmptyState message="No profit data yet" />}
                            </div>
                        </SectionCard>
                    </div>

                    {/* â”€â”€â”€â”€â”€â”€â”€â”€â”€ Recent Activity â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
                    <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {/* Recent Investments */}
                        <SectionCard
                            title="Recent Investments"
                            action={
                                <Link href="/investments" className="text-xs text-indigo-600 dark:text-indigo-400">
                                    View all â†’
                                </Link>
                            }
                        >
                            <div className="space-y-3">
                                {recentInvestments.map((inv) => (
                                    <Link
                                        key={inv.id}
                                        href={`/investments/${inv.id}`}
                                        className="block rounded-xl border border-gray-100 p-3 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/40"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                                    {inv.investment_number}
                                                </p>
                                                <p className="truncate text-xs text-gray-500">
                                                    {inv.investor ?? '—'} • {inv.investment_date}
                                                </p>
                                            </div>
                                            <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(inv.status)}`}>
                                                {inv.status_label}
                                            </span>
                                        </div>
                                        <div className="mt-2 grid grid-cols-3 gap-2 text-xs">
                                            <div>
                                                <p className="text-gray-500">Capital</p>
                                                <p className="font-semibold">{fmt(inv.capital_amount_afn)}</p>
                                            </div>
                                            <div>
                                                <p className="text-gray-500">Allocated</p>
                                                <p className="font-semibold">{fmt(inv.allocated_afn)}</p>
                                            </div>
                                            <div>
                                                <p className="text-gray-500">Profit</p>
                                                <p className="font-semibold text-emerald-600">{fmt(inv.profit_afn)}</p>
                                            </div>
                                        </div>
                                        <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div className="h-full bg-indigo-500" style={{ width: `${Math.min(100, inv.progress_percent)}%` }} />
                                        </div>
                                    </Link>
                                ))}
                                {recentInvestments.length === 0 && <EmptyState message="No investments yet" />}
                            </div>
                        </SectionCard>

                        {/* Recent Profit Entries */}
                        <SectionCard
                            title="Recent Profit Entries"
                            action={
                                <Link href="/investor-profit-entries" className="text-xs text-indigo-600 dark:text-indigo-400">
                                    View all â†’
                                </Link>
                            }
                        >
                            <div className="space-y-2">
                                {recentProfitEntries.map((e) => (
                                    <div
                                        key={e.id}
                                        className="flex items-center justify-between rounded-lg border border-gray-100 p-3 dark:border-gray-800"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-gray-900 dark:text-white">{e.investor ?? '—'}</p>
                                            <p className="truncate text-xs text-gray-500">
                                                {e.sale_invoice ?? '—'} • Qty: {e.quantity}
                                            </p>
                                        </div>
                                        <div className="ml-3 shrink-0 text-right">
                                            <p className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                                +{fmt(e.profit_amount_afn)} USD
                                            </p>
                                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${statusColor(e.status)}`}>
                                                {e.status_label}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                                {recentProfitEntries.length === 0 && <EmptyState message="No profit entries yet" />}
                            </div>
                        </SectionCard>
                    </div>

                    {/* â”€â”€â”€â”€â”€â”€â”€â”€â”€ Recent Withdrawals â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
                    <div className="mt-6">
                        <SectionCard
                            title="Recent Withdrawals"
                            action={
                                <Link href="/investor-withdrawals" className="text-xs text-indigo-600 dark:text-indigo-400">
                                    View all â†’
                                </Link>
                            }
                        >
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 text-left text-xs text-gray-500 uppercase dark:border-gray-800">
                                            <th className="pr-3 pb-2">#</th>
                                            <th className="pr-3 pb-2">Investor</th>
                                            <th className="pr-3 pb-2">Type</th>
                                            <th className="pr-3 pb-2 text-right">Amount</th>
                                            <th className="pr-3 pb-2">Date</th>
                                            <th className="pb-2 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentWithdrawals.map((w) => (
                                            <tr key={w.id} className="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800/50">
                                                <td className="py-3 pr-3 text-xs text-gray-500">{w.withdrawal_number}</td>
                                                <td className="py-3 pr-3 font-medium">{w.investor ?? '—'}</td>
                                                <td className="py-3 pr-3">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                            w.type === 'profit'
                                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                                : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                                        }`}
                                                    >
                                                        {w.type}
                                                    </span>
                                                </td>
                                                <td className="py-3 pr-3 text-right font-semibold">{fmt(w.amount_afn)} USD</td>
                                                <td className="py-3 pr-3 text-xs text-gray-500">{w.withdrawal_date}</td>
                                                <td className="py-3 text-center">
                                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(w.status)}`}>
                                                        {w.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {recentWithdrawals.length === 0 && <EmptyState message="No withdrawals yet" />}
                            </div>
                        </SectionCard>
                    </div>

                    {/* â”€â”€â”€â”€â”€â”€â”€â”€â”€ Recent Transactions â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
                    <div className="mt-6">
                        <SectionCard
                            title="Recent Transactions (Ledger)"
                            action={
                                <Link href="/investor-transactions" className="text-xs text-indigo-600 dark:text-indigo-400">
                                    View all â†’
                                </Link>
                            }
                        >
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 text-left text-xs text-gray-500 uppercase dark:border-gray-800">
                                            <th className="pr-3 pb-2">Investor</th>
                                            <th className="pr-3 pb-2">Type</th>
                                            <th className="pr-3 pb-2">Balance</th>
                                            <th className="pr-3 pb-2 text-right">Amount</th>
                                            <th className="pr-3 pb-2 text-right">After</th>
                                            <th className="pr-3 pb-2">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentTransactions.map((t) => (
                                            <tr key={t.id} className="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800/50">
                                                <td className="py-3 pr-3 font-medium">{t.investor ?? '—'}</td>
                                                <td className="py-3 pr-3">
                                                    <span className="text-xs text-gray-600 dark:text-gray-400">{t.type_label}</span>
                                                </td>
                                                <td className="py-3 pr-3">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${balanceTypeColor(t.balance_type)}`}
                                                    >
                                                        {t.balance_type}
                                                    </span>
                                                </td>
                                                <td className="py-3 pr-3 text-right font-semibold">{fmt(t.amount_afn)} USD</td>
                                                <td className="py-3 pr-3 text-right text-gray-600 dark:text-gray-400">{fmt(t.balance_after)}</td>
                                                <td className="py-3 pr-3 text-xs text-gray-500">{t.date}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {recentTransactions.length === 0 && <EmptyState message="No transactions yet" />}
                            </div>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </>
    );
}
