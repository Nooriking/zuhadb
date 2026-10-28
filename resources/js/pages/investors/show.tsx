// resources/js/pages/investors/show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Banknote, Calendar, Edit, Mail, MapPin, Package, Phone, TrendingUp, User as UserIcon, Wallet } from 'lucide-react';
import { useState } from 'react';

type MoneyCurrency = { id?: number; code: string; name?: string; symbol: string | null; decimal_places?: number } | null;

interface Wallet {
    id: number;
    capital_balance_currency: string | null;
    capital_balance_afn: string;
    profit_balance_currency: string | null;
    profit_balance_afn: string;
    total_invested_afn: string;
    total_profit_earned_afn: string;
    total_withdrawn_afn: string;
    last_transaction_at: string | null;
    currency: MoneyCurrency;
}

interface Investment {
    id: number;
    investment_number: string;
    capital_amount_currency: string | null;
    capital_amount_afn: string;
    available_amount_afn?: string;
    allocated_amount_afn?: string;
    profit_amount_currency?: string | null;
    profit_amount_afn: string;
    withdrawn_amount_afn?: string;
    source_type?: string | null;
    exchange_rate?: string | null;
    investment_date: string;
    status: string;
    currency: MoneyCurrency;
    currency_code?: string | null;
    currency_symbol?: string | null;
}

interface Withdrawal {
    id: number;
    withdrawal_number: string;
    type: 'profit' | 'capital';
    amount_currency: string | null;
    amount_afn: string;
    status: 'pending' | 'approved' | 'paid' | 'rejected';
    withdrawal_date: string;
    currency: MoneyCurrency;
    exchange_rate?: string | null;
}

interface Transaction {
    id: number;
    transaction_number: string;
    type: string;
    amount_currency?: string | null;
    amount_afn: string;
    balance_type: 'capital' | 'profit';
    description: string | null;
    transaction_date: string;
    currency: MoneyCurrency;
    exchange_rate?: string | null;
}

interface ProfitEntry {
    id: number;
    profit_amount_currency: string | null;
    profit_amount_afn: string;
    status: string;
    entry_type?: string | null;
    earned_at: string | null;
    paid_at: string | null;
    sale_id?: number | null;
    sale?: { id: number; invoice_number: string | null; sale_date?: string | null } | null;
    currency: MoneyCurrency;
    exchange_rate?: string | null;
}

interface Batch {
    id: number;
    batch_code: string;
    quantity: number;
    remaining_quantity: number;
    unit_cost_afn: string;
    profit_per_unit_afn: string;
    status: string;
    received_date: string;
    variant: {
        id: number;
        name: string | null;
        product: { id: number; name: string } | null;
    } | null;
}

interface Investor {
    id: number;
    full_name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    status: 'active' | 'inactive';
    user: { id: number; name: string; email: string } | null;
    creator: { id: number; name: string; email: string } | null;
    wallet: Wallet | null;
    investments: Investment[];
    transactions: Transaction[];
    withdrawals: Withdrawal[];
    profit_entries?: ProfitEntry[];
    batches: Batch[];
    created_at: string;
    updated_at: string;
}

interface CurrencyBucket {
    currency_code: string;
    currency_symbol: string | null;
    decimal_places: number;
    original_total: number;
    ledger_afn: number;
}

interface FundingSummary {
    contributed_by_currency: CurrencyBucket[];
    unallocated_by_currency: CurrencyBucket[];
    current_balance_afn: number;
    reporting_currency: string;
    reporting_note: string;
}

interface FundedPurchase {
    id: number;
    purchase_date: string | null;
    invoice_number: string | null;
    product_name: string;
    variant_name: string | null;
    quantity: number;
    remaining_quantity: number;
    unit_cost_afn: number;
    line_total_afn: number;
    original_currency: string | null;
    original_currency_symbol: string | null;
    original_line_amount: number | null;
    exchange_rate: number | null;
    supplier_name: string | null;
    link_type: string;
    investment_number: string | null;
    allocated_amount_afn: number | null;
    allocation_status: 'allocated' | 'unallocated';
}

interface Props {
    investor: Investor;
    stats: {
        total_investments: number;
        active_investments: number;
        total_transactions: number;
        total_withdrawals: number;
        total_batches: number;
        active_batches: number;
        funded_purchases?: number;
        realized_profit_afn?: number;
        has_cash_capital?: boolean;
        is_goods_investor?: boolean;
        active_goods_value_afn?: number;
        active_goods_count?: number;
        available_cash_afn?: number;
    };
    funding?: FundingSummary;
    funded_purchases?: FundedPurchase[];
}

const breadcrumbs = (investor: Investor): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
    { title: investor.full_name, href: `/investors/${investor.id}` },
];

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const getStatusBadge = (status: string) => {
    const map: Record<string, { variant: 'success' | 'outline' | 'destructive' | 'secondary'; label: string }> = {
        active: { variant: 'success', label: 'Active' },
        inactive: { variant: 'outline', label: 'Inactive' },
        completed: { variant: 'secondary', label: 'Completed' },
        partially_sold: { variant: 'secondary', label: 'Partially Sold' },
        fully_sold: { variant: 'secondary', label: 'Fully Sold' },
        cancelled: { variant: 'destructive', label: 'Cancelled' },
        pending: { variant: 'outline', label: 'Pending' },
        approved: { variant: 'secondary', label: 'Approved' },
        paid: { variant: 'success', label: 'Paid' },
        rejected: { variant: 'destructive', label: 'Rejected' },
    };
    const item = map[status] || { variant: 'outline' as const, label: status };
    return <Badge variant={item.variant}>{item.label}</Badge>;
};

const ledgerAfN = { code: 'AFN', symbol: '؋', decimal_places: 2 };

function originalMoney(
    amountCurrency: string | number | null | undefined,
    amountAfn: string | number | null | undefined,
    currency: MoneyCurrency,
) {
    if (currency?.code && amountCurrency !== null && amountCurrency !== undefined && String(amountCurrency) !== '') {
        return formatMoney(amountCurrency, {
            code: currency.code,
            symbol: currency.symbol,
            decimal_places: currency.decimal_places ?? 2,
        });
    }

    return formatMoney(amountAfn ?? 0, ledgerAfN);
}

export default function InvestorsShow({ investor, stats, funding, funded_purchases = [] }: Props) {
    const profitEntries = investor.profit_entries ?? [];
    const money = (
        amountCurrency: string | number | null | undefined,
        amountAfn: string | number | null | undefined,
        currency: MoneyCurrency,
    ) => originalMoney(amountCurrency, amountAfn, currency);
    const [activeTab, setActiveTab] = useState<
        'overview' | 'investments' | 'purchases' | 'withdrawals' | 'profit' | 'batches' | 'transactions'
    >('overview');

    const formatBucket = (bucket: CurrencyBucket) =>
        formatMoney(bucket.original_total, {
            code: bucket.currency_code,
            symbol: bucket.currency_symbol,
            decimal_places: bucket.decimal_places ?? 2,
        });

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .slice(0, 2)
            .join('')
            .toUpperCase();
    };

    const walletCurrency = investor.wallet?.currency;
    const capitalOriginal = investor.wallet?.capital_balance_currency;
    const profitOriginal = investor.wallet?.profit_balance_currency;
    const sameWalletCurrency = Boolean(walletCurrency?.code && capitalOriginal != null && profitOriginal != null);
    const totalOriginal = sameWalletCurrency ? Number(capitalOriginal) + Number(profitOriginal) : null;
    const totalLedgerAfn =
        parseFloat(investor.wallet?.capital_balance_afn || '0') + parseFloat(investor.wallet?.profit_balance_afn || '0');
    const totalBalance = totalLedgerAfn;

    return (
        <AppLayout breadcrumbs={breadcrumbs(investor)}>
            <Head title={`View ${investor.full_name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-black text-xl font-bold text-white">
                            {getInitials(investor.full_name)}
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight">{investor.full_name}</h1>
                                {getStatusBadge(investor.status)}
                            </div>
                            {investor.user && <p className="text-muted-foreground mt-1 text-sm">Linked user: @{investor.user.name}</p>}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={`/investors/${investor.id}/edit`}>
                            <Button variant="outline" className="gap-2">
                                <Edit className="h-4 w-4" /> Edit
                            </Button>
                        </Link>
                        <Link href={`/investor-profit-entries/by-investor/${investor.id}`}>
                            <Button variant="outline">Profit</Button>
                        </Link>
                        <Link href={`/investor-transactions/by-investor/${investor.id}`}>
                            <Button variant="outline">Ledger</Button>
                        </Link>
                        <Link href={`/investor-withdrawals/create?investor_id=${investor.id}`}>
                            <Button variant="outline" className="gap-2">
                                <Banknote className="h-4 w-4" /> New Withdrawal
                            </Button>
                        </Link>
                        {totalBalance > 0 && (
                            <Button
                                className="gap-2"
                                onClick={() => {
                                    if (!window.confirm('Pay this investor their full balance, print the receipt, and set the account to zero?')) return;
                                    router.post(`/investors/${investor.id}/settle`);
                                }}
                            >
                                <Banknote className="h-4 w-4" /> Pay out and receipt
                            </Button>
                        )}
                        <Link href="/investors">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Wallet Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Capital Balance</p>
                            <Wallet className="h-4 w-4 text-blue-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-blue-600">
                            {money(investor.wallet?.capital_balance_currency, investor.wallet?.capital_balance_afn || 0, walletCurrency)}
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Ledger AFN: {formatMoney(investor.wallet?.total_invested_afn || 0, ledgerAfN)} invested
                        </p>
                    </div>
                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Profit Balance</p>
                            <TrendingUp className="h-4 w-4 text-green-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-green-600">
                            {money(investor.wallet?.profit_balance_currency, investor.wallet?.profit_balance_afn || 0, walletCurrency)}
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Ledger AFN earned: {formatMoney(investor.wallet?.total_profit_earned_afn || 0, ledgerAfN)}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Withdrawn</p>
                            <Banknote className="h-4 w-4 text-orange-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-orange-600">
                            {formatMoney(investor.wallet?.total_withdrawn_afn || 0, ledgerAfN)}
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs">Shown in original ledger currency (AFN)</p>
                    </div>
                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Total Balance</p>
                            <DollarSignIcon />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-purple-600">
                            {totalOriginal !== null && walletCurrency
                                ? formatMoney(totalOriginal, {
                                      code: walletCurrency.code,
                                      symbol: walletCurrency.symbol,
                                      decimal_places: walletCurrency.decimal_places ?? 2,
                                  })
                                : formatMoney(totalLedgerAfn, ledgerAfN)}
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs">
                            {totalOriginal !== null
                                ? `Capital + profit in ${walletCurrency?.code}. Ledger AFN: ${formatMoney(totalLedgerAfn, ledgerAfN)}`
                                : 'Capital + profit in AFN ledger (same currency). Mixed currencies are not combined.'}
                        </p>
                    </div>
                </div>

                {funding && (
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Capital contributed (by currency)</CardTitle>
                                <CardDescription>{funding.reporting_note}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {funding.contributed_by_currency.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No capital contributions recorded.</p>
                                ) : (
                                    funding.contributed_by_currency.map((bucket) => (
                                        <div key={bucket.currency_code} className="flex items-center justify-between border-b pb-2 last:border-0">
                                            <span className="text-sm font-medium">{bucket.currency_code}</span>
                                            <div className="text-right">
                                                <p className="font-mono font-semibold">{formatBucket(bucket)}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    Ledger {funding.reporting_currency}: {formatMoney(bucket.ledger_afn, ledgerAfN)}
                                                </p>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Unallocated funds (by currency)</CardTitle>
                                <CardDescription>
                                    Available investment capital not yet allocated to purchases. Current balance (AFN ledger):{' '}
                                    {formatMoney(funding.current_balance_afn, ledgerAfN)}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {funding.unallocated_by_currency.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No unallocated cash on active investments.</p>
                                ) : (
                                    funding.unallocated_by_currency.map((bucket) => (
                                        <div key={bucket.currency_code} className="flex items-center justify-between border-b pb-2 last:border-0">
                                            <span className="text-sm font-medium">{bucket.currency_code}</span>
                                            <div className="text-right">
                                                <p className="font-mono font-semibold text-sky-700">{formatBucket(bucket)}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    Ledger {funding.reporting_currency}: {formatMoney(bucket.ledger_afn, ledgerAfN)}
                                                </p>
                                            </div>
                                        </div>
                                    ))
                                )}
                                {typeof stats.available_cash_afn === 'number' && (
                                    <p className="text-muted-foreground pt-2 text-xs">
                                        Available cash (active pots, AFN): {formatMoney(stats.available_cash_afn, ledgerAfN)}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Tabs */}
                <div className="flex flex-wrap gap-1 border-b">
                    {[
                        { key: 'overview', label: 'Overview', icon: UserIcon },
                        { key: 'investments', label: `Investments (${stats.total_investments})`, icon: Wallet },
                        { key: 'purchases', label: `Purchases (${funded_purchases.length})`, icon: Package },
                        { key: 'withdrawals', label: `Withdrawals (${stats.total_withdrawals})`, icon: Banknote },
                        { key: 'profit', label: `Profit (${profitEntries.length})`, icon: TrendingUp },
                        { key: 'batches', label: `Batches (${stats.total_batches})`, icon: Package },
                        { key: 'transactions', label: `Ledger (${stats.total_transactions})`, icon: TrendingUp },
                    ].map((tab) => {
                        const Icon = tab.icon;
                        return (
                            <button
                                key={tab.key}
                                onClick={() => setActiveTab(tab.key as any)}
                                className={`flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                                    activeTab === tab.key
                                        ? 'border-black text-black'
                                        : 'text-muted-foreground hover:text-foreground border-transparent'
                                }`}
                            >
                                <Icon className="h-4 w-4" />
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {/* Tab: Overview */}
                {activeTab === 'overview' && (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {/* Info Card */}
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>Investor Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="flex items-start gap-3">
                                        <UserIcon className="text-muted-foreground mt-0.5 h-4 w-4" />
                                        <div>
                                            <p className="text-muted-foreground text-xs">Full Name</p>
                                            <p className="font-medium">{investor.full_name}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Phone className="text-muted-foreground mt-0.5 h-4 w-4" />
                                        <div>
                                            <p className="text-muted-foreground text-xs">Phone</p>
                                            <p className="font-medium" dir="ltr">
                                                {investor.phone || '—'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Mail className="text-muted-foreground mt-0.5 h-4 w-4" />
                                        <div>
                                            <p className="text-muted-foreground text-xs">Email</p>
                                            <p className="font-medium">{investor.email || '—'}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <UserIcon className="text-muted-foreground mt-0.5 h-4 w-4" />
                                        <div>
                                            <p className="text-muted-foreground text-xs">Created By</p>
                                            <p className="font-medium">{investor.creator?.name || 'System'}</p>
                                        </div>
                                    </div>
                                </div>

                                {investor.address && (
                                    <>
                                        <div className="border-t" />
                                        <div className="flex items-start gap-3">
                                            <MapPin className="text-muted-foreground mt-0.5 h-4 w-4" />
                                            <div>
                                                <p className="text-muted-foreground text-xs">Address</p>
                                                <p className="mt-0.5 text-sm">{investor.address}</p>
                                            </div>
                                        </div>
                                    </>
                                )}

                                {investor.notes && (
                                    <>
                                        <div className="border-t" />
                                        <div>
                                            <p className="text-muted-foreground text-xs">Notes</p>
                                            <p className="mt-1 text-sm">{investor.notes}</p>
                                        </div>
                                    </>
                                )}

                                <div className="border-t" />

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="flex items-start gap-3">
                                        <Calendar className="text-muted-foreground mt-0.5 h-4 w-4" />
                                        <div>
                                            <p className="text-muted-foreground text-xs">Member Since</p>
                                            <p className="text-sm">{formatDate(investor.created_at)}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Calendar className="text-muted-foreground mt-0.5 h-4 w-4" />
                                        <div>
                                            <p className="text-muted-foreground text-xs">Last Transaction</p>
                                            <p className="text-sm">{formatDate(investor.wallet?.last_transaction_at || null)}</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Stats Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Stats</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between border-b pb-3">
                                    <span className="text-muted-foreground text-sm">Total Investments</span>
                                    <span className="text-2xl font-bold">{stats.total_investments}</span>
                                </div>
                                <div className="flex items-center justify-between border-b pb-3">
                                    <span className="text-muted-foreground text-sm">Active Investments</span>
                                    <span className="text-2xl font-bold text-green-600">{stats.active_investments}</span>
                                </div>
                                <div className="flex items-center justify-between border-b pb-3">
                                    <span className="text-muted-foreground text-sm">Total Withdrawals</span>
                                    <span className="text-2xl font-bold text-orange-600">{stats.total_withdrawals}</span>
                                </div>
                                <div className="flex items-center justify-between border-b pb-3">
                                    <span className="text-muted-foreground text-sm">Active Batches</span>
                                    <span className="text-2xl font-bold text-blue-600">{stats.active_batches}</span>
                                </div>
                                <div className="flex items-center justify-between border-b pb-3">
                                    <span className="text-muted-foreground text-sm">Transactions</span>
                                    <span className="text-2xl font-bold">{stats.total_transactions}</span>
                                </div>
                                <div className="flex items-center justify-between border-b pb-3">
                                    <span className="text-muted-foreground text-sm">Funded purchases</span>
                                    <span className="text-2xl font-bold">{stats.funded_purchases ?? funded_purchases.length}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Realized profit (AFN)</span>
                                    <span className="text-2xl font-bold text-green-600">
                                        {formatMoney(stats.realized_profit_afn ?? 0, ledgerAfN)}
                                    </span>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Realized profit comes only from linked profit entries (sale items), not from capital or purchase spend.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {activeTab === 'investments' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Investments</CardTitle>
                            <CardDescription>
                                Each amount is shown in its original currency. Values in different currencies are not combined.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {investor.investments.length === 0 ? (
                                <div className="text-muted-foreground p-12 text-center text-sm">No investments yet.</div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Number</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Source</TableHead>
                                            <TableHead className="text-right">Capital</TableHead>
                                            <TableHead className="text-right">Unallocated</TableHead>
                                            <TableHead className="text-right">Allocated</TableHead>
                                            <TableHead className="text-right">Profit</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {investor.investments.map((inv) => (
                                            <TableRow key={inv.id}>
                                                <TableCell className="font-mono text-xs">{inv.investment_number}</TableCell>
                                                <TableCell className="text-xs">{formatDate(inv.investment_date)}</TableCell>
                                                <TableCell className="text-xs">{inv.source_type || '—'}</TableCell>
                                                <TableCell className="text-right font-mono text-sm">
                                                    {money(inv.capital_amount_currency, inv.capital_amount_afn, inv.currency)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm text-sky-700">
                                                    {formatMoney(inv.available_amount_afn ?? 0, ledgerAfN)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm">
                                                    {formatMoney(inv.allocated_amount_afn ?? 0, ledgerAfN)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm">
                                                    {money(inv.profit_amount_currency, inv.profit_amount_afn, inv.currency)}
                                                </TableCell>
                                                <TableCell>{getStatusBadge(inv.status)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'purchases' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Funded purchases</CardTitle>
                            <CardDescription>
                                Only purchases linked by <code>investor_id</code> or an investment purchase allocation are listed. Unlinked stock is
                                not inferred from dates or amounts.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {funded_purchases.length === 0 ? (
                                <div className="text-muted-foreground p-12 text-center text-sm">No reliably linked purchases for this investor.</div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-center">Qty</TableHead>
                                            <TableHead className="text-right">Original cost</TableHead>
                                            <TableHead className="text-right">Ledger AFN</TableHead>
                                            <TableHead>Supplier / ref</TableHead>
                                            <TableHead>Link</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {funded_purchases.map((p) => (
                                            <TableRow key={`${p.link_type}-${p.id}`}>
                                                <TableCell className="text-xs">{formatDate(p.purchase_date)}</TableCell>
                                                <TableCell className="text-sm">
                                                    <div className="font-medium">{p.product_name}</div>
                                                    {p.variant_name && <div className="text-muted-foreground text-xs">{p.variant_name}</div>}
                                                </TableCell>
                                                <TableCell className="text-center font-mono text-xs">
                                                    {p.remaining_quantity} / {p.quantity}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-xs">
                                                    {p.original_line_amount != null && p.original_currency
                                                        ? formatMoney(p.original_line_amount, {
                                                              code: p.original_currency,
                                                              symbol: p.original_currency_symbol,
                                                              decimal_places: 2,
                                                          })
                                                        : '—'}
                                                    {p.exchange_rate != null && (
                                                        <div className="text-muted-foreground">Rate: {p.exchange_rate}</div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-xs">
                                                    {formatMoney(p.line_total_afn, ledgerAfN)}
                                                    {p.allocated_amount_afn != null && (
                                                        <div className="text-muted-foreground">
                                                            Allocated: {formatMoney(p.allocated_amount_afn, ledgerAfN)}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    <div>{p.supplier_name || '—'}</div>
                                                    <div className="text-muted-foreground font-mono">{p.invoice_number || '—'}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={p.allocation_status === 'allocated' ? 'success' : 'outline'}>
                                                        {p.link_type === 'allocation'
                                                            ? `Allocation${p.investment_number ? ` (${p.investment_number})` : ''}`
                                                            : 'investor_id'}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'profit' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Realized profit (from sales)</CardTitle>
                            <CardDescription>
                                Only profit entries linked to sales/sale items. Capital contributions and purchase spend are not treated as profit.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {profitEntries.length === 0 ? (
                                <div className="text-muted-foreground p-12 text-center text-sm">No profit entries yet.</div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Earned</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Sale</TableHead>
                                            <TableHead className="text-right">Amount</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Paid</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {profitEntries.map((entry) => (
                                            <TableRow key={entry.id}>
                                                <TableCell className="text-xs">{formatDate(entry.earned_at)}</TableCell>
                                                <TableCell className="text-xs">{entry.entry_type || 'profit'}</TableCell>
                                                <TableCell className="text-xs font-mono">
                                                    {entry.sale?.invoice_number || (entry.sale_id ? `#${entry.sale_id}` : '—')}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm">
                                                    {money(entry.profit_amount_currency, entry.profit_amount_afn, entry.currency)}
                                                </TableCell>
                                                <TableCell>{getStatusBadge(entry.status)}</TableCell>
                                                <TableCell className="text-xs">{formatDate(entry.paid_at)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Tab: Withdrawals */}
                {activeTab === 'withdrawals' && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Withdrawals</CardTitle>
                                <CardDescription>Recent withdrawal requests for this investor.</CardDescription>
                            </div>
                            <Link href={`/investor-withdrawals/create?investor_id=${investor.id}`}>
                                <Button size="sm" className="gap-2">
                                    <Banknote className="h-4 w-4" /> New Withdrawal
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent className="p-0">
                            {investor.withdrawals.length === 0 ? (
                                <div className="text-muted-foreground p-12 text-center text-sm">No withdrawals yet.</div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Number</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead className="text-right">Amount</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {investor.withdrawals.map((w) => (
                                            <TableRow key={w.id}>
                                                <TableCell className="font-mono text-xs">{w.withdrawal_number}</TableCell>
                                                <TableCell className="text-xs">{formatDate(w.withdrawal_date)}</TableCell>
                                                <TableCell>
                                                    <Badge variant={w.type === 'profit' ? 'success' : 'secondary'}>
                                                        {w.type === 'profit' ? 'Profit' : 'Capital'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-mono font-semibold">
                                                    {money(w.amount_currency, w.amount_afn, w.currency)}
                                                </TableCell>
                                                <TableCell>{getStatusBadge(w.status)}</TableCell>
                                                <TableCell className="text-right">
                                                    <Link href={`/investor-withdrawals/${w.id}`}>
                                                        <Button variant="ghost" size="sm">
                                                            View
                                                        </Button>
                                                    </Link>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Tab: Batches */}
                {activeTab === 'batches' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Batches</CardTitle>
                            <CardDescription>Inventory batches owned by this investor. Unit costs are stored in AFN ledger.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {investor.batches.length === 0 ? (
                                <div className="text-muted-foreground p-12 text-center text-sm">No active batches.</div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Batch Code</TableHead>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-center">Qty / Remaining</TableHead>
                                            <TableHead className="text-right">Unit Cost</TableHead>
                                            <TableHead className="text-right">Profit/Unit</TableHead>
                                            <TableHead>Received</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {investor.batches.map((b) => (
                                            <TableRow key={b.id}>
                                                <TableCell className="font-mono text-xs">{b.batch_code}</TableCell>
                                                <TableCell className="text-sm">{b.variant?.product?.name || '—'}</TableCell>
                                                <TableCell className="text-center font-mono text-xs">
                                                    {b.remaining_quantity} / {b.quantity}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-xs">
                                                    {formatMoney(b.unit_cost_afn, ledgerAfN)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-xs text-blue-600">
                                                    {formatMoney(b.profit_per_unit_afn, ledgerAfN)}
                                                </TableCell>
                                                <TableCell className="text-xs">{formatDate(b.received_date)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Tab: Transactions */}
                {activeTab === 'transactions' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Transactions</CardTitle>
                            <CardDescription>Latest wallet transactions for this investor.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {investor.transactions.length === 0 ? (
                                <div className="text-muted-foreground p-12 text-center text-sm">No transactions yet.</div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Number</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Balance</TableHead>
                                            <TableHead className="text-right">Amount</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {investor.transactions.map((t) => (
                                            <TableRow key={t.id}>
                                                <TableCell className="font-mono text-xs">{t.transaction_number}</TableCell>
                                                <TableCell className="text-xs">{formatDate(t.transaction_date)}</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{t.type.replace(/_/g, ' ')}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={t.balance_type === 'profit' ? 'success' : 'secondary'}>{t.balance_type}</Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm font-semibold">
                                                    {money(t.amount_currency, t.amount_afn, t.currency)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

// small helper component
function DollarSignIcon() {
    return <Wallet className="h-4 w-4 text-purple-500" />;
}
