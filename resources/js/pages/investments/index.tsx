// resources/js/pages/investments/index.tsx

import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import { formatTotals, type MoneyTotals } from '@/lib/currencies';
import { DeleteAction, RowActions, ViewAction } from '@/components/row-actions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Investment {
    id: number;
    investment_number: string;
    capital_amount_currency?: string | null;
    currency_code?: string | null;
    capital_amount_afn: string;
    available_amount_afn: string;
    allocated_amount_afn: string;
    profit_amount_afn: string;
    investment_date: string;
    status: 'active' | 'partially_used' | 'fully_used' | 'withdrawn' | 'closed';
    purchase_allocations_count: number;
    product_allocations_count: number;
    investor: { id: number; full_name: string; phone: string | null } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
    creator: { id: number; name: string } | null;
}

interface Investor {
    id: number;
    full_name: string;
    phone: string | null;
}

interface Props {
    investments: {
        data: Investment[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    investors: Investor[];
    filters: {
        search: string | null;
        investor_id: string | null;
        status: string | null;
    };
    stats: {
        total_investments: number;
        active_investments: number;
        total_capital_afn: string;
        total_available_afn: string;
        total_allocated_afn: string;
        total_profit_afn: string;
        capital_totals?: MoneyTotals;
        profit_totals?: MoneyTotals;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const getStatusBadge = (status: string) => {
    const map: Record<string, string> = {
        active: 'bg-blue-100 text-blue-800',
        partially_used: 'bg-yellow-100 text-yellow-800',
        fully_used: 'bg-purple-100 text-purple-800',
        withdrawn: 'bg-orange-100 text-orange-800',
        closed: 'bg-gray-100 text-gray-800',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[status] || 'bg-gray-100 text-gray-800'}`}>
            {status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
        </span>
    );
};

export default function InvestmentsIndex({ investments, investors, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [investorFilter, setInvestorFilter] = useState(filters.investor_id || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');

    const handleSearch = () => {
        router.get(
            '/investments',
            {
                search: search || undefined,
                investor_id: investorFilter === 'all' ? undefined : investorFilter,
                status: statusFilter === 'all' ? undefined : statusFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const confirmDelete = (investment: Investment) => {
        if (!confirm(`Move "${investment.investment_number}" to trash?`)) return;
        router.delete(`/investments/${investment.id}`, { preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Investments" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Investments</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{investments.total} Investments</span>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/investments/trash">
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Trash
                            </button>
                        </Link>
                        <Link href="/investments/create">
                            <button className="inline-flex items-center gap-2 rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                                + New Investment
                            </button>
                        </Link>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Total</p>
                        <p className="text-2xl font-bold">{stats.total_investments}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Active</p>
                        <p className="text-2xl font-bold text-blue-600">{stats.active_investments}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Total Capital</p>
                        <p className="text-xl font-bold">{formatTotals(stats.capital_totals) || `${formatPrice(stats.total_capital_afn)} AFN`}</p>
                        <p className="text-muted-foreground text-xs">Ledger {formatPrice(stats.total_capital_afn)} AFN</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Available</p>
                        <p className="text-xl font-bold text-green-600">{formatPrice(stats.total_available_afn)} AFN</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Total Profit</p>
                        <p className="text-xl font-bold text-purple-600">{formatTotals(stats.profit_totals) || `${formatPrice(stats.total_profit_afn)} AFN`}</p>
                        <p className="text-muted-foreground text-xs">Ledger {formatPrice(stats.total_profit_afn)} AFN</p>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search by number or investor...">
                    <FilterField label="Investor">
                        <select value={investorFilter} onChange={(e) => { setInvestorFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Investors</option>
                            {investors.map((i) => (
                                <option key={i.id} value={String(i.id)}>{i.full_name}</option>
                            ))}
                        </select>
                    </FilterField>
                    <FilterField label="Status">
                        <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="partially_used">Partially Used</option>
                            <option value="fully_used">Fully Used</option>
                            <option value="withdrawn">Withdrawn</option>
                            <option value="closed">Closed</option>
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
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Capital</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Available</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Used</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Profit</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Status</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {investments.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No investments found.
                                    </td>
                                </tr>
                            ) : (
                                investments.data.map((inv, index) => {
                                    return (
                                        <tr key={inv.id} className="border-b hover:bg-gray-50">
                                            <td className="px-4 py-3">{investments.from ? investments.from + index : index + 1}</td>
                                            <td className="px-4 py-3">
                                                <div className="font-mono text-xs font-semibold">{inv.investment_number}</div>
                                                <div className="text-xs text-gray-500">
                                                    {new Date(inv.investment_date).toLocaleDateString('en-US')}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {inv.investor ? (
                                                    <Link href={`/investors/${inv.investor.id}`} className="text-blue-600 hover:underline">
                                                        {inv.investor.full_name}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono">
                                                <div className="font-semibold">
                                                    {formatPrice(inv.capital_amount_currency ?? inv.capital_amount_afn)}{' '}
                                                    {inv.currency?.code || inv.currency_code || 'AFN'}
                                                </div>
                                                {inv.currency?.code && inv.currency.code !== 'AFN' ? (
                                                    <div className="text-muted-foreground text-xs">{formatPrice(inv.capital_amount_afn)} AFN ledger</div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold">
                                                {formatPrice(inv.available_amount_afn)} AFN
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono">
                                                {formatPrice(inv.allocated_amount_afn)} AFN
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold">
                                                {formatPrice(inv.profit_amount_afn)} AFN
                                            </td>
                                            <td className="px-4 py-3">{getStatusBadge(inv.status)}</td>
                                            <td className="px-4 py-3">
                                                <RowActions>
                                                    <ViewAction href={`/investments/${inv.id}`} />
                                                    {!['closed', 'withdrawn'].includes(inv.status) && (
                                                        <DeleteAction onClick={() => confirmDelete(inv)} />
                                                    )}
                                                </RowActions>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {investments.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {investments.from || 0} to {investments.to || 0} of {investments.total} results
                        </div>
                        <div className="flex gap-1">
                            {investments.links.map((link, i) => (
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
