// resources/js/pages/product-price-histories/index.tsx

import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import { RowActions, ViewAction } from '@/components/row-actions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface History {
    id: number;
    change_type: 'purchase' | 'sale' | 'both';
    change_type_label: string;
    old_purchase_price_afn: string;
    new_purchase_price_afn: string;
    old_sale_price_afn: string;
    new_sale_price_afn: string;
    exchange_rate: string;
    reason: string | null;
    created_at: string;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
    changer: { id: number; name: string } | null;
}

interface Props {
    histories: {
        data: History[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        variant_id: string | null;
        change_type: string | null;
        from: string | null;
        to: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Product Price Histories', href: '/product-price-histories' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const formatDateTime = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getChangeBadge = (type: string, label: string) => {
    const colors: Record<string, string> = {
        purchase: 'bg-blue-100 text-blue-800',
        sale: 'bg-green-100 text-green-800',
        both: 'bg-purple-100 text-purple-800',
    };
    return <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${colors[type]}`}>{label}</span>;
};

const PriceChange = ({ oldVal, newVal }: { oldVal: string; newVal: string }) => {
    const oldNum = parseFloat(oldVal);
    const newNum = parseFloat(newVal);
    const diff = newNum - oldNum;

    if (diff === 0) {
        return <span className="font-mono text-xs text-gray-400">—</span>;
    }

    return (
        <div className="text-right">
            <div className="font-mono text-sm">
                <span className="text-gray-400 line-through">{formatPrice(oldNum)}</span>
                <span className="mx-1">→</span>
                <span className="font-semibold">{formatPrice(newNum)}</span>
            </div>
            <div className={`font-mono text-xs ${diff > 0 ? 'text-red-600' : 'text-green-600'}`}>
                {diff > 0 ? '▲ +' : '▼ '}
                {formatPrice(diff)}
            </div>
        </div>
    );
};

export default function ProductPriceHistoriesIndex({ histories, filters }: Props) {
    const [typeFilter, setTypeFilter] = useState(filters.change_type || 'all');
    const [fromFilter, setFromFilter] = useState(filters.from || '');
    const [toFilter, setToFilter] = useState(filters.to || '');

    const handleSearch = () => {
        router.get(
            '/product-price-histories',
            {
                change_type: typeFilter === 'all' ? undefined : typeFilter,
                from: fromFilter || undefined,
                to: toFilter || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleClear = () => {
        setTypeFilter('all');
        setFromFilter('');
        setToFilter('');
        router.get('/product-price-histories', {}, { preserveState: true, preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Product Price Histories" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-bold tracking-tight">Price Change History</h1>
                    <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{histories.total} Records</span>
                </div>

                <IndexFilters onSearch={handleSearch} onClear={handleClear} showClear>
                    <FilterField label="Type">
                        <select value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Types</option>
                            <option value="purchase">Purchase Price</option>
                            <option value="sale">Sale Price</option>
                            <option value="both">Both</option>
                        </select>
                    </FilterField>
                    <FilterField label="From">
                        <input type="date" value={fromFilter} onChange={(e) => setFromFilter(e.target.value)} className={filterControlClass} />
                    </FilterField>
                    <FilterField label="To">
                        <input type="date" value={toFilter} onChange={(e) => setToFilter(e.target.value)} className={filterControlClass} />
                    </FilterField>
                </IndexFilters>

                {/* Table */}
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Product</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Type</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Purchase Price (AFN)</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Sale Price (AFN)</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">By</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {histories.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No price changes found.
                                    </td>
                                </tr>
                            ) : (
                                histories.data.map((history, index) => (
                                    <tr key={history.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3">{histories.from ? histories.from + index : index + 1}</td>
                                        <td className="px-4 py-3 text-xs">{formatDateTime(history.created_at)}</td>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {history.variant?.product?.brand?.name && (
                                                    <span className="text-gray-500">{history.variant.product.brand.name} — </span>
                                                )}
                                                {history.variant?.product?.name}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {history.variant?.volume_ml ? `${history.variant.volume_ml}ml` : ''}
                                                {history.variant?.concentration ? ` · ${history.variant.concentration.toUpperCase()}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">{getChangeBadge(history.change_type, history.change_type_label)}</td>
                                        <td className="px-4 py-3 text-right">
                                            {history.change_type === 'sale' ? (
                                                <span className="text-xs text-gray-400">—</span>
                                            ) : (
                                                <PriceChange oldVal={history.old_purchase_price_afn} newVal={history.new_purchase_price_afn} />
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {history.change_type === 'purchase' ? (
                                                <span className="text-xs text-gray-400">—</span>
                                            ) : (
                                                <PriceChange oldVal={history.old_sale_price_afn} newVal={history.new_sale_price_afn} />
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs">{history.changer?.name || 'System'}</td>
                                        <td className="px-4 py-3 text-center">
                                            <RowActions>
                                                <ViewAction href={`/product-price-histories/${history.id}`} />
                                            </RowActions>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {histories.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {histories.from || 0} to {histories.to || 0} of {histories.total} results
                        </div>
                        <div className="flex gap-1">
                            {histories.links.map((link, i) => (
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
