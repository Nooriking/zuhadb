// resources/js/pages/stock-transfers/trash.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Transfer {
    id: number;
    transfer_number: string;
    transfer_date: string;
    status: string;
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
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Stock Transfers', href: '/stock-transfers' },
    { title: 'Trash', href: '/stock-transfers/trash' },
];

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US');
};

export default function StockTransfersTrash({ transfers }: Props) {
    const handleRestore = (transfer: Transfer) => {
        if (!confirm(`Restore "${transfer.transfer_number}"?`)) return;
        router.post(`/stock-transfers/${transfer.id}/restore`, {}, { preserveScroll: true });
    };

    const handleForceDelete = (transfer: Transfer) => {
        if (!confirm(`Permanently delete "${transfer.transfer_number}"? This cannot be undone.`)) return;
        router.delete(`/stock-transfers/${transfer.id}/force-delete`, { preserveScroll: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Stock Transfers" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Trashed Transfers</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{transfers.total} Transfers</span>
                    </div>
                    <Link href="/stock-transfers">
                        <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Back to Transfers
                        </button>
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Transfer #</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">From</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">To</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Status</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transfers.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="h-24 px-4 py-6 text-center text-gray-500">
                                        Trash is empty.
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
                                        <td className="px-4 py-3 text-sm">{transfer.from_branch?.name || '—'}</td>
                                        <td className="px-4 py-3 text-sm">{transfer.to_branch?.name || '—'}</td>
                                        <td className="px-4 py-3 text-xs">{formatDate(transfer.transfer_date)}</td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">{transfer.status}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-center gap-3">
                                                <button
                                                    onClick={() => handleRestore(transfer)}
                                                    className="text-xs font-semibold text-green-600 hover:text-green-800"
                                                >
                                                    Restore
                                                </button>
                                                <button
                                                    onClick={() => handleForceDelete(transfer)}
                                                    className="text-xs font-semibold text-red-600 hover:text-red-800"
                                                >
                                                    Delete Forever
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

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
