import { useExchange } from '@/hooks/useExchange';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface InventoryPayload {
    id: number;
    quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    average_cost_afn: number;
    total_cost_afn: number;
    sale_price_afn: number;
    total_sale_afn: number;
    last_movement_at: string | null;
    branch: { id: number; name: string; code: string } | null;
    variant: {
        id: number;
        name: string | null;
        sku: string | null;
        volume_ml: number | null;
        concentration: string | null;
        product: {
            id: number;
            name: string;
            brand: { id: number; name: string } | null;
        } | null;
        prices: Array<{
            id: number;
            price_type: string;
            currency_code: string;
            amount: number;
            currency?: { id: number; code: string; symbol: string | null } | null;
        }>;
    } | null;
}

interface Movement {
    id: number;
    type: string;
    quantity: number;
    before_quantity: number;
    after_quantity: number;
    notes: string | null;
    created_by: string | null;
    created_at: string | null;
    batch: {
        id: number;
        batch_code: string | null;
        purchase_date: string | null;
        invoice_number: string | null;
    } | null;
}

interface BatchRow {
    id: number;
    batch_code: string | null;
    quantity: number;
    remaining_quantity: number;
    unit_cost_afn: number;
    unit_cost_currency: number;
    purchase_currency: string | null;
    exchange_rate: number;
    purchase_date: string | null;
    invoice_number: string | null;
    investment_id: number | null;
    investor: string | null;
    sale_price_afn: number;
    store_profit_per_unit_afn: number;
}

interface Props {
    inventory: InventoryPayload;
    movements: Movement[];
    batches: BatchRow[];
    stats: {
        total_purchased: number;
        total_sold: number;
        total_returned: number;
    };
}

const breadcrumbs = (inventory: InventoryPayload): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/inventory' },
    { title: inventory.variant?.product?.name || 'Item', href: `/inventory/${inventory.id}` },
];

const TYPE_LABELS: Record<string, string> = {
    purchase: 'Purchase',
    sale: 'Sale',
    return: 'Return',
    damage: 'Damage',
    transfer_in: 'Transfer In',
    transfer_out: 'Transfer Out',
    adjustment: 'Adjustment',
    opening: 'Opening Stock',
};

export default function InventoryShow({ inventory, movements, batches, stats }: Props) {
    const { convert } = useExchange();
    const [showAdjustModal, setShowAdjustModal] = useState(false);
    const [adjustForm, setAdjustForm] = useState({
        new_quantity: String(inventory.quantity),
        notes: '',
    });
    const [loading, setLoading] = useState(false);

    const money = (value: number) =>
        new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value || 0);

    const multi = (afn: number) => {
        const usd = convert(afn, 'AFN', 'USD');
        const aed = convert(afn, 'AFN', 'AED');
        return `${money(afn)} AFN · ${usd == null ? '—' : money(usd)} USD · ${aed == null ? '—' : money(aed)} AED`;
    };

    const formatDate = (value: string | null) => {
        if (!value) return '—';
        return new Date(value).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const handleAdjust = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        router.post(`/inventory/${inventory.id}/adjust`, adjustForm, {
            preserveScroll: true,
            onFinish: () => {
                setLoading(false);
                setShowAdjustModal(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(inventory)}>
            <Head title={inventory.variant?.product?.name || 'Inventory'} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            {inventory.variant?.product?.brand?.name && (
                                <span className="text-gray-500">{inventory.variant.product.brand.name} — </span>
                            )}
                            {inventory.variant?.product?.name || inventory.variant?.name || 'Inventory item'}
                        </h1>
                        <p className="mt-1 text-sm text-gray-500">
                            {inventory.branch?.name} ({inventory.branch?.code})
                            {inventory.variant?.sku ? ` · SKU ${inventory.variant.sku}` : ''}
                            {inventory.variant?.volume_ml ? ` · ${inventory.variant.volume_ml}ml` : ''}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={() => setShowAdjustModal(true)}
                            className="rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                        >
                            Adjust stock
                        </button>
                        <Link href="/inventory">
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Back
                            </button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Available</p>
                        <p className="text-2xl font-bold">{inventory.available_quantity}</p>
                        <p className="text-xs text-gray-500">
                            on hand {inventory.quantity} · reserved {inventory.reserved_quantity}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Avg cost / unit</p>
                        <p className="text-lg font-bold">{money(inventory.average_cost_afn)} AFN</p>
                        <p className="text-[11px] text-gray-500">{multi(inventory.average_cost_afn)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Stock value</p>
                        <p className="text-lg font-bold">{money(inventory.total_cost_afn)} AFN</p>
                        <p className="text-[11px] text-gray-500">{multi(inventory.total_cost_afn)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Retail value</p>
                        <p className="text-lg font-bold">{money(inventory.total_sale_afn)} AFN</p>
                        <p className="text-[11px] text-gray-500">{multi(inventory.total_sale_afn)}</p>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Purchased</p>
                        <p className="text-xl font-bold text-green-700">{stats.total_purchased}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Sold</p>
                        <p className="text-xl font-bold text-red-700">{stats.total_sold}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Returned</p>
                        <p className="text-xl font-bold text-blue-700">{stats.total_returned}</p>
                    </div>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Active batches ({batches.length})</h2>
                        <p className="text-sm text-gray-500">FIFO lots for this branch + variant. Purchase date comes from product_batches.</p>
                    </div>
                    {batches.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">No active batches.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-black text-white">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Batch</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Purchase date</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Left</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Unit cost</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {batches.map((batch) => (
                                        <tr key={batch.id} className="border-b">
                                            <td className="px-4 py-3 font-mono text-xs">
                                                <Link href={`/batches/${batch.id}`} className="text-blue-600 hover:underline">
                                                    {batch.batch_code || `#${batch.id}`}
                                                </Link>
                                                {batch.invoice_number && (
                                                    <div className="text-gray-500">Inv {batch.invoice_number}</div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-xs">{batch.purchase_date || '—'}</td>
                                            <td className="px-4 py-3 text-center font-mono font-semibold">
                                                {batch.remaining_quantity}/{batch.quantity}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-xs">
                                                <div>{money(batch.unit_cost_afn)} AFN</div>
                                                {batch.purchase_currency && batch.purchase_currency !== 'AFN' && (
                                                    <div className="text-gray-500">
                                                        {money(batch.unit_cost_currency)} {batch.purchase_currency} @ {batch.exchange_rate || '—'}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                {batch.investor || 'Store'}
                                                {batch.investment_id ? (
                                                    <div className="text-xs text-gray-500">
                                                        <Link href={`/investments/${batch.investment_id}`} className="text-blue-600 hover:underline">
                                                            Investment #{batch.investment_id}
                                                        </Link>
                                                    </div>
                                                ) : null}
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
                        <h2 className="text-lg font-semibold">Movements ({movements.length})</h2>
                    </div>
                    {movements.length === 0 ? (
                        <div className="p-8 text-center text-gray-500">No movements yet.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-black text-white">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">When</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Type</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Qty</th>
                                        <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Before → After</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Batch</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase">By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {movements.map((m) => (
                                        <tr key={m.id} className="border-b">
                                            <td className="px-4 py-3 text-xs">{formatDate(m.created_at)}</td>
                                            <td className="px-4 py-3">{TYPE_LABELS[m.type] || m.type}</td>
                                            <td className="px-4 py-3 text-center font-mono font-semibold">{m.quantity}</td>
                                            <td className="px-4 py-3 text-center font-mono text-xs">
                                                {m.before_quantity} → {m.after_quantity}
                                            </td>
                                            <td className="px-4 py-3 text-xs">
                                                {m.batch ? (
                                                    <>
                                                        <div className="font-mono">{m.batch.batch_code || `#${m.batch.id}`}</div>
                                                        <div className="text-gray-500">{m.batch.purchase_date || '—'}</div>
                                                    </>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-xs">{m.created_by || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {showAdjustModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowAdjustModal(false)}>
                    <div className="w-full max-w-md rounded-lg border bg-white shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <form onSubmit={handleAdjust}>
                            <div className="border-b p-6">
                                <h3 className="text-lg font-semibold">Adjust stock</h3>
                                <p className="mt-1 text-sm text-gray-500">Sets on-hand quantity for this branch + variant.</p>
                            </div>
                            <div className="space-y-4 p-6">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">New quantity</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={adjustForm.new_quantity}
                                        onChange={(e) => setAdjustForm({ ...adjustForm, new_quantity: e.target.value })}
                                        className="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Notes</label>
                                    <textarea
                                        value={adjustForm.notes}
                                        onChange={(e) => setAdjustForm({ ...adjustForm, notes: e.target.value })}
                                        rows={2}
                                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 border-t px-6 py-4">
                                <button
                                    type="button"
                                    onClick={() => setShowAdjustModal(false)}
                                    className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={loading}
                                    className="rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                                >
                                    {loading ? 'Saving...' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
