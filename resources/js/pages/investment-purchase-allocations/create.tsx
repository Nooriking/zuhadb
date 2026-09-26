import { BackButton } from '@/components/form-page';
// resources/js/pages/investment-purchase-allocations/create.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Investment {
    id: number;
    investment_number: string;
    exchange_rate: string;
    capital_amount_afn: string;
    available_amount_afn: string;
    available_amount_currency: string;
    investor: { id: number; full_name: string } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
}

interface Purchase {
    id: number;
    purchase_number: string;
    invoice_number: string | null;
    total_afn: string;
    exchange_rate?: number | null;
    total_usd?: number | null;
    purchase_date: string;
    branch: { id: number; name: string } | null;
    supplier: { id: number; name: string } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
}

interface Props {
    investments: Investment[];
    purchases: Purchase[];
    preInvestmentId?: string | null;
    prePurchaseId?: string | null;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchase Allocations', href: '/investment-purchase-allocations' },
    { title: 'Create', href: '/investment-purchase-allocations/create' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

export default function InvestmentPurchaseAllocationsCreate({
    investments,
    purchases,
    preInvestmentId,
    prePurchaseId,
    errors: initialErrors,
}: Props) {
    const [form, setForm] = useState({
        investment_id: preInvestmentId ? String(preInvestmentId) : investments[0] ? String(investments[0].id) : '',
        purchase_id: prePurchaseId ? String(prePurchaseId) : purchases[0] ? String(purchases[0].id) : '',
        allocated_amount_afn: '',
        notes: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const selectedInvestment = useMemo(() => investments.find((i) => i.id === parseInt(form.investment_id)), [investments, form.investment_id]);

    const selectedPurchase = useMemo(() => purchases.find((p) => p.id === parseInt(form.purchase_id)), [purchases, form.purchase_id]);

    const allocatedAfn = useMemo(() => parseFloat(form.allocated_amount_afn) || 0, [form.allocated_amount_afn]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.post('/investment-purchase-allocations', form, {
            onSuccess: () => setLoading(false),
            onError: (err) => {
                setErrors(err);
                setLoading(false);
            },
        });
    };

    const handleFillAvailable = () => {
        if (!selectedInvestment) return;
        setForm({ ...form, allocated_amount_afn: selectedInvestment.available_amount_afn });
    };

    const purchaseUsd = selectedPurchase?.total_usd ?? null;

    const handleFillPurchaseTotal = () => {
        if (!selectedPurchase || purchaseUsd == null) return;
        setForm({ ...form, allocated_amount_afn: String(purchaseUsd) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Purchase Allocation" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/investment-purchase-allocations" label="Back" />
                    <h1 className="text-2xl font-bold tracking-tight">Create Purchase Allocation</h1>
                </div>

                <div className="bg-card rounded-lg border shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Allocation Details</h2>
                        <p className="text-sm text-gray-500">Allocate capital from an investment to a purchase.</p>
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
                                    Purchase <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.purchase_id}
                                    onChange={(e) => setForm({ ...form, purchase_id: e.target.value })}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${
                                        errors.purchase_id ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                >
                                    <option value="">-- Select Purchase --</option>
                                    {purchases.map((p) => (
                                        <option key={p.id} value={String(p.id)}>
                                            {p.purchase_number} — {p.supplier?.name} ({p.total_usd != null ? `${formatPrice(p.total_usd)} USD` : 'rate missing'})
                                        </option>
                                    ))}
                                </select>
                                {errors.purchase_id && <p className="text-sm text-red-600">{errors.purchase_id}</p>}
                            </div>
                        </div>

                        {/* Info Cards */}
                        {selectedInvestment && (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div className="rounded-lg border bg-blue-50 p-4">
                                    <p className="text-xs text-gray-600">Available in Investment</p>
                                    <p className="text-lg font-bold text-blue-700">{formatPrice(selectedInvestment.available_amount_afn)} USD</p>
                                </div>
                                {selectedPurchase && (
                                    <div className="rounded-lg border bg-orange-50 p-4">
                                        <p className="text-xs text-gray-600">Purchase Total</p>
                                        <p className="text-lg font-bold text-orange-700">
                                            {purchaseUsd != null ? `${formatPrice(purchaseUsd)} USD` : 'Rate missing'}
                                        </p>
                                        <p className="text-xs text-gray-500">{formatPrice(selectedPurchase.total_afn)} AFN bill</p>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Allocated Amount (USD) <span className="text-red-500">*</span>
                            </label>
                            <div className="flex gap-2">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={form.allocated_amount_afn}
                                    onChange={(e) => setForm({ ...form, allocated_amount_afn: e.target.value })}
                                    placeholder="e.g. 2000"
                                    className={`w-full rounded-md border px-3 py-2 font-mono text-sm ${
                                        errors.allocated_amount_afn ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={handleFillAvailable}
                                    className="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium hover:bg-gray-50"
                                >
                                    Fill Available
                                </button>
                                <button
                                    type="button"
                                    onClick={handleFillPurchaseTotal}
                                    className="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium hover:bg-gray-50"
                                >
                                    Fill Purchase Total
                                </button>
                            </div>
                            {errors.allocated_amount_afn && <p className="text-sm text-red-600">{errors.allocated_amount_afn}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea
                                value={form.notes}
                                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                rows={3}
                                placeholder="Optional notes..."
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
                        {loading ? 'Saving...' : 'Create Allocation'}
                    </button>
                    <BackButton href="/investment-purchase-allocations" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
