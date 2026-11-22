import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import { useExchange } from '@/hooks/useExchange';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface InventoryRow {
    id: number;
    quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    average_cost_afn: number;
    total_cost_afn: number;
    sale_price_afn: number;
    total_sale_afn: number;
    last_movement_at: string | null;
    branch: { id: number; name: string; code: string } | null;
    variant: {
        id: number;
        name: string | null;
        volume_ml: number | null;
        concentration: string | null;
        sku: string | null;
        product: {
            id: number;
            name: string;
            brand: { id: number; name: string } | null;
        } | null;
    } | null;
}

interface Props {
    inventories: {
        data: InventoryRow[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    summary: {
        total_quantity: number;
        total_cost_afn: number;
        low_stock_count: number;
        out_of_stock_count: number;
        variant_count: number;
    };
    branches: { id: number; name: string; code: string }[];
    brands: { id: number; name: string }[];
    filters: {
        branch_id?: string | number | null;
        search?: string | null;
        stock_filter?: string | null;
        brand_id?: string | number | null;
    };
    lowStockThreshold: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/inventory' },
];

export default function InventoryIndex({
    inventories,
    summary,
    branches,
    brands,
    filters,
    lowStockThreshold,
}: Props) {
    const { convert, usdToAfn, aedToAfn } = useExchange();
    const [search, setSearch] = useState(filters.search || '');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id ? String(filters.branch_id) : 'all');
    const [stockFilter, setStockFilter] = useState(filters.stock_filter || 'all');
    const [brandFilter, setBrandFilter] = useState(filters.brand_id ? String(filters.brand_id) : 'all');

    const money = (value: number) =>
        new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value || 0);

    const multiCurrency = (afn: number) => {
        const usd = convert(afn, 'AFN', 'USD');
        const aed = convert(afn, 'AFN', 'AED');
        return {
            afn: money(afn),
            usd: usd == null ? '—' : money(usd),
            aed: aed == null ? '—' : money(aed),
        };
    };

    const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
        const next = {
            search,
            branch_id: branchFilter,
            stock_filter: stockFilter,
            brand_id: brandFilter,
            ...overrides,
        };

        router.get(
            '/inventory',
            {
                search: next.search || undefined,
                branch_id: next.branch_id === 'all' ? undefined : next.branch_id,
                stock_filter: next.stock_filter === 'all' ? undefined : next.stock_filter,
                brand_id: next.brand_id === 'all' ? undefined : next.brand_id,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const stockCost = multiCurrency(summary.total_cost_afn);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventory" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Inventory</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{inventories.total} SKUs</span>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/inventory/low-stock">
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Low stock
                            </button>
                        </Link>
                        <Link href="/inventory/transactions">
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Movements
                            </button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Available qty</p>
                        <p className="text-2xl font-bold">{new Intl.NumberFormat('en-US').format(summary.total_quantity)}</p>
                        <p className="text-xs text-gray-500">{summary.variant_count} inventory rows</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Stock cost</p>
                        <p className="text-xl font-bold">{stockCost.afn} AFN</p>
                        <p className="text-xs text-gray-500">
                            {stockCost.usd} USD · {stockCost.aed} AED
                        </p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Low stock (≤{lowStockThreshold})</p>
                        <p className="text-2xl font-bold text-amber-600">{summary.low_stock_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Out of stock</p>
                        <p className="text-2xl font-bold text-red-600">{summary.out_of_stock_count}</p>
                        <p className="text-xs text-gray-500">
                            Board USD {usdToAfn ?? '—'} · AED {aedToAfn ?? '—'}
                        </p>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={() => applyFilters()} placeholder="Search product, SKU, barcode...">
                    {branches.length > 1 && (
                        <FilterField label="Branch">
                            <select
                                value={branchFilter}
                                onChange={(e) => {
                                    setBranchFilter(e.target.value);
                                    applyFilters({ branch_id: e.target.value });
                                }}
                                className={filterControlClass}
                            >
                                <option value="all">All branches</option>
                                {branches.map((b) => (
                                    <option key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </option>
                                ))}
                            </select>
                        </FilterField>
                    )}
                    <FilterField label="Brand">
                        <select
                            value={brandFilter}
                            onChange={(e) => {
                                setBrandFilter(e.target.value);
                                applyFilters({ brand_id: e.target.value });
                            }}
                            className={filterControlClass}
                        >
                            <option value="all">All brands</option>
                            {brands.map((b) => (
                                <option key={b.id} value={String(b.id)}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                    </FilterField>
                    <FilterField label="Stock">
                        <select
                            value={stockFilter}
                            onChange={(e) => {
                                setStockFilter(e.target.value);
                                applyFilters({ stock_filter: e.target.value });
                            }}
                            className={filterControlClass}
                        >
                            <option value="all">All stock</option>
                            <option value="in_stock">In stock</option>
                            <option value="low_stock">Low stock</option>
                            <option value="out_of_stock">Out of stock</option>
                        </select>
                    </FilterField>
                </IndexFilters>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Product</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Branch</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Available</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Avg cost</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Stock value</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Retail value</th>
                            </tr>
                        </thead>
                        <tbody>
                            {inventories.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No inventory records found.
                                    </td>
                                </tr>
                            ) : (
                                inventories.data.map((inv, index) => {
                                    const cost = multiCurrency(inv.total_cost_afn);
                                    const sale = multiCurrency(inv.total_sale_afn);
                                    const low = inv.available_quantity > 0 && inv.available_quantity <= lowStockThreshold;

                                    return (
                                        <tr key={inv.id} className="border-b hover:bg-gray-50">
                                            <td className="px-4 py-3">{inventories.from ? inventories.from + index : index + 1}</td>
                                            <td className="px-4 py-3">
                                                <Link href={`/inventory/${inv.id}`} className="font-medium text-blue-600 hover:underline">
                                                    {inv.variant?.product?.brand?.name && (
                                                        <span className="text-gray-500">{inv.variant.product.brand.name} — </span>
                                                    )}
                                                    {inv.variant?.product?.name || inv.variant?.name || '—'}
                                                </Link>
                                                <div className="text-xs text-gray-500">
                                                    {inv.variant?.volume_ml ? `${inv.variant.volume_ml}ml` : ''}
                                                    {inv.variant?.concentration ? ` · ${String(inv.variant.concentration).toUpperCase()}` : ''}
                                                    {inv.variant?.sku ? ` · ${inv.variant.sku}` : ''}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="rounded-full border px-2 py-0.5 font-mono text-xs">{inv.branch?.code}</span>
                                            </td>
                                            <td className={`px-4 py-3 text-center font-mono font-semibold ${low ? 'text-amber-600' : inv.available_quantity <= 0 ? 'text-red-600' : ''}`}>
                                                {inv.available_quantity}
                                                {inv.reserved_quantity > 0 && (
                                                    <div className="text-[10px] font-normal text-gray-500">reserved {inv.reserved_quantity}</div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-xs">{money(inv.average_cost_afn)} AFN</td>
                                            <td className="px-4 py-3 text-right font-mono">
                                                <div className="font-semibold">{cost.afn} AFN</div>
                                                <div className="text-[10px] text-gray-500">{cost.usd} USD · {cost.aed} AED</div>
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono">
                                                <div className="font-semibold">{sale.afn} AFN</div>
                                                <div className="text-[10px] text-gray-500">{sale.usd} USD · {sale.aed} AED</div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {inventories.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {inventories.from || 0} to {inventories.to || 0} of {inventories.total} results
                        </div>
                        <div className="flex gap-1">
                            {inventories.links.map((link, i) => (
                                <button
                                    key={i}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
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
