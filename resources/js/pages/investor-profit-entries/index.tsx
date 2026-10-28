// resources/js/pages/investor-profit-entries/index.tsx

import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import { formatTotals, type MoneyTotals } from '@/lib/currencies';
import { CancelAction, PayAction, RowActions, ViewAction } from '@/components/row-actions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Entry {
    id: number;
    quantity: number;
    profit_per_unit_afn: string;
    profit_amount_currency: string;
    profit_amount_afn: string;
    entry_type: 'profit' | 'loss' | 'adjustment';
    status: 'pending' | 'paid' | 'cancelled';
    earned_at: string | null;
    paid_at: string | null;
    investor: { id: number; full_name: string; phone: string | null } | null;
    investment: { id: number; investment_number: string } | null;
    sale: { id: number; invoice_number: string } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
    payer: { id: number; name: string } | null;
}

interface Props {
    entries: {
        data: Entry[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    investors: { id: number; full_name: string }[];
    investments: { id: number; investment_number: string }[];
    filters: {
        investor_id: string | null;
        investment_id: string | null;
        status: string | null;
        entry_type: string | null;
    };
    stats: {
        total_entries: number;
        pending_count: number;
        paid_count: number;
        total_profit_afn: string;
        pending_profit: string;
        paid_profit: string;
        profit_totals?: MoneyTotals;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investor Profit Entries', href: '/investor-profit-entries' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US');
};

const getStatusBadge = (status: string) => {
    const map: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        paid: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[status]}`}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

export default function InvestorProfitEntriesIndex({ entries, investors, investments, filters, stats }: Props) {
    const [investorFilter, setInvestorFilter] = useState(filters.investor_id || 'all');
    const [investmentFilter, setInvestmentFilter] = useState(filters.investment_id || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [typeFilter, setTypeFilter] = useState(filters.entry_type || 'all');
    const [selectedEntries, setSelectedEntries] = useState<number[]>([]);

    const handleSearch = () => {
        router.get(
            '/investor-profit-entries',
            {
                investor_id: investorFilter === 'all' ? undefined : investorFilter,
                investment_id: investmentFilter === 'all' ? undefined : investmentFilter,
                status: statusFilter === 'all' ? undefined : statusFilter,
                entry_type: typeFilter === 'all' ? undefined : typeFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleMarkPaid = (entry: Entry) => {
        if (!confirm(`Mark this entry as paid? Amount: ${formatPrice(entry.profit_amount_currency ?? entry.profit_amount_afn)} ${entry.currency?.code || 'AFN'}`)) return;

        router.patch(`/investor-profit-entries/${entry.id}/mark-as-paid`, {}, { preserveScroll: true });
    };

    const handleCancel = (entry: Entry) => {
        const reason = prompt('Reason for cancellation:');
        if (reason === null) return;

        router.patch(`/investor-profit-entries/${entry.id}/cancel`, { reason }, { preserveScroll: true });
    };

    const handleBulkPay = () => {
        if (selectedEntries.length === 0) return;

        if (!confirm(`Mark ${selectedEntries.length} entries as paid?`)) return;

        router.post(
            '/investor-profit-entries/bulk-pay',
            { ids: selectedEntries },
            {
                preserveScroll: true,
                onSuccess: () => setSelectedEntries([]),
            },
        );
    };

    const toggleSelection = (id: number) => {
        setSelectedEntries((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const pendingIds = entries.data.filter((e) => e.status === 'pending').map((e) => e.id);
        if (selectedEntries.length === pendingIds.length) {
            setSelectedEntries([]);
        } else {
            setSelectedEntries(pendingIds);
        }
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Investor Profit Entries" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Investor Profit Entries</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{entries.total} Entries</span>
                    </div>
                    {selectedEntries.length > 0 && (
                        <button
                            onClick={handleBulkPay}
                            className="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                        >
                            Mark {selectedEntries.length} as Paid
                        </button>
                    )}
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Total Profit</p>
                        <p className="text-xl font-bold">{formatTotals(stats.profit_totals) || `${formatPrice(stats.total_profit_afn)} AFN`}</p>
                        <p className="text-muted-foreground text-xs">Ledger {formatPrice(stats.total_profit_afn)} AFN</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Pending</p>
                        <p className="text-xl font-bold text-yellow-600">{formatPrice(stats.pending_profit)} AFN</p>
                        <p className="text-xs text-gray-500">{stats.pending_count} entries</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Paid</p>
                        <p className="text-xl font-bold text-green-600">{formatPrice(stats.paid_profit)} AFN</p>
                        <p className="text-xs text-gray-500">{stats.paid_count} entries</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Total Entries</p>
                        <p className="text-xl font-bold">{stats.total_entries}</p>
                    </div>
                </div>

                <IndexFilters>
                    <FilterField label="Investor">
                        <select value={investorFilter} onChange={(e) => { setInvestorFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Investors</option>
                            {investors.map((i) => (
                                <option key={i.id} value={String(i.id)}>{i.full_name}</option>
                            ))}
                        </select>
                    </FilterField>
                    <FilterField label="Investment">
                        <select value={investmentFilter} onChange={(e) => { setInvestmentFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Investments</option>
                            {investments.map((i) => (
                                <option key={i.id} value={String(i.id)}>{i.investment_number}</option>
                            ))}
                        </select>
                    </FilterField>
                    <FilterField label="Status">
                        <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </FilterField>
                    <FilterField label="Type">
                        <select value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setTimeout(handleSearch, 0); }} className={filterControlClass}>
                            <option value="all">All Types</option>
                            <option value="profit">Profit</option>
                            <option value="loss">Loss</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </FilterField>
                </IndexFilters>

                {/* Table */}
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="w-12 px-4 py-3 text-left">
                                    <input
                                        type="checkbox"
                                        onChange={toggleAll}
                                        checked={
                                            entries.data.filter((e) => e.status === 'pending').length > 0 &&
                                            selectedEntries.length === entries.data.filter((e) => e.status === 'pending').length
                                        }
                                        className="cursor-pointer"
                                    />
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investor</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investment</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Sale</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Qty</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Profit/Unit</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Total Profit</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Status</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.length === 0 ? (
                                <tr>
                                    <td colSpan={11} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No entries found.
                                    </td>
                                </tr>
                            ) : (
                                entries.data.map((entry, index) => (
                                    <tr key={entry.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            {entry.status === 'pending' && (
                                                <input
                                                    type="checkbox"
                                                    checked={selectedEntries.includes(entry.id)}
                                                    onChange={() => toggleSelection(entry.id)}
                                                    className="cursor-pointer"
                                                />
                                            )}
                                        </td>
                                        <td className="px-4 py-3">{entries.from ? entries.from + index : index + 1}</td>
                                        <td className="px-4 py-3 text-xs">{formatDate(entry.earned_at)}</td>
                                        <td className="px-4 py-3">
                                            {entry.investor && (
                                                <Link href={`/investors/${entry.investor.id}`} className="text-blue-600 hover:underline">
                                                    {entry.investor.full_name}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {entry.investment && (
                                                <Link
                                                    href={`/investments/${entry.investment.id}`}
                                                    className="font-mono text-xs text-blue-600 hover:underline"
                                                >
                                                    {entry.investment.investment_number}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {entry.sale && (
                                                <Link href={`/sales/${entry.sale.id}`} className="font-mono text-xs text-blue-600 hover:underline">
                                                    {entry.sale.invoice_number}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center font-mono">{entry.quantity}</td>
                                        <td className="px-4 py-3 text-right font-mono text-xs">{formatPrice(entry.profit_per_unit_afn)}</td>
                                        <td className="px-4 py-3 text-right font-mono font-semibold text-green-600">
                                            {formatPrice(entry.profit_amount_currency ?? entry.profit_amount_afn)}{' '}
                                            {entry.currency?.code || 'AFN'}
                                            {entry.currency?.code && entry.currency.code !== 'AFN' ? (
                                                <div className="text-muted-foreground text-xs font-normal">{formatPrice(entry.profit_amount_afn)} AFN</div>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3">{getStatusBadge(entry.status)}</td>
                                        <td className="px-4 py-3">
                                            <RowActions>
                                                <ViewAction href={`/investor-profit-entries/${entry.id}`} />
                                                {entry.status === 'pending' && (
                                                    <>
                                                        <PayAction onClick={() => handleMarkPaid(entry)} />
                                                        <CancelAction onClick={() => handleCancel(entry)} />
                                                    </>
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
                {entries.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {entries.from || 0} to {entries.to || 0} of {entries.total} results
                        </div>
                        <div className="flex gap-1">
                            {entries.links.map((link, i) => (
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
