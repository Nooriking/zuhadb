// resources/js/pages/investor-profit-entries/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Entry {
    id: number;
    quantity: number;
    profit_per_unit_currency: string;
    profit_per_unit_afn: string;
    profit_amount_currency: string;
    profit_amount_afn: string;
    entry_type: 'profit' | 'loss' | 'adjustment';
    status: 'pending' | 'paid' | 'cancelled';
    earned_at: string | null;
    paid_at: string | null;
    notes: string | null;
    investor: { id: number; full_name: string; phone: string | null; email: string | null } | null;
    investment: { id: number; investment_number: string; capital_amount_afn: string } | null;
    sale: { id: number; invoice_number: string; total_afn: string } | null;
    currency: { id: number; code: string; name: string; symbol: string | null } | null;
    payer: { id: number; name: string; email: string } | null;
}

interface Props {
    entry: Entry;
}

const breadcrumbs = (entry: Entry): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investor Profit Entries', href: '/investor-profit-entries' },
    { title: `Entry #${entry.id}`, href: `/investor-profit-entries/${entry.id}` },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
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

export default function InvestorProfitEntryShow({ entry }: Props) {
    const handleMarkPaid = () => {
        if (!confirm('Mark this entry as paid?')) return;
        router.patch(`/investor-profit-entries/${entry.id}/mark-as-paid`, {}, { preserveScroll: true });
    };

    const handleCancel = () => {
        const reason = prompt('Reason for cancellation:');
        if (reason === null) return;
        router.patch(`/investor-profit-entries/${entry.id}/cancel`, { reason }, { preserveScroll: true });
    };

    const statusColors: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        paid: 'bg-green-100 text-green-800 border-green-200',
        cancelled: 'bg-red-100 text-red-800 border-red-200',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(entry)}>
            <Head title={`Profit Entry #${entry.id}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-bold">Profit Entry #{entry.id}</h1>
                            <span
                                className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusColors[entry.status]}`}
                            >
                                {entry.status.toUpperCase()}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">Earned: {formatDateTime(entry.earned_at)}</p>
                    </div>
                    <div className="flex gap-2">
                        {entry.status === 'pending' && (
                            <>
                                <button
                                    onClick={handleMarkPaid}
                                    className="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                                >
                                    âœ“ Mark as Paid
                                </button>
                                <button
                                    onClick={handleCancel}
                                    className="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                                >
                                    Cancel
                                </button>
                            </>
                        )}
                        <Link href="/investor-profit-entries">
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Back
                            </button>
                        </Link>
                    </div>
                </div>

                {/* Amount Card */}
                <div className="rounded-lg border-2 border-green-200 bg-green-50 p-6 text-center shadow-sm">
                    <p className="text-xs text-gray-600 uppercase">Profit Amount</p>
                    <p className="mt-2 font-mono text-4xl font-bold text-green-700">
                        {formatPrice(entry.profit_amount_afn)} USD
                    </p>
                    <p className="mt-1 text-sm text-gray-600">{formatPrice(entry.profit_amount_afn)} USD</p>
                </div>

                {/* Details */}
                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold">Details</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs text-gray-500">Investor</p>
                            {entry.investor && (
                                <Link href={`/investors/${entry.investor.id}`} className="font-medium text-blue-600 hover:underline">
                                    {entry.investor.full_name}
                                </Link>
                            )}
                            {entry.investor?.phone && (
                                <p className="text-xs text-gray-500" dir="ltr">
                                    {entry.investor.phone}
                                </p>
                            )}
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Investment</p>
                            {entry.investment && (
                                <Link href={`/investments/${entry.investment.id}`} className="font-mono text-sm text-blue-600 hover:underline">
                                    {entry.investment.investment_number}
                                </Link>
                            )}
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Related Sale</p>
                            {entry.sale ? (
                                <Link href={`/sales/${entry.sale.id}`} className="font-mono text-sm text-blue-600 hover:underline">
                                    {entry.sale.invoice_number}
                                </Link>
                            ) : (
                                '—'
                            )}
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Quantity</p>
                            <p className="font-mono font-medium">{entry.quantity}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Profit per Unit ({entry.currency?.code})</p>
                            <p className="font-mono">{formatPrice(entry.profit_per_unit_currency)}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Profit per Unit (USD)</p>
                            <p className="font-mono">{formatPrice(entry.profit_per_unit_afn)}</p>
                        </div>
                        {entry.status === 'paid' && entry.payer && (
                            <>
                                <div>
                                    <p className="text-xs text-gray-500">Paid By</p>
                                    <p className="font-medium">{entry.payer.name}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500">Paid At</p>
                                    <p className="text-sm">{formatDateTime(entry.paid_at)}</p>
                                </div>
                            </>
                        )}
                    </div>

                    {entry.notes && (
                        <>
                            <hr className="my-4 border-gray-200" />
                            <div>
                                <p className="text-xs text-gray-500">Notes</p>
                                <p className="mt-1 text-sm whitespace-pre-wrap">{entry.notes}</p>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
