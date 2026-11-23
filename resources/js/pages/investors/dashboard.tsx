import AppLayout from '@/layouts/app-layout';
import { useExchange } from '@/hooks/useExchange';
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
import { useMemo, useState, type ReactNode } from 'react';

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

const nf = (n: number, digits = 2) =>
    n.toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits });

/** Resolve AFN → { afn, usd, aed } using server Tri or live board rates. */
function useTriFromAfn(afnAmount: number, tri?: Tri | null): Tri {
    const { usdToAfn, aedToAfn } = useExchange();
    if (tri) {
        return {
            afn: tri.afn,
            usd: tri.usd,
            aed: tri.aed,
        };
    }
    return {
        afn: afnAmount,
        usd: usdToAfn && usdToAfn > 0 ? afnAmount / usdToAfn : null,
        aed: aedToAfn && aedToAfn > 0 ? afnAmount / aedToAfn : null,
    };
}

/**
 * Every amount: USD large on top, AFN + AED smaller below.
 * Uses Latin currency codes only (no Arabic-script glyphs).
 */
function AmountStack({
    afnAmount,
    tri,
    size = 'md',
}: {
    afnAmount: number;
    tri?: Tri | null;
    size?: 'sm' | 'md' | 'lg';
}) {
    const t = useTriFromAfn(afnAmount, tri);
    const primary =
        size === 'lg' ? 'text-2xl font-bold' : size === 'sm' ? 'text-sm font-semibold' : 'text-base font-bold';

    return (
        <div className="min-w-0">
            <p className={`truncate tabular-nums text-zinc-900 dark:text-white ${primary}`}>
                {t.usd != null ? `$${nf(t.usd)}` : '—'}
                <span className="ml-1 text-[10px] font-medium tracking-wide text-zinc-400 uppercase">USD</span>
            </p>
            <p className="mt-0.5 truncate text-[11px] tabular-nums text-zinc-500">
                AFN {nf(t.afn)} · AED {t.aed != null ? nf(t.aed) : '—'}
            </p>
        </div>
    );
}

function KpiCard({
    label,
    afnAmount,
    tri,
    sub,
    icon: Icon,
}: {
    label: string;
    afnAmount: number;
    tri?: Tri | null;
    sub?: ReactNode;
    icon: LucideIcon;
}) {
    return (
        <div className="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs font-medium tracking-wide text-zinc-500 uppercase">{label}</p>
                    <div className="mt-2">
                        <AmountStack afnAmount={afnAmount} tri={tri} size="lg" />
                    </div>
                    {sub ? <div className="mt-2 text-xs text-zinc-500">{sub}</div> : null}
                </div>
                <div className="rounded-xl bg-zinc-100 p-2.5 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}

function Section({ title, children, action }: { title: string; children: ReactNode; action?: ReactNode }) {
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

function AverageRatesPanel({ rows = [] }: { rows?: AverageRate[] | null }) {
    const { usdToAfn, aedToAfn } = useExchange();
    const list = rows ?? [];

    if (!list.length) {
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
                        {list.map((r) => {
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
                                    <td className="py-2.5 pr-3">
                                        <AmountStack afnAmount={r.total_afn} size="sm" />
                                    </td>
                                    <td className="py-2.5 pr-3 tabular-nums">
                                        {r.weighted_avg_rate != null
                                            ? r.weighted_avg_rate.toLocaleString(undefined, { maximumFractionDigits: 4 })
                                            : '—'}
                                    </td>
                                    <td className="py-2.5 pr-3 tabular-nums">
                                        {r.board_rate != null
                                            ? r.board_rate.toLocaleString(undefined, { maximumFractionDigits: 4 })
                                            : '—'}
                                    </td>
                                    <td
                                        className={`py-2.5 tabular-nums ${up ? 'text-emerald-600' : down ? 'text-rose-600' : 'text-zinc-500'}`}
                                    >
                                        {r.rate_delta == null
                                            ? '—'
                                            : `${up ? '+' : ''}${r.rate_delta.toLocaleString(undefined, { maximumFractionDigits: 4 })}${
                                                  r.rate_delta_pct != null
                                                      ? ` (${up ? '+' : ''}${r.rate_delta_pct.toFixed(2)}%)`
                                                      : ''
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

function InventoryTable({ items = [] }: { items?: InventoryItem[] | null }) {
    const safeItems = items ?? [];
    const [q, setQ] = useState('');
    const filtered = useMemo(() => {
        const s = q.trim().toLowerCase();
        if (!s) return safeItems;
        return safeItems.filter(
            (i) =>
                i.product_name.toLowerCase().includes(s) ||
                (i.batch_code || '').toLowerCase().includes(s) ||
                (i.variant_name || '').toLowerCase().includes(s),
        );
    }, [safeItems, q]);

    if (!safeItems.length) {
        return (
            <p className="text-sm text-zinc-500">
                No investor-funded batches yet. Select an investor to load the goods list.
            </p>
        );
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
                                <td className="py-2.5 pr-3">
                                    <AmountStack afnAmount={i.unit_cost_afn} size="sm" />
                                    <div className="mt-1">
                                        <p className="text-[10px] text-zinc-400 uppercase">Sale</p>
                                        <AmountStack afnAmount={i.sale_price_afn} size="sm" />
                                    </div>
                                </td>
                                <td className="py-2.5 pr-3 text-amber-700 dark:text-amber-400">
                                    <AmountStack afnAmount={i.potential_profit_afn} size="sm" />
                                </td>
                                <td className="py-2.5 text-emerald-700 dark:text-emerald-400">
                                    <AmountStack afnAmount={i.actual_profit_afn} size="sm" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="text-xs text-zinc-500">
                Showing {filtered.length} of {safeItems.length} batches · profit earned is sale-time only
            </p>
        </div>
    );
}

function InlineSubAmount({ label, afnAmount, tri }: { label: string; afnAmount: number; tri?: Tri | null }) {
    return (
        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
            <span>{label}</span>
            <AmountStack afnAmount={afnAmount} tri={tri} size="sm" />
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
                        Amounts show USD first, with AFN and AED equivalents below
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
                    afnAmount={kpis.total_capital_afn}
                    tri={kpis3c?.total_capital}
                    sub={
                        <InlineSubAmount
                            label="Invested total"
                            afnAmount={kpis.total_invested_afn}
                            tri={kpis3c?.total_invested}
                        />
                    }
                    icon={Wallet}
                />
                <KpiCard
                    label="Profit earned (sales)"
                    afnAmount={kpis.total_profit_earned_actual_afn}
                    tri={kpis3c?.total_profit_earned_actual}
                    sub={
                        <div className="space-y-1">
                            <InlineSubAmount
                                label="Wallet profit"
                                afnAmount={kpis.total_profit_afn}
                                tri={kpis3c?.total_profit}
                            />
                            <InlineSubAmount
                                label="Paid"
                                afnAmount={kpis.total_profit_paid_afn}
                                tri={kpis3c?.total_profit_paid}
                            />
                        </div>
                    }
                    icon={TrendingUp}
                />
                <KpiCard
                    label="Inventory at cost"
                    afnAmount={kpis.inventory_cost_afn}
                    tri={kpis3c?.inventory_cost}
                    sub={`${kpis.inventory_quantity} pcs · ${kpis.inventory_batch_count} batches`}
                    icon={Package}
                />
                <KpiCard
                    label="Potential profit"
                    afnAmount={kpis.potential_profit_afn}
                    tri={kpis3c?.potential_profit}
                    sub={
                        <InlineSubAmount
                            label="Sale value"
                            afnAmount={kpis.inventory_sale_value_afn}
                            tri={kpis3c?.inventory_sale_value}
                        />
                    }
                    icon={PiggyBank}
                />
            </div>

            <Section title="Weighted average FX (investment cost basis)">
                <AverageRatesPanel rows={averageRates} />
            </Section>

            <Section
                title="Investor goods"
                action={
                    <span className="flex items-center gap-2 text-xs text-zinc-500">
                        <span>{kpis.inventory_variant_count} variants</span>
                        <span>·</span>
                        <span className="inline-flex items-center gap-1">
                            withdrawn
                            <AmountStack
                                afnAmount={kpis.total_withdrawn_afn}
                                tri={kpis3c?.total_withdrawn}
                                size="sm"
                            />
                        </span>
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
                        <ul className="space-y-3 text-sm">
                            {byCurrency.map((c) => (
                                <li key={c.currency_code} className="flex items-start justify-between gap-3">
                                    <span className="font-medium">{c.currency_code}</span>
                                    <div className="text-right">
                                        <p className="text-xs text-zinc-500">
                                            Original: cap {c.capital_currency.toLocaleString()} · profit{' '}
                                            {c.profit_currency.toLocaleString()}
                                        </p>
                                        <AmountStack afnAmount={c.capital_afn + c.profit_afn} size="sm" />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>

                <Section title="Last 12 months">
                    {monthly.length === 0 ? (
                        <p className="text-sm text-zinc-500">No monthly activity.</p>
                    ) : (
                        <ul className="space-y-3 text-sm">
                            {monthly.map((m) => (
                                <li key={m.month} className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <span className="font-medium">{m.month}</span>
                                    <div className="flex flex-wrap gap-4 sm:justify-end">
                                        <div>
                                            <p className="text-[10px] text-zinc-400 uppercase">Deposits</p>
                                            <AmountStack afnAmount={m.deposits} size="sm" />
                                        </div>
                                        <div>
                                            <p className="text-[10px] text-zinc-400 uppercase">Profit</p>
                                            <AmountStack afnAmount={m.profits} size="sm" />
                                        </div>
                                        <div>
                                            <p className="text-[10px] text-zinc-400 uppercase">Out</p>
                                            <AmountStack afnAmount={m.withdrawals} size="sm" />
                                        </div>
                                    </div>
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
                        <ul className="space-y-3 text-sm">
                            {topInvestors.map((t) => (
                                <li key={t.id} className="flex items-start justify-between gap-3">
                                    <Link
                                        href={`${dashboardPath}?investor_id=${t.id}`}
                                        className="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                    >
                                        {t.name}
                                    </Link>
                                    <div className="text-right">
                                        <AmountStack afnAmount={t.total_invested_afn} size="sm" />
                                        <div className="mt-1">
                                            <p className="text-[10px] text-zinc-400 uppercase">Profit</p>
                                            <AmountStack afnAmount={t.profit_afn} size="sm" />
                                        </div>
                                    </div>
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
                        <ul className="space-y-3 text-sm">
                            {recentTransactions.map((t) => (
                                <li key={t.id} className="flex items-start justify-between gap-3">
                                    <span>
                                        <span className="font-medium">{t.type}</span>
                                        <span className="text-zinc-500"> · {t.investor || '—'}</span>
                                        <span className="block text-xs text-zinc-400">{t.date}</span>
                                    </span>
                                    <AmountStack afnAmount={t.amount_afn} size="sm" />
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>

                <Section title="Pending withdrawals" action={<ArrowDownToLine className="h-4 w-4 text-zinc-400" />}>
                    {pendingWithdrawals.length === 0 ? (
                        <p className="text-sm text-zinc-500">No pending withdrawals.</p>
                    ) : (
                        <ul className="space-y-3 text-sm">
                            {pendingWithdrawals.map((w) => (
                                <li key={w.id} className="flex items-start justify-between gap-3">
                                    <span>
                                        <span className="font-medium">{w.withdrawal_number}</span>
                                        <span className="text-zinc-500"> · {w.investor || '—'}</span>
                                        <span className="block text-xs text-zinc-400">
                                            {w.type} · {w.withdrawal_date}
                                        </span>
                                    </span>
                                    <AmountStack afnAmount={w.amount_afn} size="sm" />
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
