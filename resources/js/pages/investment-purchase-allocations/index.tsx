// resources/js/pages/investment-purchase-allocations/index.tsx

import { DeleteAction, RowActions, ViewAction } from '@/components/row-actions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Allocation {
    id: number;
    allocated_amount_currency: string;
    allocated_amount_afn: string;
    notes: string | null;
    investment: {
        id: number;
        investment_number: string;
        capital_amount_afn: string;
        available_amount_afn: string;
        investor: { id: number; full_name: string } | null;
    } | null;
    purchase: {
        id: number;
        purchase_number: string;
        invoice_number: string | null;
        total_afn: string;
        purchase_date: string;
    } | null;
    creator: { id: number; name: string } | null;
    created_at: string;
}

interface Props {
    allocations: {
        data: Allocation[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        investment_id: string | null;
        purchase_id: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
    { title: 'Purchase Allocations', href: '/investment-purchase-allocations' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US');
};

export default function InvestmentPurchaseAllocationsIndex({ allocations }: Props) {
    const confirmDelete = (allocation: Allocation) => {
        if (!confirm(`Remove this allocation? This will return ${formatPrice(allocation.allocated_amount_afn)} USD to the investment.`)) return;

        router.delete(`/investment-purchase-allocations/${allocation.id}`, { preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Investment Purchase Allocations" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Purchase Allocations</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{allocations.total} Allocations</span>
                    </div>
                    <Link href="/investment-purchase-allocations/create">
                        <button className="inline-flex items-center gap-2 rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                            + New Allocation
                        </button>
                    </Link>
                </div>

                {/* Table */}
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investment</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investor</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Purchase</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Allocated</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">By</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {allocations.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No allocations found.
                                    </td>
                                </tr>
                            ) : (
                                allocations.data.map((allocation, index) => (
                                    <tr key={allocation.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3">{allocations.from ? allocations.from + index : index + 1}</td>
                                        <td className="px-4 py-3">
                                            {allocation.investment ? (
                                                <Link
                                                    href={`/investments/${allocation.investment.id}`}
                                                    className="font-mono text-xs text-blue-600 hover:underline"
                                                >
                                                    {allocation.investment.investment_number}
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {allocation.investment?.investor ? (
                                                <Link
                                                    href={`/investors/${allocation.investment.investor.id}`}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {allocation.investment.investor.full_name}
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {allocation.purchase ? (
                                                <div>
                                                    <Link
                                                        href={`/purchases/${allocation.purchase.id}`}
                                                        className="font-mono text-xs text-blue-600 hover:underline"
                                                    >
                                                        {allocation.purchase.purchase_number}
                                                    </Link>
                                                    <div className="text-xs text-gray-500">
                                                        Total: {formatPrice(allocation.purchase.total_afn)} AFN
                                                    </div>
                                                </div>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            {allocation.purchase ? formatDate(allocation.purchase.purchase_date) : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono font-semibold text-orange-600">
                                            <div>{formatPrice(allocation.allocated_amount_afn)} USD</div>
                                            <div className="text-xs text-gray-500">{formatPrice(allocation.allocated_amount_afn)} USD</div>
                                        </td>
                                        <td className="px-4 py-3 text-xs">{allocation.creator?.name || '—'}</td>
                                        <td className="px-4 py-3">
                                            <RowActions>
                                                <ViewAction href={`/investment-purchase-allocations/${allocation.id}`} />
                                                <DeleteAction onClick={() => confirmDelete(allocation)} title="Remove" />
                                            </RowActions>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {allocations.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {allocations.from || 0} to {allocations.to || 0} of {allocations.total} results
                        </div>
                        <div className="flex gap-1">
                            {allocations.links.map((link, i) => (
                                <button
                                    key={i}
                                    onClick={() => handlePageChange(link.url)}
                                    disabled={!link.url}
                                    className={`min-w-[40px] rounded-md border px-3 py-1 text-sm ${
                                        link.active
                                            ? 'bg-black text-white'
                                            : link.url
                                              ? 'bg-white hover:bg-gray-100'
                                              : 'cursor-not-allowed bg-gray-100 text-gray-400'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
