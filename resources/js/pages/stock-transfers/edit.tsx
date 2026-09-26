// resources/js/pages/stock-transfers/edit.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Transfer {
    id: number;
    transfer_number: string;
    transfer_date: string;
    status: 'draft' | 'in_transit' | 'completed' | 'cancelled';
    notes: string | null;
    from_branch: { id: number; name: string; code: string } | null;
    to_branch: { id: number; name: string; code: string } | null;
}

interface Props {
    transfer: Transfer;
    errors?: Record<string, string>;
}

const breadcrumbs = (transfer: Transfer): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Stock Transfers', href: '/stock-transfers' },
    { title: transfer.transfer_number, href: `/stock-transfers/${transfer.id}` },
    { title: 'Edit', href: `/stock-transfers/${transfer.id}/edit` },
];

export default function StockTransfersEdit({ transfer, errors: initialErrors }: Props) {
    const [form, setForm] = useState({
        transfer_date: transfer.transfer_date,
        status: transfer.status,
        notes: transfer.notes || '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.put(`/stock-transfers/${transfer.id}`, form, {
            onSuccess: () => setLoading(false),
            onError: (err) => {
                setErrors(err);
                setLoading(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(transfer)}>
            <Head title={`Edit ${transfer.transfer_number}`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Edit Transfer</h1>
                        <span className="font-mono text-sm text-gray-500">{transfer.transfer_number}</span>
                    </div>
                    <Link href={`/stock-transfers/${transfer.id}`}>
                        <button type="button" className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Back
                        </button>
                    </Link>
                </div>

                {/* Info box */}
                <div className="rounded-lg border border-dashed bg-gray-50 p-4">
                    <p className="text-sm text-gray-600">
                        <strong>Note:</strong> Only metadata can be edited. Transfer items cannot be modified after creation. If you need to change
                        items, cancel this transfer and create a new one.
                    </p>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Transfer Information</h2>
                    </div>
                    <div className="space-y-6 p-6">
                        {/* Read-only branch info */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-xs text-gray-500">From Branch</p>
                                <p className="font-medium">{transfer.from_branch?.name}</p>
                                <p className="font-mono text-xs text-gray-500">{transfer.from_branch?.code}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">To Branch</p>
                                <p className="font-medium">{transfer.to_branch?.name}</p>
                                <p className="font-mono text-xs text-gray-500">{transfer.to_branch?.code}</p>
                            </div>
                        </div>

                        <hr className="border-gray-200" />

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Transfer Date <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={form.transfer_date}
                                    onChange={(e) => setForm({ ...form, transfer_date: e.target.value })}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${
                                        errors.transfer_date ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                />
                                {errors.transfer_date && <p className="text-sm text-red-600">{errors.transfer_date}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Status</label>
                                <select
                                    value={form.status}
                                    onChange={(e) => setForm({ ...form, status: e.target.value as any })}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option value="draft">Draft</option>
                                    <option value="in_transit">In Transit</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea
                                value={form.notes}
                                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                rows={3}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                </div>

                <div className="flex gap-2">
                    <button
                        type="submit"
                        disabled={loading}
                        className="rounded-md bg-black px-6 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                    >
                        {loading ? 'Saving...' : 'Update Transfer'}
                    </button>
                    <Link href={`/stock-transfers/${transfer.id}`}>
                        <button type="button" className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
