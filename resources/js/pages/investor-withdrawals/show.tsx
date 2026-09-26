import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Withdrawal {
    id: number;
    withdrawal_number: string;
    type: 'profit' | 'capital';
    amount_afn: string;
    balance_before_afn: string;
    balance_after_afn: string;
    withdrawal_date: string | null;
    status: 'pending' | 'approved' | 'paid' | 'rejected';
    notes: string | null;
    approved_at: string | null;
    paid_at: string | null;
    investor: { id: number; full_name: string; phone: string | null; email: string | null } | null;
    investment: { id: number; investment_number: string } | null;
    approved_by: { id: number; name: string } | null;
    paid_by: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
}

interface Props {
    withdrawal: Withdrawal;
}

const breadcrumbs = (withdrawal: Withdrawal): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investor Withdrawals', href: '/investor-withdrawals' },
    { title: withdrawal.withdrawal_number, href: `/investor-withdrawals/${withdrawal.id}` },
];

const formatPrice = (value: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(typeof value === 'string' ? parseFloat(value) : value);

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

export default function InvestorWithdrawalShow({ withdrawal }: Props) {
    const statusColors: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-blue-100 text-blue-800',
        paid: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(withdrawal)}>
            <Head title={withdrawal.withdrawal_number} />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-bold">{withdrawal.withdrawal_number}</h1>
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusColors[withdrawal.status]}`}>
                                {withdrawal.status}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">
                            {withdrawal.type === 'profit' ? 'Profit withdrawal' : 'Capital withdrawal'} · {formatDate(withdrawal.withdrawal_date)}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {withdrawal.status === 'pending' && (
                            <>
                                <button
                                    className="rounded-md bg-black px-4 py-2 text-sm text-white"
                                    onClick={() => router.post(`/investor-withdrawals/${withdrawal.id}/approve`)}
                                >
                                    Approve
                                </button>
                                <button
                                    className="rounded-md border px-4 py-2 text-sm"
                                    onClick={() => router.post(`/investor-withdrawals/${withdrawal.id}/reject`)}
                                >
                                    Reject
                                </button>
                            </>
                        )}
                        {withdrawal.status === 'paid' && (
                            <a href={`/investor-withdrawals/${withdrawal.id}/receipt`} className="rounded-md bg-black px-4 py-2 text-sm text-white">
                                Print receipt
                            </a>
                        )}
                        {withdrawal.status === 'approved' && (
                            <button
                                className="rounded-md bg-black px-4 py-2 text-sm text-white"
                                onClick={() => router.post(`/investor-withdrawals/${withdrawal.id}/mark-paid`)}
                            >
                                Mark paid
                            </button>
                        )}
                        <Link href="/investor-withdrawals" className="rounded-md border px-4 py-2 text-sm">
                            Back
                        </Link>
                    </div>
                </div>

                <div className="rounded-lg border bg-white p-6 text-center shadow-sm">
                    <p className="text-xs uppercase text-gray-500">Amount</p>
                    <p className="mt-2 font-mono text-4xl font-bold">{formatPrice(withdrawal.amount_afn)} USD</p>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Before</p>
                        <p className="font-mono text-xl font-bold">{formatPrice(withdrawal.balance_before_afn)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">After</p>
                        <p className="font-mono text-xl font-bold">{formatPrice(withdrawal.balance_after_afn)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Investor</p>
                        {withdrawal.investor ? (
                            <Link href={`/investors/${withdrawal.investor.id}`} className="font-medium text-blue-600 hover:underline">
                                {withdrawal.investor.full_name}
                            </Link>
                        ) : (
                            '—'
                        )}
                        {withdrawal.investment && (
                            <p className="text-xs text-gray-500">
                                Investment{' '}
                                <Link href={`/investments/${withdrawal.investment.id}`} className="text-blue-600 hover:underline">
                                    {withdrawal.investment.investment_number}
                                </Link>
                            </p>
                        )}
                    </div>
                </div>

                <div className="rounded-lg border bg-white p-6">
                    <h2 className="mb-3 text-lg font-semibold">Record</h2>
                    <div className="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                        <p>Approved by: {withdrawal.approved_by?.name || '—'}</p>
                        <p>Approved at: {formatDate(withdrawal.approved_at)}</p>
                        <p>Paid by: {withdrawal.paid_by?.name || '—'}</p>
                        <p>Paid at: {formatDate(withdrawal.paid_at)}</p>
                        <p>Created by: {withdrawal.creator?.name || '—'}</p>
                        <p className="md:col-span-2">Notes: {withdrawal.notes || '—'}</p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
