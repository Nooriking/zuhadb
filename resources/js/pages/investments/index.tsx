import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import { DeleteAction, RowActions, ViewAction } from '@/components/row-actions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Investment {
    id: number;
    amount_currency: string | number;
    amount_afn: string | number;
    exchange_rate: string | number | null;
    investment_date: string;
    status: 'active' | 'closed';
    notes: string | null;
    batches_count: number;
    total_cost_batches: string | number | null;
    investor: { id: number; name: string; email: string | null } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
}

interface InvestorOption {
    id: number;
    name: string;
}

interface CurrencyOption {
    id: number;
    code: string;
    symbol: string | null;
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
    summary: {
        total_invested_afn: number;
        total_batches: number;
        active_count: number;
    };
    investors: InvestorOption[];
    currencies: CurrencyOption[];
    filters: {
        investor_id?: string | null;
        status?: string | null;
        currency_id?: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
];

const formatMoney = (value: string | number | null | undefined) => {
    const num = typeof value === 'string' ? parseFloat(value) : Number(value ?? 0);
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number.isFinite(num) ? num : 0);
};

const statusBadge = (status: string) => {
    const map: Record<string, string> = {
        active: 'bg-blue-100 text-blue-800',
        closed: 'bg-gray-100 text-gray-800',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[status] || 'bg-gray-100 text-gray-800'}`}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

export default function InvestmentsIndex({ investments, summary, investors, currencies, filters }: Props) {
    const [investorFilter, setInvestorFilter] = useState(filters.investor_id ? String(filters.investor_id) : 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [currencyFilter, setCurrencyFilter] = useState(filters.currency_id ? String(filters.currency_id) : 'all');

    const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
        const next = {
            investor_id: investorFilter,
            status: statusFilter,
            currency_id: currencyFilter,
            ...overrides,
        };

        router.get(
            '/investments',
            {
                investor_id: next.investor_id === 'all' ? undefined : next.investor_id,
                status: next.status === 'all' ? undefined : next.status,
                currency_id: next.currency_id === 'all' ? undefined : next.currency_id,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const confirmDelete = (investment: Investment) => {
        const label = `#${investment.id}`;
        if (!confirm(`Delete investment ${label}? This only works when no batches are linked.`)) return;
        router.delete(`/investments/${investment.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Investments" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Investments</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{investments.total}</span>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/investors">
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Investors
                            </button>
                        </Link>
                        <Link href="/investments/create">
                            <button className="inline-flex items-center gap-2 rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                                New Investment
                            </button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Active capital (AFN)</p>
                        <p className="text-2xl font-bold">{formatMoney(summary.total_invested_afn)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Active investments</p>
                        <p className="text-2xl font-bold text-blue-600">{summary.active_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Linked batches</p>
                        <p className="text-2xl font-bold">{summary.total_batches}</p>
                    </div>
                </div>

                <IndexFilters>
                    <FilterField label="Investor">
                        <select
                            value={investorFilter}
                            onChange={(e) => {
                                setInvestorFilter(e.target.value);
                                applyFilters({ investor_id: e.target.value });
                            }}
                            className={filterControlClass}
                        >
                            <option value="all">All investors</option>
                            {investors.map((i) => (
                                <option key={i.id} value={String(i.id)}>
                                    {i.name}
                                </option>
                            ))}
                        </select>
                    </FilterField>
                    <FilterField label="Status">
                        <select
                            value={statusFilter}
                            onChange={(e) => {
                                setStatusFilter(e.target.value);
                                applyFilters({ status: e.target.value });
                            }}
                            className={filterControlClass}
                        >
                            <option value="all">All status</option>
                            <option value="active">Active</option>
                            <option value="closed">Closed</option>
                        </select>
                    </FilterField>
                    <FilterField label="Currency">
                        <select
                            value={currencyFilter}
                            onChange={(e) => {
                                setCurrencyFilter(e.target.value);
                                applyFilters({ currency_id: e.target.value });
                            }}
                            className={filterControlClass}
                        >
                            <option value="all">All currencies</option>
                            {currencies.map((c) => (
                                <option key={c.id} value={String(c.id)}>
                                    {c.code}
                                </option>
                            ))}
                        </select>
                    </FilterField>
                </IndexFilters>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investor</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Amount</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">AFN</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Batches</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Status</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {investments.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No investments found.
                                    </td>
                                </tr>
                            ) : (
                                investments.data.map((inv, index) => (
                                    <tr key={inv.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3">{investments.from ? investments.from + index : index + 1}</td>
                                        <td className="px-4 py-3">
                                            {inv.investor ? (
                                                <Link href={`/investors/${inv.investor.id}`} className="text-blue-600 hover:underline">
                                                    {inv.investor.name}
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-gray-600">
                                            {new Date(inv.investment_date).toLocaleDateString('en-US')}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {formatMoney(inv.amount_currency)} {inv.currency?.code || 'AFN'}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono font-semibold">{formatMoney(inv.amount_afn)}</td>
                                        <td className="px-4 py-3 text-center font-mono">{inv.batches_count}</td>
                                        <td className="px-4 py-3">{statusBadge(inv.status)}</td>
                                        <td className="px-4 py-3">
                                            <RowActions>
                                                <ViewAction href={`/investments/${inv.id}`} />
                                                {inv.status === 'active' && inv.batches_count === 0 && (
                                                    <DeleteAction onClick={() => confirmDelete(inv)} />
                                                )}
                                            </RowActions>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {investments.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {investments.from || 0} to {investments.to || 0} of {investments.total} results
                        </div>
                        <div className="flex gap-1">
                            {investments.links.map((link, i) => (
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
