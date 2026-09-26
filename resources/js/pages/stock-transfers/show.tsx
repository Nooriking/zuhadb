// resources/js/pages/stock-transfers/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface TransferItem {
    id: number;
    quantity: number;
    from_batch: { id: number; batch_code: string; remaining_quantity: number } | null;
    to_batch: { id: number; batch_code: string } | null;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    } | null;
}

interface Transfer {
    id: number;
    transfer_number: string;
    transfer_date: string;
    status: 'draft' | 'in_transit' | 'completed' | 'cancelled';
    notes: string | null;
    from_branch: { id: number; name: string; code: string } | null;
    to_branch: { id: number; name: string; code: string } | null;
    creator: { id: number; name: string; email: string } | null;
    items: TransferItem[];
    created_at: string;
    updated_at: string;
}

interface Props {
    transfer: Transfer;
}

const breadcrumbs = (transfer: Transfer): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Stock Transfers', href: '/stock-transfers' },
    { title: transfer.transfer_number, href: `/stock-transfers/${transfer.id}` },
];

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const getStatusBadge = (status: string) => {
    const map: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800 border-gray-200',
        in_transit: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        completed: 'bg-green-100 text-green-800 border-green-200',
        cancelled: 'bg-red-100 text-red-800 border-red-200',
    };
    return (
        <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${map[status] || map.draft}`}>
            {status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
        </span>
    );
};

export default function StockTransfersShow({ transfer }: Props) {
    const handleMarkCompleted = () => {
        if (!confirm('Mark as completed? This will move inventory between branches.')) return;

        router.patch(
            `/stock-transfers/${transfer.id}/mark-as-completed`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => alert('Transfer completed.'),
                onError: (errors) => alert(errors.message || 'Failed.'),
            },
        );
    };

    const handleCancel = () => {
        if (!confirm('Cancel this transfer?')) return;
        router.patch(`/stock-transfers/${transfer.id}/cancel`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(transfer)}>
            <Head title={`Transfer ${transfer.transfer_number}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="font-mono text-xl font-bold">{transfer.transfer_number}</h1>
                            {getStatusBadge(transfer.status)}
                        </div>
                        <p className="mt-1 text-sm text-gray-500">{formatDate(transfer.transfer_date)}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {transfer.status === 'draft' && (
                            <>
                                <button
                                    onClick={handleMarkCompleted}
                                    className="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                                >
                                    ✓ Mark as Completed
                                </button>
                                <button
                                    onClick={handleCancel}
                                    className="rounded-md border border-orange-300 bg-white px-4 py-2 text-sm font-medium text-orange-600 hover:bg-orange-50"
                                >
                                    Cancel
                                </button>
                            </>
                        )}
                        {transfer.status !== 'completed' && (
                            <Link href={`/stock-transfers/${transfer.id}/edit`}>
                                <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                    Edit
                                </button>
                            </Link>
                        )}
                        <Link href="/stock-transfers">
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Back
                            </button>
                        </Link>
                    </div>
                </div>

                {/* From → To Visual */}
                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div className="text-center">
                            <p className="text-xs text-gray-500 uppercase">From Branch</p>
                            <p className="mt-2 text-xl font-bold">{transfer.from_branch?.name}</p>
                            <p className="font-mono text-sm text-gray-500">{transfer.from_branch?.code}</p>
                        </div>
                        <div className="flex items-center justify-center text-3xl text-gray-400">→</div>
                        <div className="text-center">
                            <p className="text-xs text-gray-500 uppercase">To Branch</p>
                            <p className="mt-2 text-xl font-bold">{transfer.to_branch?.name}</p>
                            <p className="font-mono text-sm text-gray-500">{transfer.to_branch?.code}</p>
                        </div>
                    </div>
                </div>

                {/* Info Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Created By</p>
                        <p className="font-medium">{transfer.creator?.name || 'System'}</p>
                        <p className="text-xs text-gray-500">{transfer.creator?.email}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Created At</p>
                        <p className="text-sm">{formatDate(transfer.created_at)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Total Items</p>
                        <p className="text-2xl font-bold">{transfer.items.length}</p>
                    </div>
                </div>

                {transfer.notes && (
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Notes</p>
                        <p className="mt-1 text-sm">{transfer.notes}</p>
                    </div>
                )}

                {/* Items Table */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Transfer Items ({transfer.items.length})</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-black text-white">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Product</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase">From Batch</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase">To Batch</th>
                                    <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transfer.items.map((item) => (
                                    <tr key={item.id} className="border-b">
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {item.variant?.product?.brand?.name && (
                                                    <span className="text-gray-500">{item.variant.product.brand.name} — </span>
                                                )}
                                                {item.variant?.product?.name}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {item.variant?.volume_ml ? `${item.variant.volume_ml}ml` : ''}
                                                {item.variant?.concentration ? ` · ${item.variant.concentration.toUpperCase()}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs">{item.from_batch?.batch_code || '—'}</td>
                                        <td className="px-4 py-3 font-mono text-xs">
                                            {item.to_batch?.batch_code || <span className="text-gray-400">Not yet</span>}
                                        </td>
                                        <td className="px-4 py-3 text-center font-mono font-bold">{item.quantity}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
