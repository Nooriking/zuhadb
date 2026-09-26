import { BackButton } from '@/components/form-page';
// resources/js/pages/investment-product-allocations/create.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Batch {
    id: number;
    batch_code: string;
    quantity: number;
    remaining_quantity: number;
    unit_cost_currency: string;
    unit_cost_afn: string;
    unit_cost_usd?: number | null;
    exchange_rate: string;
    profit_per_unit_afn?: string;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    } | null;
    branch: { id: number; name: string; code: string } | null;
}

interface Investment {
    id: number;
    investment_number: string;
    exchange_rate: string;
    available_amount_afn: string;
    investor: { id: number; full_name: string } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
}

interface Props {
    batches: Batch[];
    investments: Investment[];
    preInvestmentId?: string | null;
    preBatchId?: string | null;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Product Allocations', href: '/investment-product-allocations' },
    { title: 'Create', href: '/investment-product-allocations/create' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

export default function InvestmentProductAllocationsCreate({ batches, investments, preInvestmentId, preBatchId, errors: initialErrors }: Props) {
    const [form, setForm] = useState({
        investment_id: preInvestmentId ? String(preInvestmentId) : investments[0] ? String(investments[0].id) : '',
        batch_id: preBatchId ? String(preBatchId) : batches[0] ? String(batches[0].id) : '',
        funded_quantity: '',
        cost_per_unit_afn: batches[0]?.unit_cost_usd != null ? String(batches[0].unit_cost_usd) : '0',
        profit_per_unit_afn: batches[0]?.profit_per_unit_afn || '0',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const selectedBatch = useMemo(() => batches.find((b) => b.id === parseInt(form.batch_id)), [batches, form.batch_id]);

    const selectedInvestment = useMemo(() => investments.find((i) => i.id === parseInt(form.investment_id)), [investments, form.investment_id]);

    // وقتی Batch تغییر می‌کند، مقادیر پیش‌فرض را پر کن
    const handleBatchChange = (batchId: string) => {
        const batch = batches.find((b) => b.id === parseInt(batchId));
        if (batch) {
            setForm((prev) => ({
                ...prev,
                batch_id: batchId,
                cost_per_unit_afn: batch.unit_cost_usd != null ? String(batch.unit_cost_usd) : '0',
                profit_per_unit_afn: batch.profit_per_unit_afn || '0',
            }));
        }
    };

    // محاسبه خودکار سود بر اساس مبلغ موجود در Investment
    const handleCalculateProfit = () => {
        if (!selectedBatch) return;

        const costUsd = selectedBatch.unit_cost_usd ?? 0;
        const profitAfn = costUsd * 0.2;

        setForm((prev) => ({
            ...prev,
            profit_per_unit_afn: profitAfn.toFixed(2),
        }));
    };

    const totalCostAfn = useMemo(() => {
        const qty = parseFloat(form.funded_quantity) || 0;
        return qty * parseFloat(form.cost_per_unit_afn);
    }, [form.funded_quantity, form.cost_per_unit_afn]);

    const totalProfitAfn = useMemo(() => {
        const qty = parseFloat(form.funded_quantity) || 0;
        return qty * parseFloat(form.profit_per_unit_afn);
    }, [form.funded_quantity, form.profit_per_unit_afn]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.post('/investment-product-allocations', form, {
            onSuccess: () => setLoading(false),
            onError: (err) => {
                setErrors(err);
                setLoading(false);
            },
        });
    };

    const maxFundableQty = useMemo(() => {
        if (!selectedBatch) return 0;
        return selectedBatch.quantity; // Simplified — در بک‌اند دقیق بررسی می‌شود
    }, [selectedBatch]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Product Allocation" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/investment-product-allocations" label="Back" />
                    <h1 className="text-2xl font-bold tracking-tight">Create Product Allocation</h1>
                </div>

                <div className="bg-card rounded-lg border shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Allocation Details</h2>
                        <p className="text-sm text-gray-500">Link an investment to a product batch and specify profit per unit.</p>
                    </div>
                    <div className="space-y-6 p-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Investment <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.investment_id}
                                    onChange={(e) => setForm({ ...form, investment_id: e.target.value })}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${
                                        errors.investment_id ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                >
                                    <option value="">-- Select Investment --</option>
                                    {investments.map((i) => (
                                        <option key={i.id} value={String(i.id)}>
                                            {i.investment_number} — {i.investor?.full_name} ({formatPrice(i.available_amount_afn)} USD available)
                                        </option>
                                    ))}
                                </select>
                                {errors.investment_id && <p className="text-sm text-red-600">{errors.investment_id}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Batch <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.batch_id}
                                    onChange={(e) => handleBatchChange(e.target.value)}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${errors.batch_id ? 'border-red-500' : 'border-gray-300'}`}
                                    required
                                >
                                    <option value="">-- Select Batch --</option>
                                    {batches.map((b) => (
                                        <option key={b.id} value={String(b.id)}>
                                            {b.batch_code} — {b.variant?.product?.brand?.name} {b.variant?.product?.name} ({b.quantity} units)
                                        </option>
                                    ))}
                                </select>
                                {errors.batch_id && <p className="text-sm text-red-600">{errors.batch_id}</p>}
                            </div>
                        </div>

                        {/* Info Cards */}
                        {selectedBatch && (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div className="rounded-lg border bg-blue-50 p-4">
                                    <p className="text-xs text-gray-600">Batch Quantity</p>
                                    <p className="text-lg font-bold text-blue-700">{selectedBatch.quantity} units</p>
                                    <p className="text-xs text-gray-500">Remaining: {selectedBatch.remaining_quantity}</p>
                                </div>
                                <div className="rounded-lg border bg-orange-50 p-4">
                                    <p className="text-xs text-gray-600">Unit Cost (from batch)</p>
                                    <p className="text-lg font-bold text-orange-700">
                                        {selectedBatch.unit_cost_usd != null ? `${formatPrice(selectedBatch.unit_cost_usd)} USD` : 'Rate missing'}
                                    </p>
                                </div>
                                {selectedInvestment && (
                                    <div className="rounded-lg border bg-green-50 p-4">
                                        <p className="text-xs text-gray-600">Available in Investment</p>
                                        <p className="text-lg font-bold text-green-700">{formatPrice(selectedInvestment.available_amount_afn)} USD</p>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Funded Quantity <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max={maxFundableQty}
                                    value={form.funded_quantity}
                                    onChange={(e) => setForm({ ...form, funded_quantity: e.target.value })}
                                    placeholder="e.g. 20"
                                    className={`w-full rounded-md border px-3 py-2 font-mono text-sm ${
                                        errors.funded_quantity ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                />
                                {errors.funded_quantity && <p className="text-sm text-red-600">{errors.funded_quantity}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Cost per Unit (USD) <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={form.cost_per_unit_afn}
                                    onChange={(e) => setForm({ ...form, cost_per_unit_afn: e.target.value })}
                                    className={`w-full rounded-md border px-3 py-2 font-mono text-sm ${
                                        errors.cost_per_unit_afn ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                />
                            </div>
                        </div>

                        <div className="rounded-lg border border-dashed p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="text-sm font-semibold">Profit Settings (Investor's share per unit)</h3>
                                <button
                                    type="button"
                                    onClick={handleCalculateProfit}
                                    className="rounded-md bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                >
                                    Auto Calculate (20%)
                                </button>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Profit per Unit (USD) <span className="text-red-500">*</span>
                                </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={form.profit_per_unit_afn}
                                        onChange={(e) => setForm({ ...form, profit_per_unit_afn: e.target.value })}
                                        className={`w-full rounded-md border px-3 py-2 font-mono text-sm ${
                                            errors.profit_per_unit_afn ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                        required
                                    />
                            </div>
                            <p className="mt-2 text-xs text-gray-500">This is the investor profit earned on each unit sold.</p>
                        </div>

                        {/* Preview */}
                        {form.funded_quantity && (
                            <div className="rounded-lg border bg-gray-50 p-4">
                                <div className="grid grid-cols-2 gap-y-2 text-sm">
                                    <span className="text-gray-500">Total Cost:</span>
                                    <span className="text-right font-mono font-semibold">{formatPrice(totalCostAfn)} USD</span>
                                    <span className="text-gray-500">Potential Profit:</span>
                                    <span className="text-right font-mono font-semibold text-green-600">{formatPrice(totalProfitAfn)} USD</span>
                                    <span className="col-span-2 my-2 border-t" />
                                    <span className="font-bold">Total Value:</span>
                                    <span className="text-right font-mono font-bold">{formatPrice(totalCostAfn + totalProfitAfn)} USD</span>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex gap-2">
                    <button
                        type="submit"
                        disabled={loading}
                        className="rounded-md bg-black px-6 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                    >
                        {loading ? 'Saving...' : 'Create Allocation'}
                    </button>
                    <BackButton href="/investment-product-allocations" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
