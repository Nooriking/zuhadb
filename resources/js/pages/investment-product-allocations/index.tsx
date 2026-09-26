// resources/js/pages/investment-product-allocations/index.tsx

import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import { DeleteAction, RowActions, ViewAction } from '@/components/row-actions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Allocation {
    id: number;
    funded_quantity: number;
    sold_quantity: number;
    remaining_quantity: number;
    cost_per_unit_afn: string;
    profit_per_unit_afn: string;
    profit_earned_afn: string;
    status: 'active' | 'sold_out' | 'cancelled';
    investment: { id: number; investment_number: string; investor: { id: number; full_name: string } | null } | null;
    batch: { id: number; batch_code: string; branch: { id: number; name: string; code: string } | null } | null;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    } | null;
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
    investments: { id: number; investment_number: string }[];
    filters: {
        investment_id: string | null;
        batch_id: string | null;
        status: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
    { title: 'Product Allocations', href: '/investment-product-allocations' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const getStatusBadge = (status: string) => {
    const map: Record<string, string> = {
        active: 'bg-green-100 text-green-800',
        sold_out: 'bg-gray-100 text-gray-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[status]}`}>
            {status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
        </span>
    );
};

export default function InvestmentProductAllocationsIndex({ allocations, investments, filters }: Props) {
    const [investmentFilter, setInvestmentFilter] = useState(filters.investment_id || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');

    const handleSearch = () => {
        router.get(
            '/investment-product-allocations',
            {
                investment_id: investmentFilter === 'all' ? undefined : investmentFilter,
                status: statusFilter === 'all' ? undefined : statusFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const confirmDelete = (allocation: Allocation) => {
        if (!confirm('Remove this product allocation? This action cannot be undone.')) return;
        router.delete(`/investment-product-allocations/${allocation.id}`, { preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Product Allocations" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Investment Product Allocations</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">
                            {allocations.total} Allocations
                        </span>
                    </div>
                    <Link href="/investment-product-allocations/create">
                        <button className="inline-flex items-center gap-2 rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                            + New Allocation
                        </button>
                    </Link>
                </div>

                <IndexFilters>
                    <FilterField label="Investment">
                        <select value={investmentFilter} onChange={(e) => { setInvestmentFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Investments</option>
                            {investments.map((inv) => (
                                <option key={inv.id} value={String(inv.id)}>{inv.investment_number}</option>
                            ))}
                        </select>
                    </FilterField>
                    <FilterField label="Status">
                        <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="sold_out">Sold Out</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </FilterField>
                </IndexFilters>

                {/* Table */}
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investment</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investor</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Batch</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Product</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Funded</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Sold</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Remaining</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Profit/Unit</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Profit Earned</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Status</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {allocations.data.length === 0 ? (
                                <tr>
                                    <td colSpan={12} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No allocations found.
                                    </td>
                                </tr>
                            ) : (
                                allocations.data.map((allocation, index) => (
                                    <tr key={allocation.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            {allocations.from ? allocations.from + index : index + 1}
                                        </td>
                                        <td className="px-4 py-3">
                                            {allocation.investment && (
                                                <Link
                                                    href={`/investments/${allocation.investment.id}`}
                                                    className="font-mono text-xs text-blue-600 hover:underline"
                                                >
                                                    {allocation.investment.investment_number}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {allocation.investment?.investor?.full_name || '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {allocation.batch && (
                                                <Link
                                                    href={`/batches/${allocation.batch.id}`}
                                                    className="font-mono text-xs text-blue-600 hover:underline"
                                                >
                                                    {allocation.batch.batch_code}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {allocation.variant?.product?.brand?.name && (
                                                    <span className="text-gray-500">{allocation.variant.product.brand.name} — </span>
                                                )}
                                                {allocation.variant?.product?.name}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {allocation.variant?.volume_ml ? `${allocation.variant.volume_ml}ml` : ''}
                                                {allocation.variant?.concentration ? ` · ${allocation.variant.concentration.toUpperCase()}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-center font-mono font-semibold">{allocation.funded_quantity}</td>
                                        <td className="px-4 py-3 text-center font-mono text-red-600">{allocation.sold_quantity}</td>
                                        <td className="px-4 py-3 text-center font-mono font-bold text-green-600">
                                            {allocation.remaining_quantity}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-xs">
                                            {formatPrice(allocation.profit_per_unit_afn)}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono font-semibold text-purple-600">
                                            {formatPrice(allocation.profit_earned_afn)}
                                        </td>
                                        <td className="px-4 py-3">{getStatusBadge(allocation.status)}</td>
                                        <td className="px-4 py-3">
                                            <RowActions>
                                                <ViewAction href={`/investment-product-allocations/${allocation.id}`} />
                                                {allocation.sold_quantity === 0 && (
                                                    <DeleteAction onClick={() => confirmDelete(allocation)} />
                                                )}
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