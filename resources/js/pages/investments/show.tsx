// resources/js/pages/investments/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface PurchaseAllocation {
    id: number;
    allocated_amount_currency: string;
    allocated_amount_afn: string;
    purchase: {
        id: number;
        purchase_number: string;
        invoice_number: string | null;
        total_afn: string;
        purchase_date: string;
    } | null;
}

interface ProductAllocation {
    id: number;
    funded_quantity: number;
    sold_quantity: number;
    remaining_quantity: number;
    cost_per_unit_afn: string;
    profit_per_unit_afn: string;
    profit_earned_afn: string;
    batch: { id: number; batch_code: string; quantity: number; remaining_quantity: number } | null;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    } | null;
}

interface ProfitEntry {
    id: number;
    quantity: number;
    profit_amount_afn: string;
    status: 'pending' | 'paid';
    earned_at: string;
}

interface Investment {
    id: number;
    investment_number: string;
    exchange_rate: string;
    capital_amount_currency: string;
    capital_amount_afn: string;
    available_amount_currency: string;
    available_amount_afn: string;
    allocated_amount_afn: string;
    profit_amount_currency: string;
    profit_amount_afn: string;
    withdrawn_amount_afn: string;
    investment_date: string;
    status: 'active' | 'partially_used' | 'fully_used' | 'withdrawn' | 'closed';
    notes: string | null;
    investor: { id: number; full_name: string; phone: string | null; email: string | null } | null;
    currency: { id: number; code: string; name: string; symbol: string | null } | null;
    creator: { id: number; name: string; email: string } | null;
    parent: { id: number; investment_number: string } | null;
    renewals: { id: number; investment_number: string; capital_amount_afn: string; status: string }[];
    purchase_allocations: PurchaseAllocation[];
    product_allocations: ProductAllocation[];
    profit_entries: ProfitEntry[];
    created_at: string;
    updated_at: string;
}

interface Props {
    investment: Investment;
    stats: {
        total_purchases: number;
        total_batches: number;
        total_profit: string;
        profit_paid: string;
        profit_pending: string;
    };
}

const breadcrumbs = (investment: Investment): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
    { title: investment.investment_number, href: `/investments/${investment.id}` },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

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
        active: 'bg-blue-100 text-blue-800',
        partially_used: 'bg-yellow-100 text-yellow-800',
        fully_used: 'bg-purple-100 text-purple-800',
        withdrawn: 'bg-orange-100 text-orange-800',
        closed: 'bg-gray-100 text-gray-800',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[status] || 'bg-gray-100 text-gray-800'}`}>
            {status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
        </span>
    );
};

export default function InvestmentsShow({ investment, stats }: Props) {
    const [showWithdrawModal, setShowWithdrawModal] = useState(false);
    const [withdrawForm, setWithdrawForm] = useState({
        amount_afn: '',
        notes: '',
    });
    const [loading, setLoading] = useState(false);

    const handleWithdraw = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);

        router.post(`/investments/${investment.id}/withdraw`, withdrawForm, {
            preserveScroll: true,
            onSuccess: () => {
                setShowWithdrawModal(false);
                setWithdrawForm({ amount_afn: '', notes: '' });
                setLoading(false);
            },
            onError: () => setLoading(false),
        });
    };

    const handleClose = () => {
        if (!confirm('Close this investment? It must have no available capital and no unsold products.')) return;
        router.patch(`/investments/${investment.id}/close`, {}, { preserveScroll: true });
    };

    const progress =
        parseFloat(investment.capital_amount_afn) > 0
            ? Math.round((parseFloat(investment.allocated_amount_afn) / parseFloat(investment.capital_amount_afn)) * 100)
            : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs(investment)}>
            <Head title={`Investment ${investment.investment_number}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="font-mono text-xl font-bold">{investment.investment_number}</h1>
                            {getStatusBadge(investment.status)}
                        </div>
                        <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                            <Link href={`/investors/${investment.investor?.id}`} className="text-blue-600 hover:underline">
                                {investment.investor?.full_name}
                            </Link>
                            <span>·</span>
                            <span>{formatDate(investment.investment_date)}</span>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {parseFloat(investment.available_amount_afn) > 0 && (
                            <button
                                onClick={() => setShowWithdrawModal(true)}
                                className="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700"
                            >
                                Withdraw Capital
                            </button>
                        )}
                        {investment.status !== 'closed' && (
                            <button
                                onClick={handleClose}
                                className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50"
                            >
                                Close Investment
                            </button>
                        )}
                        <Link href={`/investments/${investment.id}/edit`}>
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Edit
                            </button>
                        </Link>
                        <Link href="/investments">
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Back
                            </button>
                        </Link>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Capital</p>
                        <p className="text-xl font-bold">
                            {formatPrice(investment.capital_amount_afn)} USD
                        </p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Available</p>
                        <p className="text-xl font-bold text-green-600">{formatPrice(investment.available_amount_afn)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Allocated</p>
                        <p className="text-xl font-bold text-orange-600">{formatPrice(investment.allocated_amount_afn)} USD</p>
                        <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                            <div className="h-full rounded-full bg-black" style={{ width: `${progress}%` }} />
                        </div>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Total Profit</p>
                        <p className="text-xl font-bold text-purple-600">{formatPrice(investment.profit_amount_afn)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Withdrawn</p>
                        <p className="text-xl font-bold text-gray-600">{formatPrice(investment.withdrawn_amount_afn)} USD</p>
                    </div>
                </div>

                {/* Info Boxes */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Created By</p>
                        <p className="font-medium">{investment.creator?.name || 'System'}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Total Batches</p>
                        <p className="font-medium">{stats.total_batches}</p>
                    </div>
                </div>

                {investment.notes && (
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Notes</p>
                        <p className="mt-1 text-sm">{investment.notes}</p>
                    </div>
                )}

                {/* Purchase Allocations */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Purchase Allocations ({investment.purchase_allocations.length})</h2>
                        <p className="text-sm text-gray-500">Purchases funded by this investment.</p>
                    </div>
                    {investment.purchase_allocations.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">
                            No purchases allocated yet. Available capital: {formatPrice(investment.available_amount_afn)} USD.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-black text-white">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Purchase</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Purchase Total</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Allocated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {investment.purchase_allocations.map((a) => (
                                        <tr key={a.id} className="border-b">
                                            <td className="px-4 py-3">
                                                {a.purchase ? (
                                                    <Link
                                                        href={`/purchases/${a.purchase.id}`}
                                                        className="font-mono text-xs text-blue-600 hover:underline"
                                                    >
                                                        {a.purchase.purchase_number}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-xs">{a.purchase ? formatDate(a.purchase.purchase_date) : '—'}</td>
                                            <td className="px-4 py-3 text-right font-mono">
                                                {a.purchase ? formatPrice(a.purchase.total_afn) + ' AFN' : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold text-orange-600">
                                                {formatPrice(a.allocated_amount_afn)} USD
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Product Allocations */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Product Allocations ({investment.product_allocations.length})</h2>
                        <p className="text-sm text-gray-500">Batches funded by this investment.</p>
                    </div>
                    {investment.product_allocations.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">No products allocated yet.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-black text-white">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Batch</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Product</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Funded</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Sold</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Remaining</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Cost/Unit</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Profit/Unit</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Profit Earned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {investment.product_allocations.map((a) => (
                                        <tr key={a.id} className="border-b">
                                            <td className="px-4 py-3 font-mono text-xs">
                                                {a.batch ? (
                                                    <Link href={`/batches/${a.batch.id}`} className="text-blue-600 hover:underline">
                                                        {a.batch.batch_code}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {a.variant?.product?.brand?.name && (
                                                        <span className="text-gray-500">{a.variant.product.brand.name} — </span>
                                                    )}
                                                    {a.variant?.product?.name}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    {a.variant?.volume_ml ? `${a.variant.volume_ml}ml` : ''}
                                                    {a.variant?.concentration ? ` · ${a.variant.concentration.toUpperCase()}` : ''}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center font-mono">{a.funded_quantity}</td>
                                            <td className="px-4 py-3 text-center font-mono text-red-600">{a.sold_quantity}</td>
                                            <td className="px-4 py-3 text-center font-mono font-bold text-green-600">{a.remaining_quantity}</td>
                                            <td className="px-4 py-3 text-right font-mono text-xs">{formatPrice(a.cost_per_unit_afn)}</td>
                                            <td className="px-4 py-3 text-right font-mono text-xs">{formatPrice(a.profit_per_unit_afn)}</td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold text-purple-600">
                                                {formatPrice(a.profit_earned_afn)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Profit Entries */}
                {investment.profit_entries && investment.profit_entries.length > 0 && (
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Profit History (Last 20)</h2>
                            <div className="mt-1 flex gap-4 text-xs">
                                <span className="text-green-600">Paid: {formatPrice(stats.profit_paid)} USD</span>
                                <span className="text-yellow-600">Pending: {formatPrice(stats.profit_pending)} USD</span>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-black text-white">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Qty</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Profit</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {investment.profit_entries.map((p) => (
                                        <tr key={p.id} className="border-b">
                                            <td className="px-4 py-3 text-xs">{formatDate(p.earned_at)}</td>
                                            <td className="px-4 py-3 text-center font-mono">{p.quantity}</td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold text-purple-600">
                                                {formatPrice(p.profit_amount_afn)} USD
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                        p.status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                                    }`}
                                                >
                                                    {p.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {/* Withdraw Modal */}
            {showWithdrawModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowWithdrawModal(false)}>
                    <div className="w-full max-w-md rounded-lg border bg-white shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <form onSubmit={handleWithdraw}>
                            <div className="border-b p-6">
                                <h3 className="text-lg font-semibold">Withdraw Capital</h3>
                                <p className="mt-1 text-sm text-gray-500">Available: {formatPrice(investment.available_amount_afn)} USD</p>
                            </div>
                            <div className="space-y-4 p-6">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Amount (USD) *</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max={investment.available_amount_afn}
                                        value={withdrawForm.amount_afn}
                                        onChange={(e) => setWithdrawForm({ ...withdrawForm, amount_afn: e.target.value })}
                                        className="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Notes</label>
                                    <textarea
                                        value={withdrawForm.notes}
                                        onChange={(e) => setWithdrawForm({ ...withdrawForm, notes: e.target.value })}
                                        rows={2}
                                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 border-t px-6 py-4">
                                <button
                                    type="button"
                                    onClick={() => setShowWithdrawModal(false)}
                                    className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={loading}
                                    className="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-50"
                                >
                                    {loading ? 'Processing...' : 'Withdraw'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
