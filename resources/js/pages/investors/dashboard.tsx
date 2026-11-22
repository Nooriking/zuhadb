import AppLayout from '@/layouts/app-layout';
import { useExchange } from '@/hooks/useExchange';
import { formatMoney } from '@/lib/money';
import { Head, Link, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowDownToLine,
    LineChart,
    Package,
    PiggyBank,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import { useMemo, useState } from 'react';

type Tri = { afn: number; usd: number | null; aed: number | null };

type AverageRate = {
    currency_code: string;
    currency_id: number | null;
    total_currency: number;
    total_afn: number;
    investments_count: number;
    weighted_avg_rate: number | null;
    board_rate: number | null;
    rate_delta: number | null;
    rate_delta_pct: number | null;
};

type InventoryItem = {
    batch_id: number;
    batch_code: string;
    product_name: string;
    variant_name?: string | null;
    volume_ml?: number | null;
    concentration?: string | null;
    status: string;
    quantity: number;
    remaining_quantity: number;
    sold_quantity: number;
    unit_cost_afn: number;
    sale_price_afn: number;
    cost_value_afn: number;
    sale_value_afn: number;
    potential_profit_afn: number;
    actual_profit_afn: number;
    purchase_currency_code?: string | null;
    exchange_rate: number;
    purchase_date?: string | null;
};

type Kpis = {
    total_capital_afn: number;
    total_profit_afn: number;
    total_invested_afn: number;
    total_withdrawn_afn: number;
    total_profit_earned_actual_afn: number;
    total_profit_paid_afn: number;
    inventory_cost_afn: number;
    inventory_sale_value_afn: number;
    potential_profit_afn: number;
    inventory_quantity: number;
    inventory_batch_count: number;
    inventory_variant_count: number;
    total_investors: number;
};

type Props = {
    kpis: Kpis;
    kpis3c?: Record<string, Tri | null> | null;
    averageRates?: AverageRate[];
    inventoryItems?: InventoryItem[];
    monthly?: { month: string; deposits: number; profits: number; withdrawals: number }[];
    byCurrency?: {
        currency_code: string;
        capital_afn: number;
        profit_afn: number;
        capital_currency: number;
        profit_currency: number;
    }[];
    topInvestors?: { id: number; name: string; total_invested_afn: number; capital_afn: number; profit_afn: number }[];
    recentTransactions?: {
        id: number;
        date: string | null;
        investor: string | null;
        type: string;
        currency_code?: string | null;
        amount_afn: number;
        balance_after_afn: number;
    }[];
    pendingWithdrawals?: {
        id: number;
        withdrawal_number: string;
        investor: string | null;
        type: string;
        amount_afn: number;
        currency_code?: string | null;
        withdrawal_date: string | null;
    }[];
    investors?: { id: number; name: string }[];
    exchange?: unknown;
    own?: boolean;
    filters?: { investor_id?: number | null };
    dashboardPath?: string;
};

const afn = (n: number) => formatMoney(n, { code: 'AFN', symbol: '؋', decimal_places: 2 } as never);

function TriLine({ t }: { t?: Tri | null }) {
    if (!t) return null;
    return (
        <p className="mt-1 text-[11px] text-zinc-500">
            {t.usd != null ? `$${t.usd.toLocaleString()}` : '—'} · {t.aed != null ? `د.إ ${t.aed.toLocaleString()}` : '—'}
        </p>
    );
}

function KpiCard({
    label,
    value,
    sub,
    icon: Icon,
    tri,
}: {
    label: string;
    value: string;
    sub?: string;
    icon: LucideIcon;
    tri?: Tri | null;
}) {
    return (
        <div className="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs font-medium tracking-wide text-zinc-500 uppercase">{label}</p>
                    <p className="mt-2 truncate text-2xl font-bold text-zinc-900 dark:text-white">{value}</p>
                    <TriLine t={tri} />
                    {sub ? <p className="mt-1 text-xs text-zinc-500">{sub}</p> : null}
                </div>
                <div className="rounded-xl bg-zinc-100 p-2.5 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}

function Section({ title, children, action }: { title: string; children: React.ReactNode; action?: React.ReactNode }) {
    return (
        <section className="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <h2 className="text-sm font-semibold text-zinc-900 dark:text-white">{title}</h2>
                {action}
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

function AverageRatesPanel({ rows }: { rows: AverageRate[] }) {
    const { usdToAfn, aedToAfn } = useExchange();

    if (!rows.length) {
        return <p className="text-sm text-zinc-500">No investment FX history yet.</p>;
    }

    return (
        <div className="space-y-3">
            <p className="text-xs text-zinc-500">
                Weighted average = Σ amount_afn ÷ Σ amount_currency (cost basis). Board today:{' '}
                {usdToAfn ? `1 USD = ${usdToAfn.toLocaleString()} AFN` : 'USD —'} ·{' '}
                {aedToAfn ? `1 AED = ${aedToAfn.toLocaleString()} AFN` : 'AED —'}
            </p>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[640px] text-left text-sm">
                    <thead className="text-xs tracking-wide text-zinc-500 uppercase">
                        <tr className="border-b border-zinc-100 dark:border-zinc-800">
                            <th className="py-2 pr-3 font-medium">Currency</th>
                            <th className="py-2 pr-3 font-medium">Invested</th>
                            <th className="py-2 pr-3 font-medium">Historical AFN</th>
                            <th className="py-2 pr-3 font-medium">Avg rate</th>
                            <th className="py-2 pr-3 font-medium">Board</th>
                            <th className="py-2 font-medium">Δ vs board</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => {
                            const up = (r.rate_delta ?? 0) > 0;
                            const down = (r.rate_delta ?? 0) < 0;
                            return (
                                <tr key={r.currency_code} className="border-b border-zinc-50 last:border-0 dark:border-zinc-900">
                                    <td className="py-2.5 pr-3 font-medium">
                                        {r.currency_code}
                                        <span className="ml-2 text-xs text-zinc-400">{r.investments_count} tx</span>
                                    </td>
                                    <td className="py-2.5 pr-3">
                                        {r.total_currency.toLocaleString()} {r.currency_code}
                                    </td>
                                    <td className="py-2.5 pr-3">{afn(r.total_afn)}</td>
                                    <td className="py-2.5 pr-3 tabular-nums">
                                        {r.weighted_avg_rate != null ? r.weighted_avg_rate.toLocaleString(undefined, { maximumFractionDigits: 4 }) : '—'}
                                    </td>
                                    <td className="py-2.5 pr-3 tabular-nums">
                                        {r.board_rate != null ? r.board_rate.toLocaleString(undefined, { maximumFractionDigits: 4 }) : '—'}
                                    </td>
                                    <td
                                        className={`py-2.5 tabular-nums ${up ? 'text-emerald-600' : down ? 'text-rose-600' : 'text-zinc-500'}`}
                                    >
                                        {r.rate_delta == null
                                            ? '—'
                                            : `${up ? '+' : ''}${r.rate_delta.toLocaleString(undefined, { maximumFractionDigits: 4 })}${
                                                  r.rate_delta_pct != null ? ` (${up ? '+' : ''}${r.rate_delta_pct.toFixed(2)}%)` : ''
                                              }`}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function InventoryTable({ items }: { items: InventoryItem[] }) {
    const [q, setQ] = useState('');
    const filtered = useMemo(() => {
        const s = q.trim().toLowerCase();
        if (!s) return items;
        return items.filter(
            (i) =>
                i.product_name.toLowerCase().includes(s) ||
                (i.batch_code || '').toLowerCase().includes(s) ||
                (i.variant_name || '').toLowerCase().includes(s),
        );
    }, [items, q]);

    if (!items.length) {
        return <p className="text-sm text-zinc-500">No investor-funded batches yet. Select an investor to load the goods list.</p>;
    }

    return (
        <div className="space-y-3">
            <input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Filter products / batch…"
                className="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
            />
            <div className="overflow-x-auto">
                <table className="w-full min-w-[900px] text-left text-sm">
                    <thead className="text-xs tracking-wide text-zinc-500 uppercase">
                        <tr className="border-b border-zinc-100 dark:border-zinc-800">
                            <th className="py-2 pr-3 font-medium">Product</th>
                            <th className="py-2 pr-3 font-medium">Qty</th>
                            <th className="py-2 pr-3 font-medium">Cost / Sale</th>
                            <th className="py-2 pr-3 font-medium">Potential</th>
                            <th className="py-2 font-medium">Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((i) => (
                            <tr key={i.batch_id} className="border-b border-zinc-50 last:border-0 dark:border-zinc-900">
                                <td className="py-2.5 pr-3">
                                    <div className="font-medium text-zinc-900 dark:text-white">{i.product_name}</div>
                                    <div className="text-xs text-zinc-500">
                                        {i.batch_code}
                                        {i.volume_ml ? ` · ${i.volume_ml}ml` : ''}
                                        {i.concentration ? ` · ${i.concentration}` : ''}
                                        {i.purchase_currency_code ? ` · ${i.purchase_currency_code}` : ''}
                                    </div>
                                </td>
                                <td className="py-2.5 pr-3 tabular-nums">
                                    {i.remaining_quantity}/{i.quantity}
                                    <div className="text-xs text-zinc-500">sold {i.sold_quantity}</div>
                                </td>
                                <td className="py-2.5 pr-3 tabular-nums">
                                    {afn(i.unit_cost_afn)}
                                    <div className="text-xs text-zinc-500">sale {afn(i.sale_price_afn)}</div>
                                </td>
                                <td className="py-2.5 pr-3 tabular-nums text-amber-700 dark:text-amber-400">
                                    {afn(i.potential_profit_afn)}
                                </td>
                                <td className="py-2.5 tabular-nums text-emerald-700 dark:text-emerald-400">
                                    {afn(i.actual_profit_afn)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="text-xs text-zinc-500">
                Showing {filtered.length} of {items.length} batches · profit earned is sale-time only
            </p>
        </div>
    );
}

export default function InvestorDashboard({
    kpis,
    kpis3c = null,
    averageRates = [],
    inventoryItems = [],
    monthly = [],
    byCurrency = [],
    topInvestors = [],
    recentTransactions = [],
    pendingWithdrawals = [],
    investors = [],
    own = false,
    filters = {},
    dashboardPath = '/investors/dashboard',
}: Props) {
    const selectedInvestor = filters.investor_id ?? '';

    const onInvestorChange = (id: string) => {
        router.get(
            dashboardPath,
            id ? { investor_id: id } : {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const body = (
        <div className="mx-auto flex max-w-7xl flex-col gap-6 p-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">
                        {own ? 'My investor dashboard' : 'Investor dashboard'}
                    </h1>
                    <p className="text-sm text-zinc-500">
                        Capital, sale-time profit, inventory, and weighted FX averages
                    </p>
                </div>
                {!own && investors.length > 0 ? (
                    <select
                        value={selectedInvestor || ''}
                        onChange={(e) => onInvestorChange(e.target.value)}
                        className="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <option value="">All investors</option>
                        {investors.map((inv) => (
                            <option key={inv.id} value={inv.id}>
                                {inv.name}
                            </option>
                        ))}
                    </select>
                ) : null}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Capital (wallet)"
                    value={afn(kpis.total_capital_afn)}
                    tri={kpis3c?.total_capital}
                    sub={`Invested total ${afn(kpis.total_invested_afn)}`}
                    icon={Wallet}
                />
                <KpiCard
                    label="Profit earned (sales)"
                    value={afn(kpis.total_profit_earned_actual_afn)}
                    tri={kpis3c?.total_profit_earned_actual}
                    sub={`Wallet profit ${afn(kpis.total_profit_afn)} · paid ${afn(kpis.total_profit_paid_afn)}`}
                    icon={TrendingUp}
                />
                <KpiCard
                    label="Inventory at cost"
                    value={afn(kpis.inventory_cost_afn)}
                    tri={kpis3c?.inventory_cost}
                    sub={`${kpis.inventory_quantity} pcs · ${kpis.inventory_batch_count} batches`}
                    icon={Package}
                />
                <KpiCard
                    label="Potential profit"
                    value={afn(kpis.potential_profit_afn)}
                    tri={kpis3c?.potential_profit}
                    sub={`Sale value ${afn(kpis.inventory_sale_value_afn)}`}
                    icon={PiggyBank}
                />
            </div>

            <Section title="Weighted average FX (investment cost basis)">
                <AverageRatesPanel rows={averageRates} />
            </Section>

            <Section
                title="Investor goods"
                action={
                    <span className="text-xs text-zinc-500">
                        {kpis.inventory_variant_count} variants · withdrawn {afn(kpis.total_withdrawn_afn)}
                    </span>
                }
            >
                <InventoryTable items={inventoryItems} />
            </Section>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Section title="Wallet by currency">
                    {byCurrency.length === 0 ? (
                        <p className="text-sm text-zinc-500">No wallet rows.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {byCurrency.map((c) => (
                                <li key={c.currency_code} className="flex items-center justify-between gap-3">
                                    <span className="font-medium">{c.currency_code}</span>
                                    <span className="tabular-nums text-zinc-600 dark:text-zinc-300">
                                        cap {c.capital_currency.toLocaleString()} · profit {c.profit_currency.toLocaleString()}
                                        <span className="ml-2 text-xs text-zinc-400">
                                            ({afn(c.capital_afn + c.profit_afn)})
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>

                <Section title="Last 12 months">
                    {monthly.length === 0 ? (
                        <p className="text-sm text-zinc-500">No monthly activity.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {monthly.map((m) => (
                                <li key={m.month} className="flex items-center justify-between gap-3">
                                    <span className="font-medium">{m.month}</span>
                                    <span className="tabular-nums text-zinc-600 dark:text-zinc-300">
                                        +{afn(m.deposits)} · profit {afn(m.profits)} · out {afn(m.withdrawals)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </div>

            {!own ? (
                <Section title="Top investors" action={<Users className="h-4 w-4 text-zinc-400" />}>
                    {topInvestors.length === 0 ? (
                        <p className="text-sm text-zinc-500">No investors.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {topInvestors.map((t) => (
                                <li key={t.id} className="flex items-center justify-between gap-3">
                                    <Link
                                        href={`${dashboardPath}?investor_id=${t.id}`}
                                        className="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                    >
                                        {t.name}
                                    </Link>
                                    <span className="tabular-nums">
                                        {afn(t.total_invested_afn)} · profit {afn(t.profit_afn)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            ) : null}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Section title="Recent transactions" action={<LineChart className="h-4 w-4 text-zinc-400" />}>
                    {recentTransactions.length === 0 ? (
                        <p className="text-sm text-zinc-500">No transactions.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {recentTransactions.map((t) => (
                                <li key={t.id} className="flex items-center justify-between gap-3">
                                    <span>
                                        <span className="font-medium">{t.type}</span>
                                        <span className="text-zinc-500"> · {t.investor || '—'}</span>
                                        <span className="block text-xs text-zinc-400">{t.date}</span>
                                    </span>
                                    <span className="tabular-nums">{afn(t.amount_afn)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>

                <Section title="Pending withdrawals" action={<ArrowDownToLine className="h-4 w-4 text-zinc-400" />}>
                    {pendingWithdrawals.length === 0 ? (
                        <p className="text-sm text-zinc-500">No pending withdrawals.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {pendingWithdrawals.map((w) => (
                                <li key={w.id} className="flex items-center justify-between gap-3">
                                    <span>
                                        <span className="font-medium">{w.withdrawal_number}</span>
                                        <span className="text-zinc-500"> · {w.investor || '—'}</span>
                                        <span className="block text-xs text-zinc-400">
                                            {w.type} · {w.withdrawal_date}
                                        </span>
                                    </span>
                                    <span className="tabular-nums">{afn(w.amount_afn)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </div>
        </div>
    );

    if (own) {
        return (
            <>
                <Head title="My dashboard" />
                <div className="bg-zinc-50 min-h-screen dark:bg-zinc-950">{body}</div>
            </>
        );
    }

    return (
        <AppLayout breadcrumbs={[{ title: 'Investors', href: '/investors' }, { title: 'Dashboard', href: dashboardPath }]}>
            <Head title="Investor dashboard" />
            {body}
        </AppLayout>
    );
}
