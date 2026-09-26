// resources/js/pages/investors/show.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Banknote, Calendar, Edit, Mail, MapPin, Package, Phone, TrendingUp, User as UserIcon, Wallet } from 'lucide-react';
import { useState } from 'react';

interface Wallet {
    id: number;
    capital_balance_currency: string;
    capital_balance_afn: string;
    profit_balance_currency: string;
    profit_balance_afn: string;
    total_invested_afn: string;
    total_profit_earned_afn: string;
    total_withdrawn_afn: string;
    last_transaction_at: string | null;
    currency: { id: number; code: string; name: string; symbol: string | null } | null;
}

interface Investment {
    id: number;
    investment_number: string;
    capital_amount_currency: string;
    capital_amount_afn: string;
    profit_amount_afn: string;
    investment_date: string;
    status: string;
    currency: { id: number; code: string; symbol: string | null } | null;
}

interface Withdrawal {
    id: number;
    withdrawal_number: string;
    type: 'profit' | 'capital';
    amount_currency: string;
    amount_afn: string;
    status: 'pending' | 'approved' | 'paid' | 'rejected';
    withdrawal_date: string;
    currency: { id: number; code: string; symbol: string | null } | null;
}

interface Transaction {
    id: number;
    transaction_number: string;
    type: string;
    amount_afn: string;
    balance_type: 'capital' | 'profit';
    description: string | null;
    transaction_date: string;
    currency: { id: number; code: string; symbol: string | null } | null;
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
    batches: Batch[];
    created_at: string;
    updated_at: string;
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
    };
}

const breadcrumbs = (investor: Investor): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investors', href: '/investors' },
    { title: investor.full_name, href: `/investors/${investor.id}` },
];

const formatPrice = (value: string | number, digits: number = 0) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(num);
};

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

export default function InvestorsShow({ investor, stats }: Props) {
    const [activeTab, setActiveTab] = useState<'overview' | 'withdrawals' | 'batches' | 'transactions'>('overview');

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .slice(0, 2)
            .join('')
            .toUpperCase();
    };

    const totalBalance = parseFloat(investor.wallet?.capital_balance_afn || '0') + parseFloat(investor.wallet?.profit_balance_afn || '0');

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
                        <p className="mt-1 text-2xl font-bold text-blue-600">{formatPrice(investor.wallet?.capital_balance_afn || 0)} USD</p>
                        <p className="text-muted-foreground mt-1 text-xs">Invested: {formatPrice(investor.wallet?.total_invested_afn || 0)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Profit Balance</p>
                            <TrendingUp className="h-4 w-4 text-green-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-green-600">{formatPrice(investor.wallet?.profit_balance_afn || 0)} USD</p>
                        <p className="text-muted-foreground mt-1 text-xs">Earned: {formatPrice(investor.wallet?.total_profit_earned_afn || 0)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Withdrawn</p>
                            <Banknote className="h-4 w-4 text-orange-500" />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-orange-600">{formatPrice(investor.wallet?.total_withdrawn_afn || 0)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">Total Balance</p>
                            <DollarSignIcon />
                        </div>
                        <p className="mt-1 text-2xl font-bold text-purple-600">{formatPrice(totalBalance)} USD</p>
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex gap-1 border-b">
                    {[
                        { key: 'overview', label: 'Overview', icon: UserIcon },
                        { key: 'withdrawals', label: `Withdrawals (${stats.total_withdrawals})`, icon: Banknote },
                        { key: 'batches', label: `Batches (${stats.total_batches})`, icon: Package },
                        { key: 'transactions', label: `Transactions (${stats.total_transactions})`, icon: TrendingUp },
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
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Transactions</span>
                                    <span className="text-2xl font-bold">{stats.total_transactions}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
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
                                                <TableCell className="text-right font-mono font-semibold">{formatPrice(w.amount_afn)} USD</TableCell>
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
                            <CardTitle>Active Batches</CardTitle>
                            <CardDescription>Inventory batches owned by this investor.</CardDescription>
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
                                                <TableCell className="text-right font-mono text-xs">{formatPrice(b.unit_cost_afn)} AFN</TableCell>
                                                <TableCell className="text-right font-mono text-xs text-blue-600">
                                                    {formatPrice(b.profit_per_unit_afn)} USD
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
                                                    {formatPrice(t.amount_afn)} USD
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
