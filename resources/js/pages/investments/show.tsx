import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface BatchRow {
    id: number;
    batch_code: string | null;
    invoice_number: string | null;
    purchase_date: string | null;
    quantity: number;
    remaining_quantity: number;
    unit_cost_afn: string | number | null;
    total_cost_afn: string | number | null;
    status: string | null;
    variant?: {
        id: number;
        volume_ml?: number | null;
        concentration?: string | null;
        product?: {
            id: number;
            name: string;
            brand?: { id: number; name: string } | null;
        } | null;
    } | null;
    purchase_currency?: { id: number; code: string; symbol: string | null } | null;
}

interface TransactionRow {
    id: number;
    type: string;
    type_label?: string;
    amount_currency: string | number;
    amount_afn: string | number;
    currency_code?: string | null;
    transaction_date: string | null;
    description: string | null;
}

interface Investment {
    id: number;
    amount_currency: string | number;
    amount_afn: string | number;
    exchange_rate: string | number | null;
    investment_date: string;
    status: 'active' | 'closed';
    notes: string | null;
    investor: { id: number; name: string; email: string | null } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
    creator: { id: number; name: string } | null;
    batches: BatchRow[];
    transactions: TransactionRow[];
    created_at: string;
    updated_at: string;
}

interface Props {
    investment: Investment;
    computed: {
        total_profit_afn: number;
        total_withdrawn_afn: number;
        remaining_capital_afn: number;
        batches_cost_afn: number;
        batches_remaining_qty: number;
    };
}

const breadcrumbs = (investment: Investment): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
    { title: `Investment #${investment.id}`, href: `/investments/${investment.id}` },
];

const formatMoney = (value: string | number | null | undefined) => {
    const num = typeof value === 'string' ? parseFloat(value) : Number(value ?? 0);
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number.isFinite(num) ? num : 0);
};

const formatDate = (date: string | null | undefined) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const statusBadge = (status: string) => {
    const map: Record<string, string> = {
        active: 'bg-blue-100 text-blue-800',
        closed: 'bg-gray-100 text-gray-800',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[status] || 'bg-gray-100 text-gray-800'}`}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

export default function InvestmentsShow({ investment, computed }: Props) {
    const [status, setStatus] = useState(investment.status);
    const [notes, setNotes] = useState(investment.notes || '');
    const [saving, setSaving] = useState(false);

    const saveMeta = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        router.put(
            `/investments/${investment.id}`,
            { status, notes },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const handleDelete = () => {
        if (!confirm('Delete this investment? Only allowed when no batches are linked.')) return;
        router.delete(`/investments/${investment.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(investment)}>
            <Head title={`Investment #${investment.id}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-bold">Investment #{investment.id}</h1>
                            {statusBadge(investment.status)}
                        </div>
                        <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                            {investment.investor ? (
                                <Link href={`/investors/${investment.investor.id}`} className="text-blue-600 hover:underline">
                                    {investment.investor.name}
                                </Link>
                            ) : (
                                <span>—</span>
                            )}
                            <span>·</span>
                            <span>{formatDate(investment.investment_date)}</span>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {investment.batches.length === 0 && (
                            <button
                                onClick={handleDelete}
                                className="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                            >
                                Delete
                            </button>
                        )}
                        <Link href="/investments">
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Back
                            </button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Amount</p>
                        <p className="text-xl font-bold">
                            {formatMoney(investment.amount_currency)} {investment.currency?.code || 'AFN'}
                        </p>
                        <p className="text-xs text-gray-500">{formatMoney(investment.amount_afn)} AFN</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Remaining capital</p>
                        <p className="text-xl font-bold text-green-600">{formatMoney(computed.remaining_capital_afn)} AFN</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Profit earned</p>
                        <p className="text-xl font-bold text-purple-600">{formatMoney(computed.total_profit_afn)} AFN</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Withdrawn</p>
                        <p className="text-xl font-bold text-orange-600">{formatMoney(computed.total_withdrawn_afn)} AFN</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Batch stock cost</p>
                        <p className="text-xl font-bold">{formatMoney(computed.batches_cost_afn)} AFN</p>
                        <p className="text-xs text-gray-500">{computed.batches_remaining_qty} units left</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Exchange rate</p>
                        <p className="font-mono font-medium">{investment.exchange_rate ?? '1'}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Created by</p>
                        <p className="font-medium">{investment.creator?.name || 'System'}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Batches</p>
                        <p className="font-medium">{investment.batches.length}</p>
                    </div>
                </div>

                <form onSubmit={saveMeta} className="rounded-lg border bg-white p-4 shadow-sm">
                    <h2 className="mb-3 text-lg font-semibold">Status & notes</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Status</label>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value as 'active' | 'closed')}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                rows={2}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                    <div className="mt-3">
                        <button
                            type="submit"
                            disabled={saving}
                            className="rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                        >
                            {saving ? 'Saving...' : 'Save changes'}
                        </button>
                    </div>
                </form>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Linked batches ({investment.batches.length})</h2>
                        <p className="text-sm text-gray-500">Stock lots funded by this investment.</p>
                    </div>
                    {investment.batches.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">No batches linked yet.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-black text-white">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Batch</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Product</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Qty</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Left</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Unit cost</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {investment.batches.map((batch) => (
                                        <tr key={batch.id} className="border-b">
                                            <td className="px-4 py-3 font-mono text-xs">
                                                <Link href={`/batches/${batch.id}`} className="text-blue-600 hover:underline">
                                                    {batch.batch_code || `#${batch.id}`}
                                                </Link>
                                                <div className="text-gray-500">{formatDate(batch.purchase_date)}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {batch.variant?.product?.brand?.name && (
                                                        <span className="text-gray-500">{batch.variant.product.brand.name} — </span>
                                                    )}
                                                    {batch.variant?.product?.name || '—'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center font-mono">{batch.quantity}</td>
                                            <td className="px-4 py-3 text-center font-mono font-semibold text-green-600">
                                                {batch.remaining_quantity}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono">{formatMoney(batch.unit_cost_afn)}</td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold">
                                                {formatMoney(batch.total_cost_afn)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Ledger transactions ({investment.transactions.length})</h2>
                        <p className="text-sm text-gray-500">Capital, profit, and withdrawals for this investment.</p>
                    </div>
                    {investment.transactions.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">No transactions yet.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-black text-white">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Type</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Amount</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">AFN</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {investment.transactions.map((tx) => (
                                        <tr key={tx.id} className="border-b">
                                            <td className="px-4 py-3 text-xs">{formatDate(tx.transaction_date)}</td>
                                            <td className="px-4 py-3">{tx.type_label || tx.type}</td>
                                            <td className="px-4 py-3 text-right font-mono">
                                                {formatMoney(tx.amount_currency)} {tx.currency_code || investment.currency?.code || ''}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono font-semibold">{formatMoney(tx.amount_afn)}</td>
                                            <td className="px-4 py-3 text-xs text-gray-600">{tx.description || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
