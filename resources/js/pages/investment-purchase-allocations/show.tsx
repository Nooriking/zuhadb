// resources/js/pages/investment-purchase-allocations/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Allocation {
    id: number;
    allocated_amount_currency: string;
    allocated_amount_afn: string;
    notes: string | null;
    investment: {
        id: number;
        investment_number: string;
        capital_amount_afn: string;
        currency: { id: number; code: string; symbol: string | null } | null;
        investor: { id: number; full_name: string; phone: string | null } | null;
    } | null;
    purchase: {
        id: number;
        purchase_number: string;
        invoice_number: string | null;
        total_afn: string;
        purchase_date: string;
        branch: { id: number; name: string } | null;
        supplier: { id: number; name: string } | null;
    } | null;
    creator: { id: number; name: string; email: string } | null;
    created_at: string;
}

interface Props {
    allocation: Allocation;
}

const breadcrumbs = (allocation: Allocation): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchase Allocations', href: '/investment-purchase-allocations' },
    { title: `Allocation #${allocation.id}`, href: `/investment-purchase-allocations/${allocation.id}` },
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

export default function InvestmentPurchaseAllocationShow({ allocation }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs(allocation)}>
            <Head title={`Allocation #${allocation.id}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight">Allocation Details</h1>
                    <Link href="/investment-purchase-allocations">
                        <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">Back</button>
                    </Link>
                </div>

                {/* Summary */}
                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="text-center">
                            <p className="text-xs text-gray-500">Investment</p>
                            {allocation.investment ? (
                                <>
                                    <Link
                                        href={`/investments/${allocation.investment.id}`}
                                        className="font-mono text-lg font-bold text-blue-600 hover:underline"
                                    >
                                        {allocation.investment.investment_number}
                                    </Link>
                                    <p className="mt-1 text-sm text-gray-500">Investor: {allocation.investment.investor?.full_name}</p>
                                </>
                            ) : (
                                '—'
                            )}
                        </div>
                        <div className="text-center">
                            <p className="text-xs text-gray-500">Purchase</p>
                            {allocation.purchase ? (
                                <>
                                    <Link
                                        href={`/purchases/${allocation.purchase.id}`}
                                        className="font-mono text-lg font-bold text-blue-600 hover:underline"
                                    >
                                        {allocation.purchase.purchase_number}
                                    </Link>
                                    <p className="mt-1 text-sm text-gray-500">Supplier: {allocation.purchase.supplier?.name}</p>
                                </>
                            ) : (
                                '—'
                            )}
                        </div>
                    </div>

                    <div className="mt-6 rounded-lg bg-orange-50 p-6 text-center">
                        <p className="text-xs text-gray-600">Allocated Amount</p>
                        <p className="text-3xl font-bold text-orange-700">
                            {formatPrice(allocation.allocated_amount_afn)} USD
                        </p>
                        <p className="mt-1 text-sm text-gray-500">{formatPrice(allocation.allocated_amount_afn)} USD</p>
                    </div>
                </div>

                {/* Details */}
                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold">Details</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs text-gray-500">Purchase Date</p>
                            <p className="font-medium">{allocation.purchase ? formatDate(allocation.purchase.purchase_date) : '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Purchase Invoice</p>
                            <p className="font-mono text-sm">{allocation.purchase?.invoice_number || '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Purchase Total</p>
                            <p className="font-mono font-medium">{allocation.purchase ? formatPrice(allocation.purchase.total_afn) + ' AFN' : '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Branch</p>
                            <p className="font-medium">{allocation.purchase?.branch?.name || '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Created By</p>
                            <p className="font-medium">{allocation.creator?.name || 'System'}</p>
                            <p className="text-xs text-gray-500">{allocation.creator?.email}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Created At</p>
                            <p className="text-sm">{formatDate(allocation.created_at)}</p>
                        </div>
                    </div>

                    {allocation.notes && (
                        <>
                            <hr className="my-4 border-gray-200" />
                            <div>
                                <p className="text-xs text-gray-500">Notes</p>
                                <p className="mt-1 text-sm">{allocation.notes}</p>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
