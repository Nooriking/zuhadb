import { MoneyField } from '@/components/money-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useExchange } from '@/hooks/useExchange';
import AppLayout from '@/layouts/app-layout';
import { defaultCurrencyId, type CatalogCurrency } from '@/lib/currencies';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowLeft, Package, PiggyBank, TrendingUp, Wallet } from 'lucide-react';
import { useState, type ReactNode } from 'react';

type Tri = { afn: number; usd: number | null; aed: number | null };

type InvestorProfile = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    status: string;
    created_at: string | null;
};

type Totals = {
    capital_afn: number;
    profit_afn: number;
    invested_afn: number;
    withdrawn_afn: number;
};

type Inventory = {
    quantity: number;
    cost_afn: number;
    sale_value_afn: number;
    batch_count: number;
    potential_profit_afn: number;
};

type WalletRow = {
    currency_code: string;
    currency_symbol: string | null;
    capital_currency: number;
    capital_afn: number;
    profit_currency: number;
    profit_afn: number;
};

type InvestmentRow = {
    id: number;
    currency_code: string | null;
    exchange_rate: number;
    amount_currency: number;
    amount_afn: number;
    investment_date: string | null;
    status: string;
    batches_count: number;
};

type TransactionRow = {
    id: number;
    date: string | null;
    type: string;
    currency_code: string | null;
    amount_afn: number;
    balance_after_afn: number;
    description: string | null;
};

type WithdrawalRow = {
    id: number;
    withdrawal_number: string;
    type: string;
    currency_code: string | null;
    amount_afn: number;
    withdrawal_date: string | null;
    status: string;
};

type Props = {
    investor: InvestorProfile;
    totals: Totals;
    inventory: Inventory;
    walletByCurrency: WalletRow[];
    investments: InvestmentRow[];
    transactions: TransactionRow[];
    withdrawals: WithdrawalRow[];
    currencies?: CatalogCurrency[];
};

const breadcrumbs = (investor: InvestorProfile): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
    { title: investor.name, href: `/investors/${investor.id}` },
];

const nf = (n: number, digits = 2) =>
    n.toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits });

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const typeLabel = (type: string) =>
    type
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');

function useTriFromAfn(afnAmount: number): Tri {
    const { usdToAfn, aedToAfn } = useExchange();
    return {
        afn: afnAmount,
        usd: usdToAfn && usdToAfn > 0 ? afnAmount / usdToAfn : null,
        aed: aedToAfn && aedToAfn > 0 ? afnAmount / aedToAfn : null,
    };
}

/** USD large (blue) on top; AFN + AED smaller grey below. */
function AmountStack({ afnAmount, size = 'md' }: { afnAmount: number; size?: 'sm' | 'md' | 'lg' }) {
    const t = useTriFromAfn(afnAmount);
    const primary =
        size === 'lg' ? 'text-2xl font-bold' : size === 'sm' ? 'text-sm font-semibold' : 'text-base font-bold';

    return (
        <div className="min-w-0">
            <p className={`truncate tabular-nums text-blue-600 dark:text-blue-400 ${primary}`}>
                {t.usd != null ? `$${nf(t.usd)}` : '—'}
                <span className="ml-1 text-[10px] font-medium tracking-wide text-blue-400/80 uppercase">USD</span>
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
    icon: Icon,
}: {
    label: string;
    afnAmount: number;
    icon: LucideIcon;
}) {
    return (
        <div className="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs font-medium tracking-wide text-zinc-500 uppercase">{label}</p>
                    <div className="mt-2">
                        <AmountStack afnAmount={afnAmount} size="lg" />
                    </div>
                </div>
                <div className="rounded-xl bg-zinc-100 p-2.5 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}

function EmptyState() {
    return <p className="py-8 text-center text-sm text-zinc-500">No data yet</p>;
}

function StatusBadge({ status }: { status: string }) {
    const map: Record<string, { variant: 'success' | 'outline' | 'destructive' | 'secondary'; label: string }> = {
        active: { variant: 'success', label: 'Active' },
        inactive: { variant: 'outline', label: 'Inactive' },
        closed: { variant: 'secondary', label: 'Closed' },
        pending: { variant: 'outline', label: 'Pending' },
        approved: { variant: 'secondary', label: 'Approved' },
        paid: { variant: 'success', label: 'Paid' },
        rejected: { variant: 'destructive', label: 'Rejected' },
    };
    const item = map[status] || { variant: 'outline' as const, label: status };
    return <Badge variant={item.variant}>{item.label}</Badge>;
}

type TabId = 'investments' | 'transactions' | 'withdrawals';

export default function InvestorsShow({
    investor,
    totals,
    inventory,
    walletByCurrency,
    investments,
    transactions,
    withdrawals,
    currencies = [],
}: Props) {
    const [tab, setTab] = useState<TabId>('investments');
    const [capitalOpen, setCapitalOpen] = useState(false);
    const [capitalForm, setCapitalForm] = useState({
        capital_amount: '',
        currency_id: defaultCurrencyId(currencies),
        investment_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });
    const [capitalErrors, setCapitalErrors] = useState<Record<string, string>>({});
    const [capitalLoading, setCapitalLoading] = useState(false);

    const openCapital = () => {
        setCapitalErrors({});
        setCapitalForm({
            capital_amount: '',
            currency_id: defaultCurrencyId(currencies),
            investment_date: new Date().toISOString().slice(0, 10),
            notes: '',
        });
        setCapitalOpen(true);
    };

    const submitCapital = (e: React.FormEvent) => {
        e.preventDefault();
        setCapitalLoading(true);
        setCapitalErrors({});
        router.post(
            `/investors/${investor.id}/capital`,
            {
                source_type: 'cash',
                currency_id: capitalForm.currency_id,
                capital_amount: capitalForm.capital_amount,
                investment_date: capitalForm.investment_date,
                notes: capitalForm.notes,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCapitalOpen(false);
                    setCapitalLoading(false);
                },
                onError: (errs) => {
                    setCapitalErrors(errs);
                    setCapitalLoading(false);
                },
            },
        );
    };

    const tabs: { id: TabId; label: string; count: number }[] = [
        { id: 'investments', label: 'Investments', count: investments.length },
        { id: 'transactions', label: 'Transactions', count: transactions.length },
        { id: 'withdrawals', label: 'Withdrawals', count: withdrawals.length },
    ];

    const hasInventory = inventory.batch_count > 0 || inventory.quantity > 0;
    const hasWallet = walletByCurrency.length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs(investor)}>
            <Head title={investor.name} />

            <div className="mx-auto flex max-w-6xl flex-col gap-6 p-6">
                {/* ─── 1. Profile ─── */}
                <section className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-4">
                        <Link
                            href="/investors"
                            className="mt-1 rounded-lg border border-zinc-200 p-2 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">{investor.name}</h1>
                                <StatusBadge status={investor.status} />
                            </div>
                            <p className="mt-1 text-sm text-zinc-500">
                                {[investor.phone, investor.email].filter(Boolean).join(' · ') || 'No contact info'}
                            </p>
                            {investor.created_at ? (
                                <p className="mt-0.5 text-xs text-zinc-400">Since {formatDate(investor.created_at)}</p>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/users/${investor.id}/edit`}>Edit</Link>
                        </Button>
                        {currencies.length > 0 ? (
                            <Button onClick={openCapital}>Add capital</Button>
                        ) : null}
                    </div>
                </section>

                {/* Wallet by currency + inventory strip (only when data exists) */}
                {(hasWallet || hasInventory) && (
                    <section className="grid gap-3 sm:grid-cols-2">
                        {hasWallet ? (
                            <div className="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                <p className="text-xs font-medium tracking-wide text-zinc-500 uppercase">Wallet by currency</p>
                                <div className="mt-3 space-y-3">
                                    {walletByCurrency.map((w) => (
                                        <div
                                            key={w.currency_code}
                                            className="flex items-start justify-between gap-3 border-b border-zinc-100 pb-3 last:border-0 last:pb-0 dark:border-zinc-800"
                                        >
                                            <span className="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                                {w.currency_code}
                                            </span>
                                            <div className="text-right">
                                                <p className="text-xs text-zinc-500">Capital</p>
                                                <AmountStack afnAmount={w.capital_afn} size="sm" />
                                                <p className="mt-1 text-xs text-zinc-500">Profit</p>
                                                <AmountStack afnAmount={w.profit_afn} size="sm" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : null}

                        {hasInventory ? (
                            <div className="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                <div className="flex items-center gap-2">
                                    <Package className="h-4 w-4 text-zinc-500" />
                                    <p className="text-xs font-medium tracking-wide text-zinc-500 uppercase">Active inventory</p>
                                </div>
                                <p className="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                                    {inventory.quantity} units · {inventory.batch_count} batches
                                </p>
                                <div className="mt-3 grid grid-cols-2 gap-3">
                                    <div>
                                        <p className="text-xs text-zinc-500">Cost</p>
                                        <AmountStack afnAmount={inventory.cost_afn} size="sm" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-zinc-500">Sale value</p>
                                        <AmountStack afnAmount={inventory.sale_value_afn} size="sm" />
                                    </div>
                                    <div className="col-span-2">
                                        <p className="text-xs text-zinc-500">Potential profit</p>
                                        <AmountStack afnAmount={inventory.potential_profit_afn} size="sm" />
                                    </div>
                                </div>
                            </div>
                        ) : null}
                    </section>
                )}

                {/* ─── 2. KPI summary (4 cards) ─── */}
                <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard label="Capital" afnAmount={totals.capital_afn} icon={Wallet} />
                    <KpiCard label="Profit" afnAmount={totals.profit_afn} icon={TrendingUp} />
                    <KpiCard label="Invested" afnAmount={totals.invested_afn} icon={PiggyBank} />
                    <KpiCard label="Withdrawn" afnAmount={totals.withdrawn_afn} icon={Wallet} />
                </section>

                {/* ─── 3. Tabs ─── */}
                <section className="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <div className="flex gap-1 overflow-x-auto border-b border-zinc-100 px-3 pt-3 dark:border-zinc-800">
                        {tabs.map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                onClick={() => setTab(t.id)}
                                className={`rounded-t-lg px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors ${
                                    tab === t.id
                                        ? 'border-b-2 border-blue-600 text-blue-600'
                                        : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'
                                }`}
                            >
                                {t.label}
                                <span className="ml-1.5 text-xs text-zinc-400">({t.count})</span>
                            </button>
                        ))}
                    </div>

                    <div className="p-4">
                        {tab === 'investments' && (
                            <InvestmentsTable rows={investments} />
                        )}
                        {tab === 'transactions' && (
                            <TransactionsTable rows={transactions} />
                        )}
                        {tab === 'withdrawals' && (
                            <WithdrawalsTable rows={withdrawals} />
                        )}
                    </div>
                </section>
            </div>

            <Dialog open={capitalOpen} onOpenChange={setCapitalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add capital</DialogTitle>
                        <DialogDescription>Record a cash capital deposit for {investor.name}.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCapital} className="space-y-4">
                        <MoneyField
                            label="Amount"
                            currencies={currencies}
                            currencyId={String(capitalForm.currency_id)}
                            amount={capitalForm.capital_amount}
                            onCurrencyChange={(id) => setCapitalForm((f) => ({ ...f, currency_id: id }))}
                            onAmountChange={(v) => setCapitalForm((f) => ({ ...f, capital_amount: v }))}
                            error={capitalErrors.capital_amount || capitalErrors.currency_id}
                        />
                        <div className="space-y-2">
                            <Label htmlFor="investment_date">Date</Label>
                            <Input
                                id="investment_date"
                                type="date"
                                value={capitalForm.investment_date}
                                onChange={(e) => setCapitalForm((f) => ({ ...f, investment_date: e.target.value }))}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Input
                                id="notes"
                                value={capitalForm.notes}
                                onChange={(e) => setCapitalForm((f) => ({ ...f, notes: e.target.value }))}
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCapitalOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={capitalLoading}>
                                {capitalLoading ? 'Saving…' : 'Save'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function TableShell({ children, empty }: { children: ReactNode; empty: boolean }) {
    if (empty) return <EmptyState />;
    return <div className="overflow-x-auto">{children}</div>;
}

function InvestmentsTable({ rows }: { rows: InvestmentRow[] }) {
    return (
        <TableShell empty={rows.length === 0}>
            <table className="w-full min-w-[640px] text-left text-sm">
                <thead className="text-xs tracking-wide text-zinc-500 uppercase">
                    <tr className="border-b border-zinc-100 dark:border-zinc-800">
                        <th className="py-2 pr-3 font-medium">Date</th>
                        <th className="py-2 pr-3 font-medium">Amount</th>
                        <th className="py-2 pr-3 font-medium">Currency</th>
                        <th className="py-2 pr-3 font-medium">Batches</th>
                        <th className="py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.id} className="border-b border-zinc-50 last:border-0 dark:border-zinc-900">
                            <td className="py-2.5 pr-3 text-zinc-600">{formatDate(r.investment_date)}</td>
                            <td className="py-2.5 pr-3">
                                <AmountStack afnAmount={r.amount_afn} size="sm" />
                                {r.currency_code && r.currency_code !== 'AFN' ? (
                                    <p className="mt-0.5 text-[11px] text-zinc-400">
                                        {nf(r.amount_currency)} {r.currency_code}
                                        {r.exchange_rate > 0 ? ` @ ${nf(r.exchange_rate, 4)}` : ''}
                                    </p>
                                ) : null}
                            </td>
                            <td className="py-2.5 pr-3">{r.currency_code ?? '—'}</td>
                            <td className="py-2.5 pr-3 tabular-nums">{r.batches_count}</td>
                            <td className="py-2.5">
                                <StatusBadge status={r.status} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </TableShell>
    );
}

function TransactionsTable({ rows }: { rows: TransactionRow[] }) {
    return (
        <TableShell empty={rows.length === 0}>
            <table className="w-full min-w-[700px] text-left text-sm">
                <thead className="text-xs tracking-wide text-zinc-500 uppercase">
                    <tr className="border-b border-zinc-100 dark:border-zinc-800">
                        <th className="py-2 pr-3 font-medium">Date</th>
                        <th className="py-2 pr-3 font-medium">Type</th>
                        <th className="py-2 pr-3 font-medium">Amount</th>
                        <th className="py-2 pr-3 font-medium">Balance after</th>
                        <th className="py-2 font-medium">Note</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.id} className="border-b border-zinc-50 last:border-0 dark:border-zinc-900">
                            <td className="py-2.5 pr-3 text-zinc-600">{formatDate(r.date)}</td>
                            <td className="py-2.5 pr-3">
                                <span className="font-medium text-zinc-800 dark:text-zinc-200">{typeLabel(r.type)}</span>
                                {r.currency_code ? (
                                    <span className="ml-1.5 text-xs text-zinc-400">{r.currency_code}</span>
                                ) : null}
                            </td>
                            <td className="py-2.5 pr-3">
                                <AmountStack afnAmount={r.amount_afn} size="sm" />
                            </td>
                            <td className="py-2.5 pr-3">
                                <AmountStack afnAmount={r.balance_after_afn} size="sm" />
                            </td>
                            <td className="max-w-[200px] truncate py-2.5 text-zinc-500">{r.description || '—'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </TableShell>
    );
}

function WithdrawalsTable({ rows }: { rows: WithdrawalRow[] }) {
    return (
        <TableShell empty={rows.length === 0}>
            <table className="w-full min-w-[640px] text-left text-sm">
                <thead className="text-xs tracking-wide text-zinc-500 uppercase">
                    <tr className="border-b border-zinc-100 dark:border-zinc-800">
                        <th className="py-2 pr-3 font-medium">Date</th>
                        <th className="py-2 pr-3 font-medium">Number</th>
                        <th className="py-2 pr-3 font-medium">Type</th>
                        <th className="py-2 pr-3 font-medium">Amount</th>
                        <th className="py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.id} className="border-b border-zinc-50 last:border-0 dark:border-zinc-900">
                            <td className="py-2.5 pr-3 text-zinc-600">{formatDate(r.withdrawal_date)}</td>
                            <td className="py-2.5 pr-3 font-medium">{r.withdrawal_number || `#${r.id}`}</td>
                            <td className="py-2.5 pr-3 capitalize">
                                {r.type}
                                {r.currency_code ? (
                                    <span className="ml-1.5 text-xs text-zinc-400">{r.currency_code}</span>
                                ) : null}
                            </td>
                            <td className="py-2.5 pr-3">
                                <AmountStack afnAmount={r.amount_afn} size="sm" />
                            </td>
                            <td className="py-2.5">
                                <StatusBadge status={r.status} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </TableShell>
    );
}
