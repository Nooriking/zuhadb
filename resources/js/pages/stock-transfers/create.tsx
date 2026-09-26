import { BackButton } from '@/components/form-page';
// resources/js/pages/stock-transfers/create.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Branch {
    id: number;
    name: string;
    code: string;
}

interface Batch {
    id: number;
    batch_code: string;
    quantity: number;
    remaining_quantity: number;
    unit_cost_afn: string;
    branch_id: number;
    branch: { id: number; name: string } | null;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    } | null;
}

interface LineItem {
    _key: string;
    from_batch_id: string;
    quantity: string;
}

interface Props {
    branches: Branch[];
    batches: Batch[];
    nextNumber: string;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Stock Transfers', href: '/stock-transfers' },
    { title: 'Create', href: '/stock-transfers/create' },
];

const newLine = (): LineItem => ({
    _key: Math.random().toString(36).slice(2),
    from_batch_id: '',
    quantity: '1',
});

export default function StockTransfersCreate({ branches, batches, nextNumber, errors: initialErrors }: Props) {
    const [form, setForm] = useState({
        from_branch_id: branches[0] ? String(branches[0].id) : '',
        to_branch_id: branches[1] ? String(branches[1].id) : '',
        transfer_date: new Date().toISOString().slice(0, 10),
        status: 'draft' as 'draft' | 'in_transit' | 'completed' | 'cancelled',
        notes: '',
    });
    const [items, setItems] = useState<LineItem[]>([newLine()]);
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    // Batches فیلترشده بر اساس from_branch_id
    const availableBatches = useMemo(() => {
        return batches.filter((b) => String(b.branch_id) === form.from_branch_id);
    }, [batches, form.from_branch_id]);

    const updateItem = (index: number, field: keyof LineItem, value: string) => {
        setItems((prev) => {
            const copy = [...prev];
            copy[index] = { ...copy[index], [field]: value };

            if (field === 'from_batch_id' && value) {
                const batch = batches.find((b) => b.id === parseInt(value));
                if (batch) {
                    copy[index].quantity = '1';
                }
            }
            return copy;
        });
    };

    const addItem = () => setItems((prev) => [...prev, newLine()]);
    const removeItem = (index: number) => {
        if (items.length === 1) return;
        setItems((prev) => prev.filter((_, i) => i !== index));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        const payload = {
            ...form,
            items: items.map((it) => ({
                from_batch_id: parseInt(it.from_batch_id),
                quantity: parseInt(it.quantity),
            })),
        };

        router.post('/stock-transfers', payload, {
            onSuccess: () => setLoading(false),
            onError: (err) => {
                setErrors(err);
                setLoading(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Stock Transfer" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/stock-transfers" label="Back" />
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Create Stock Transfer</h1>
                        <span className="font-mono text-sm text-gray-500">{nextNumber}</span>
                    </div>
                </div>

                {/* Transfer Info */}
                <div className="bg-card rounded-lg border shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Transfer Information</h2>
                    </div>
                    <div className="space-y-6 p-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    From Branch <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.from_branch_id}
                                    onChange={(e) => setForm({ ...form, from_branch_id: e.target.value })}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${
                                        errors.from_branch_id ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                >
                                    <option value="">-- Select Source --</option>
                                    {branches.map((b) => (
                                        <option key={b.id} value={String(b.id)}>
                                            {b.name} ({b.code})
                                        </option>
                                    ))}
                                </select>
                                {errors.from_branch_id && <p className="text-sm text-red-600">{errors.from_branch_id}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    To Branch <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.to_branch_id}
                                    onChange={(e) => setForm({ ...form, to_branch_id: e.target.value })}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${
                                        errors.to_branch_id ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                >
                                    <option value="">-- Select Destination --</option>
                                    {branches
                                        .filter((b) => String(b.id) !== form.from_branch_id)
                                        .map((b) => (
                                            <option key={b.id} value={String(b.id)}>
                                                {b.name} ({b.code})
                                            </option>
                                        ))}
                                </select>
                                {errors.to_branch_id && <p className="text-sm text-red-600">{errors.to_branch_id}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Transfer Date <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={form.transfer_date}
                                    onChange={(e) => setForm({ ...form, transfer_date: e.target.value })}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    required
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Status</label>
                                <select
                                    value={form.status}
                                    onChange={(e) => setForm({ ...form, status: e.target.value as any })}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option value="draft">Draft (save without moving)</option>
                                    <option value="in_transit">In Transit</option>
                                </select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea
                                value={form.notes}
                                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                rows={2}
                                placeholder="Optional notes..."
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                </div>

                {/* Items */}
                <div className="bg-card rounded-lg border shadow-sm">
                    <div className="flex items-center justify-between border-b px-6 py-4">
                        <div>
                            <h2 className="text-lg font-semibold">Items to Transfer</h2>
                            <p className="text-sm text-gray-500">Only batches from the source branch are shown.</p>
                        </div>
                        <button
                            type="button"
                            onClick={addItem}
                            className="rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                        >
                            + Add Item
                        </button>
                    </div>
                    <div className="space-y-3 p-6">
                        {availableBatches.length === 0 && form.from_branch_id && (
                            <div className="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
                                No available batches in this branch.
                            </div>
                        )}

                        {items.map((item, index) => {
                            const selectedBatch = batches.find((b) => b.id === parseInt(item.from_batch_id));
                            const maxQty = selectedBatch?.remaining_quantity || 0;

                            return (
                                <div key={item._key} className="rounded-lg border p-3">
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-12">
                                        <div className="space-y-2 md:col-span-7">
                                            <label className="text-xs font-medium">Product Batch</label>
                                            <select
                                                value={item.from_batch_id}
                                                onChange={(e) => updateItem(index, 'from_batch_id', e.target.value)}
                                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                            >
                                                <option value="">-- Select Batch --</option>
                                                {availableBatches.map((b) => (
                                                    <option key={b.id} value={String(b.id)}>
                                                        {b.batch_code} — {b.variant?.product?.brand?.name ? `${b.variant.product.brand.name} — ` : ''}
                                                        {b.variant?.product?.name}
                                                        {b.variant?.volume_ml ? ` · ${b.variant.volume_ml}ml` : ''}
                                                        {b.variant?.concentration ? ` · ${b.variant.concentration.toUpperCase()}` : ''} (
                                                        {b.remaining_quantity} available)
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="space-y-2 md:col-span-3">
                                            <label className="text-xs font-medium">Quantity (max: {maxQty})</label>
                                            <input
                                                type="number"
                                                min="1"
                                                max={maxQty}
                                                value={item.quantity}
                                                onChange={(e) => updateItem(index, 'quantity', e.target.value)}
                                                className="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
                                            />
                                        </div>

                                        <div className="flex items-end justify-end md:col-span-2">
                                            {items.length > 1 && (
                                                <button type="button" onClick={() => removeItem(index)} className="text-red-600 hover:text-red-800">
                                                    ✕ Remove
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Submit */}
                <div className="flex gap-2">
                    <button
                        type="submit"
                        disabled={loading}
                        className="rounded-md bg-black px-6 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                    >
                        {loading ? 'Saving...' : 'Create Transfer'}
                    </button>
                    <BackButton href="/stock-transfers" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
