// resources/js/pages/inventory/index.tsx

import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Inventory {
    id: number;
    branch_id: number;
    variant_id: number;
    quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    average_cost_afn: string;
    branch: { id: number; name: string; code: string } | null;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        sale_price_afn: string;
        minimum_stock: number;
        product: {
            id: number;
            name: string;
            brand: { id: number; name: string } | null;
        } | null;
    } | null;
}

interface Props {
    inventories: {
        data: Inventory[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    branches: { id: number; name: string; code: string }[];
    filters: {
        search: string | null;
        branch_id: string | null;
        stock_status: string | null;
    };
    stats: {
        total_items: number;
        total_quantity: number;
        total_amount: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/inventory' },
];

export default function InventoryIndex({ inventories, branches, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id || 'all');
    const [stockFilter, setStockFilter] = useState(filters.stock_status || 'all');

    const handleSearch = () => {
        router.get(
            '/inventory',
            {
                search: search || undefined,
                branch_id: branchFilter === 'all' ? undefined : branchFilter,
                stock_status: stockFilter === 'all' ? undefined : stockFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const money = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value || 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventory" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Inventory</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{inventories.total} Items</span>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Quantity on hand</p>
                        <p className="text-2xl font-bold">{new Intl.NumberFormat('en-US').format(stats.total_quantity)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Total amount</p>
                        <p className="text-2xl font-bold">{money(stats.total_amount)} AFN</p>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by product name...">
                    {branches.length > 1 && (
                        <FilterField label="Branch">
                            <select
                                value={branchFilter}
                                onChange={(e) => {
                                    setBranchFilter(e.target.value);
                                    setTimeout(handleSearch, 0);
                                }}
                                className={filterControlClass}
                            >
                                <option value="all">All Branches</option>
                                {branches.map((b) => (
                                    <option key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </option>
                                ))}
                            </select>
                        </FilterField>
                    )}
                    <FilterField label="Stock">
                        <select
                            value={stockFilter}
                            onChange={(e) => {
                                setStockFilter(e.target.value);
                                setTimeout(handleSearch, 0);
                            }}
                            className={filterControlClass}
                        >
                            <option value="all">All Stock</option>
                            <option value="in">In Stock</option>
                            <option value="low">Low Stock</option>
                            <option value="out">Out of Stock</option>
                        </select>
                    </FilterField>
                </IndexFilters>

                {/* Table */}
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Product</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Branch</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Quantity</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Total amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {inventories.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No inventory records found.
                                    </td>
                                </tr>
                            ) : (
                                inventories.data.map((inv, index) => (
                                    <tr key={inv.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3">{inventories.from ? inventories.from + index : index + 1}</td>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {inv.variant?.product?.brand?.name && (
                                                    <span className="text-gray-500">{inv.variant.product.brand.name} — </span>
                                                )}
                                                {inv.variant?.product?.name}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {inv.variant?.volume_ml ? `${inv.variant.volume_ml}ml` : ''}
                                                {inv.variant?.concentration ? ` · ${inv.variant.concentration.toUpperCase()}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full border px-2 py-0.5 font-mono text-xs">{inv.branch?.code}</span>
                                        </td>
                                        <td className="px-4 py-3 text-center font-mono font-semibold">{inv.quantity}</td>
                                        <td className="px-4 py-3 text-right font-mono font-semibold">
                                            {money(inv.quantity * (parseFloat(inv.variant?.sale_price_afn || '0') || 0))} AFN
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {inventories.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {inventories.from || 0} to {inventories.to || 0} of {inventories.total} results
                        </div>
                        <div className="flex gap-1">
                            {inventories.links.map((link, i) => (
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
