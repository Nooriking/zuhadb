// resources/js/pages/investor-transactions/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Transaction {
    id: number;
    type: string;
    type_label: string;
    amount_currency: string;
    amount_afn: string;
    balance_type: 'capital' | 'profit';
    balance_before_afn: string;
    balance_after_afn: string;
    exchange_rate: string;
    transaction_date: string;
    description: string | null;
    reference_type: string | null;
    reference_id: number | null;
    investor: { id: number; full_name: string; phone: string | null; email: string | null } | null;
    investment: { id: number; investment_number: string; capital_amount_afn: string } | null;
    currency: { id: number; code: string; name: string; symbol: string | null } | null;
    creator: { id: number; name: string; email: string } | null;
    created_at: string;
}

interface Props {
    transaction: Transaction;
}

const breadcrumbs = (transaction: Transaction): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investor Transactions', href: '/investor-transactions' },
    { title: `#${transaction.id}`, href: `/investor-transactions/${transaction.id}` },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export default function InvestorTransactionShow({ transaction }: Props) {
    const typeColors: Record<string, string> = {
        capital_deposit: 'bg-green-100 text-green-800 border-green-200',
        capital_withdrawn: 'bg-red-100 text-red-800 border-red-200',
        profit_earned: 'bg-blue-100 text-blue-800 border-blue-200',
        profit_withdrawn: 'bg-orange-100 text-orange-800 border-orange-200',
        reinvestment: 'bg-purple-100 text-purple-800 border-purple-200',
        adjustment: 'bg-gray-100 text-gray-800 border-gray-200',
        loss: 'bg-red-100 text-red-800 border-red-200',
    };

    const delta = parseFloat(transaction.balance_after_afn) - parseFloat(transaction.balance_before_afn);

    return (
        <AppLayout breadcrumbs={breadcrumbs(transaction)}>
            <Head title={`Transaction #${transaction.id}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-bold tracking-tight">Transaction #{transaction.id}</h1>
                            <span
                                className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${typeColors[transaction.type] || 'bg-gray-100 text-gray-800'}`}
                            >
                                {transaction.type_label}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">{formatDate(transaction.transaction_date)}</p>
                    </div>
                    <Link href="/investor-transactions">
                        <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">Back</button>
                    </Link>
                </div>

                {/* Amount Card */}
                <div className={`rounded-lg border-2 p-6 text-center shadow-sm ${typeColors[transaction.type] || 'bg-gray-50'}`}>
                    <p className="text-xs uppercase">Amount</p>
                    <p className="mt-2 font-mono text-4xl font-bold">
                        {formatPrice(transaction.amount_afn)} USD
                    </p>
                    <p className="mt-1 text-sm">{formatPrice(transaction.amount_afn)} USD</p>
                </div>

                {/* Balance Change */}
                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold">Balance Change</h2>
                    <div className="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p className="text-xs text-gray-500">Before</p>
                            <p className="font-mono text-xl font-bold">{formatPrice(transaction.balance_before_afn)} USD</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Change</p>
                            <p className={`font-mono text-xl font-bold ${delta >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                {delta >= 0 ? '+' : ''}
                                {formatPrice(delta)} USD
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">After</p>
                            <p className="font-mono text-xl font-bold">{formatPrice(transaction.balance_after_afn)} USD</p>
                        </div>
                    </div>
                    <p className="mt-4 text-center text-xs text-gray-500">
                        Affects: <strong>{transaction.balance_type.toUpperCase()}</strong> balance
                    </p>
                </div>

                {/* Details */}
                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold">Details</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs text-gray-500">Investor</p>
                            {transaction.investor ? (
                                <Link href={`/investors/${transaction.investor.id}`} className="font-medium text-blue-600 hover:underline">
                                    {transaction.investor.full_name}
                                </Link>
                            ) : (
                                '—'
                            )}
                            {transaction.investor?.phone && (
                                <p className="text-xs text-gray-500" dir="ltr">
                                    {transaction.investor.phone}
                                </p>
                            )}
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Investment</p>
                            {transaction.investment ? (
                                <Link href={`/investments/${transaction.investment.id}`} className="font-mono text-sm text-blue-600 hover:underline">
                                    {transaction.investment.investment_number}
                                </Link>
                            ) : (
                                <p className="text-gray-500">—</p>
                            )}
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Currency</p>
                            <p className="font-medium">
                                {transaction.currency?.code} — {transaction.currency?.name}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Exchange Rate</p>
                            <p className="font-mono font-medium">
                                1 {transaction.currency?.code} = {transaction.exchange_rate} AFN
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Created By</p>
                            <p className="font-medium">{transaction.creator?.name || 'System'}</p>
                            <p className="text-xs text-gray-500">{transaction.creator?.email}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Created At</p>
                            <p className="text-sm">{formatDateTime(transaction.created_at)}</p>
                        </div>
                    </div>

                    {transaction.description && (
                        <>
                            <hr className="my-4 border-gray-200" />
                            <div>
                                <p className="text-xs text-gray-500">Description</p>
                                <p className="mt-1 text-sm">{transaction.description}</p>
                            </div>
                        </>
                    )}

                    {transaction.reference_type && (
                        <>
                            <hr className="my-4 border-gray-200" />
                            <div>
                                <p className="text-xs text-gray-500">Reference</p>
                                <p className="font-mono text-xs">
                                    {transaction.reference_type.split('\\').pop()} #{transaction.reference_id}
                                </p>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
