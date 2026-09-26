// resources/js/pages/investor-transactions/index.tsx

import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import { RowActions, ViewAction } from '@/components/row-actions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Transaction {
    id: number;
    type: string;
    type_label: string;
    amount_currency: string;
    amount_afn: string;
    balance_type: 'capital' | 'profit';
    balance_before_afn: string;
    balance_after_afn: string;
    transaction_date: string;
    description: string | null;
    investor: { id: number; full_name: string; phone: string | null } | null;
    investment: { id: number; investment_number: string } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
    creator: { id: number; name: string } | null;
}

interface Props {
    transactions: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    investors: { id: number; full_name: string; phone: string | null }[];
    types: Record<string, string>;
    filters: {
        investor_id: string | null;
        type: string | null;
        from: string | null;
        to: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investor Transactions', href: '/investor-transactions' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const getTypeBadge = (type: string, label: string) => {
    const colors: Record<string, string> = {
        capital_deposit: 'bg-green-100 text-green-800',
        capital_withdrawn: 'bg-red-100 text-red-800',
        profit_earned: 'bg-blue-100 text-blue-800',
        profit_withdrawn: 'bg-orange-100 text-orange-800',
        reinvestment: 'bg-purple-100 text-purple-800',
        adjustment: 'bg-gray-100 text-gray-800',
        loss: 'bg-red-100 text-red-800',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${colors[type] || 'bg-gray-100 text-gray-800'}`}>
            {label}
        </span>
    );
};

export default function InvestorTransactionsIndex({ transactions, investors, types, filters }: Props) {
    const [investorFilter, setInvestorFilter] = useState(filters.investor_id || 'all');
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');
    const [fromFilter, setFromFilter] = useState(filters.from || '');
    const [toFilter, setToFilter] = useState(filters.to || '');

    const handleSearch = () => {
        router.get(
            '/investor-transactions',
            {
                investor_id: investorFilter === 'all' ? undefined : investorFilter,
                type: typeFilter === 'all' ? undefined : typeFilter,
                from: fromFilter || undefined,
                to: toFilter || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleClear = () => {
        setInvestorFilter('all');
        setTypeFilter('all');
        setFromFilter('');
        setToFilter('');
        router.get('/investor-transactions', {}, { preserveState: true, preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Investor Transactions" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-bold tracking-tight">Investor Transactions</h1>
                    <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{transactions.total} Transactions</span>
                </div>

                <IndexFilters onSearch={handleSearch} onClear={handleClear} showClear>
                    <FilterField label="Investor">
                        <select value={investorFilter} onChange={(e) => { setInvestorFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Investors</option>
                            {investors.map((i) => (
                                <option key={i.id} value={String(i.id)}>{i.full_name}</option>
                            ))}
                        </select>
                    </FilterField>
                    <FilterField label="Type">
                        <select value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Types</option>
                            {Object.entries(types).map(([key, label]) => (
                                <option key={key} value={key}>{label}</option>
                            ))}
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
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investor</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Type</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investment</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Amount</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Balance Type</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Before â†’ After</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No transactions found.
                                    </td>
                                </tr>
                            ) : (
                                transactions.data.map((transaction, index) => (
                                    <tr key={transaction.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3">{transactions.from ? transactions.from + index : index + 1}</td>
                                        <td className="px-4 py-3 text-xs">{new Date(transaction.transaction_date).toLocaleDateString('en-US')}</td>
                                        <td className="px-4 py-3">
                                            {transaction.investor && (
                                                <Link href={`/investors/${transaction.investor.id}`} className="text-blue-600 hover:underline">
                                                    {transaction.investor.full_name}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">{getTypeBadge(transaction.type, transaction.type_label)}</td>
                                        <td className="px-4 py-3">
                                            {transaction.investment ? (
                                                <Link
                                                    href={`/investments/${transaction.investment.id}`}
                                                    className="font-mono text-xs text-blue-600 hover:underline"
                                                >
                                                    {transaction.investment.investment_number}
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="font-mono font-semibold">
                                                {formatPrice(transaction.amount_afn)} USD
                                            </div>
                                            <div className="text-xs text-gray-500">{formatPrice(transaction.amount_afn)} USD</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">
                                                {transaction.balance_type}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-xs text-gray-500">
                                            {formatPrice(transaction.balance_before_afn)} â†’ {formatPrice(transaction.balance_after_afn)}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <RowActions>
                                                <ViewAction href={`/investor-transactions/${transaction.id}`} />
                                            </RowActions>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {transactions.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {transactions.from || 0} to {transactions.to || 0} of {transactions.total} results
                        </div>
                        <div className="flex gap-1">
                            {transactions.links.map((link, i) => (
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
