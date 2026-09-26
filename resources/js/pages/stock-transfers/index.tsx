// resources/js/pages/stock-transfers/index.tsx

import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import { CancelAction, CompleteAction, DeleteAction, EditAction, RowActions, ViewAction } from '@/components/row-actions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Transfer {
    id: number;
    transfer_number: string;
    transfer_date: string;
    status: 'draft' | 'in_transit' | 'completed' | 'cancelled';
    items_count: number;
    notes: string | null;
    from_branch: { id: number; name: string; code: string } | null;
    to_branch: { id: number; name: string; code: string } | null;
    creator: { id: number; name: string } | null;
}

interface Props {
    transfers: {
        data: Transfer[];
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
        status: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Stock Transfers', href: '/stock-transfers' },
];

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US');
};

const getStatusBadge = (status: string) => {
    const map: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        in_transit: 'bg-yellow-100 text-yellow-800',
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[status] || map.draft}`}>
            {status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
        </span>
    );
};

export default function StockTransfersIndex({ transfers, branches, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');

    const handleSearch = () => {
        router.get(
            '/stock-transfers',
            {
                search: search || undefined,
                branch_id: branchFilter === 'all' ? undefined : branchFilter,
                status: statusFilter === 'all' ? undefined : statusFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleMarkCompleted = (transfer: Transfer) => {
        if (!confirm(`Mark "${transfer.transfer_number}" as completed? This will move inventory between branches.`)) return;

        router.patch(
            `/stock-transfers/${transfer.id}/mark-as-completed`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => alert('Transfer completed. Inventory updated.'),
                onError: (errors) => alert(errors.message || 'Failed. Check stock availability.'),
            },
        );
    };

    const handleCancel = (transfer: Transfer) => {
        if (!confirm(`Cancel "${transfer.transfer_number}"?`)) return;

        router.patch(`/stock-transfers/${transfer.id}/cancel`, {}, { preserveScroll: true });
    };

    const confirmDelete = (transfer: Transfer) => {
        if (!confirm(`Move "${transfer.transfer_number}" to trash?`)) return;
        router.delete(`/stock-transfers/${transfer.id}`, { preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stock Transfers" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Stock Transfers</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{transfers.total} Transfers</span>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/stock-transfers/trash">
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Trash
                            </button>
                        </Link>
                        <Link href="/stock-transfers/create">
                            <button className="inline-flex items-center gap-2 rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                                + New Transfer
                            </button>
                        </Link>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search transfer number...">
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
                    <FilterField label="Status">
                        <select
                            value={statusFilter}
                            onChange={(e) => {
                                setStatusFilter(e.target.value);
                                setTimeout(handleSearch, 0);
                            }}
                            className={filterControlClass}
                        >
                            <option value="all">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="in_transit">In Transit</option>
                            <option value="completed">Completed</option>
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
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Transfer #</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">From</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">To</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Items</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Status</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transfers.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="h-24 px-4 py-6 text-center text-gray-500">
                                        No transfers found.
                                    </td>
                                </tr>
                            ) : (
                                transfers.data.map((transfer, index) => (
                                    <tr key={transfer.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3">{transfers.from ? transfers.from + index : index + 1}</td>
                                        <td className="px-4 py-3">
                                            <div className="font-mono text-xs font-semibold">{transfer.transfer_number}</div>
                                            {transfer.creator && <div className="text-xs text-gray-500">By: {transfer.creator.name}</div>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full border px-2 py-0.5 text-xs">{transfer.from_branch?.code}</span>
                                            <div className="text-xs text-gray-500">{transfer.from_branch?.name}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full border px-2 py-0.5 text-xs">{transfer.to_branch?.code}</span>
                                            <div className="text-xs text-gray-500">{transfer.to_branch?.name}</div>
                                        </td>
                                        <td className="px-4 py-3 text-xs">{formatDate(transfer.transfer_date)}</td>
                                        <td className="px-4 py-3 text-center">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">{transfer.items_count}</span>
                                        </td>
                                        <td className="px-4 py-3">{getStatusBadge(transfer.status)}</td>
                                        <td className="px-4 py-3">
                                            <RowActions>
                                                {transfer.status === 'draft' && (
                                                    <>
                                                        <CompleteAction onClick={() => handleMarkCompleted(transfer)} />
                                                        <CancelAction onClick={() => handleCancel(transfer)} />
                                                    </>
                                                )}
                                                <ViewAction href={`/stock-transfers/${transfer.id}`} />
                                                {transfer.status !== 'completed' && (
                                                    <>
                                                        <EditAction href={`/stock-transfers/${transfer.id}/edit`} />
                                                        <DeleteAction onClick={() => confirmDelete(transfer)} />
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
                {transfers.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {transfers.from || 0} to {transfers.to || 0} of {transfers.total} results
                        </div>
                        <div className="flex gap-1">
                            {transfers.links.map((link, i) => (
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
