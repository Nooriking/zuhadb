// resources/js/pages/investment-product-allocations/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Allocation {
    id: number;
    funded_quantity: number;
    sold_quantity: number;
    remaining_quantity: number;
    cost_per_unit_currency: string;
    cost_per_unit_afn: string;
    profit_per_unit_currency: string;
    profit_per_unit_afn: string;
    capital_recovered_afn: string;
    profit_earned_afn: string;
    status: 'active' | 'sold_out' | 'cancelled';
    investment: {
        id: number;
        investment_number: string;
        investor: { id: number; full_name: string; phone: string | null } | null;
        currency: { id: number; code: string; symbol: string | null } | null;
    } | null;
    purchase_allocation: {
        id: number;
        purchase: { id: number; purchase_number: string; total_afn: string } | null;
    } | null;
    batch: {
        id: number;
        batch_code: string;
        quantity: number;
        remaining_quantity: number;
        branch: { id: number; name: string } | null;
    } | null;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: { id: number; name: string; brand: { id: number; name: string } | null } | null;
    } | null;
    created_at: string;
}

interface Props {
    allocation: Allocation;
}

const breadcrumbs = (allocation: Allocation): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Product Allocations', href: '/investment-product-allocations' },
    { title: `Allocation #${allocation.id}`, href: `/investment-product-allocations/${allocation.id}` },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

export default function InvestmentProductAllocationShow({ allocation }: Props) {
    const soldPercent = allocation.funded_quantity > 0 ? Math.round((allocation.sold_quantity / allocation.funded_quantity) * 100) : 0;

    const totalCostAfn = parseFloat(allocation.cost_per_unit_afn) * allocation.funded_quantity;
    const potentialProfitAfn = parseFloat(allocation.profit_per_unit_afn) * allocation.funded_quantity;

    return (
        <AppLayout breadcrumbs={breadcrumbs(allocation)}>
            <Head title={`Allocation #${allocation.id}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Product Allocation</h1>
                        <span
                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                allocation.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                            }`}
                        >
                            {allocation.status.replace('_', ' ')}
                        </span>
                    </div>
                    <Link href="/investment-product-allocations">
                        <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">Back</button>
                    </Link>
                </div>

                {/* Summary */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Investment</p>
                        {allocation.investment ? (
                            <Link
                                href={`/investments/${allocation.investment.id}`}
                                className="font-mono text-sm font-semibold text-blue-600 hover:underline"
                            >
                                {allocation.investment.investment_number}
                            </Link>
                        ) : (
                            '—'
                        )}
                        <p className="mt-1 text-xs text-gray-500">Investor: {allocation.investment?.investor?.full_name}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Batch</p>
                        {allocation.batch ? (
                            <Link href={`/batches/${allocation.batch.id}`} className="font-mono text-sm font-semibold text-blue-600 hover:underline">
                                {allocation.batch.batch_code}
                            </Link>
                        ) : (
                            '—'
                        )}
                        <p className="mt-1 text-xs text-gray-500">Branch: {allocation.batch?.branch?.name}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Product</p>
                        <p className="text-sm font-semibold">
                            {allocation.variant?.product?.brand?.name && (
                                <span className="text-gray-500">{allocation.variant.product.brand.name} — </span>
                            )}
                            {allocation.variant?.product?.name}
                        </p>
                        <p className="mt-1 text-xs text-gray-500">
                            {allocation.variant?.volume_ml ? `${allocation.variant.volume_ml}ml` : ''}
                            {allocation.variant?.concentration ? ` · ${allocation.variant.concentration.toUpperCase()}` : ''}
                        </p>
                    </div>
                </div>

                {/* Stock Progress */}
                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold">Stock Status</h2>
                    <div className="grid grid-cols-3 gap-6 text-center">
                        <div>
                            <p className="text-xs text-gray-500">Funded</p>
                            <p className="font-mono text-3xl font-bold">{allocation.funded_quantity}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Sold</p>
                            <p className="font-mono text-3xl font-bold text-red-600">{allocation.sold_quantity}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Remaining</p>
                            <p className="font-mono text-3xl font-bold text-green-600">{allocation.remaining_quantity}</p>
                        </div>
                    </div>
                    <div className="mt-6">
                        <div className="mb-2 flex items-center justify-between text-sm">
                            <span className="text-gray-500">Progress</span>
                            <span className="font-semibold">{soldPercent}% sold</span>
                        </div>
                        <div className="h-3 w-full overflow-hidden rounded-full bg-gray-200">
                            <div className="h-full rounded-full bg-green-500" style={{ width: `${soldPercent}%` }} />
                        </div>
                    </div>
                </div>

                {/* Financial Summary */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <h3 className="mb-4 font-semibold">Cost & Profit per Unit</h3>
                        <div className="grid grid-cols-2 gap-y-2 text-sm">
                            <span className="text-gray-500">Cost per Unit (currency):</span>
                            <span className="text-right font-mono">
                                {formatPrice(allocation.cost_per_unit_currency)} {allocation.investment?.currency?.code}
                            </span>
                            <span className="text-gray-500">Cost per Unit (USD):</span>
                            <span className="text-right font-mono">{formatPrice(allocation.cost_per_unit_afn)}</span>
                            <span className="col-span-2 my-2 border-t" />
                            <span className="text-gray-500">Profit per Unit (currency):</span>
                            <span className="text-right font-mono text-green-600">
                                {formatPrice(allocation.profit_per_unit_currency)} {allocation.investment?.currency?.code}
                            </span>
                            <span className="text-gray-500">Profit per Unit (USD):</span>
                            <span className="text-right font-mono font-semibold text-green-700">{formatPrice(allocation.profit_per_unit_afn)}</span>
                        </div>
                    </div>

                    <div className="rounded-lg border bg-white p-6 shadow-sm">
                        <h3 className="mb-4 font-semibold">Totals</h3>
                        <div className="grid grid-cols-2 gap-y-2 text-sm">
                            <span className="text-gray-500">Total Cost:</span>
                            <span className="text-right font-mono font-semibold">{formatPrice(totalCostAfn)} USD</span>
                            <span className="text-gray-500">Potential Profit (all sold):</span>
                            <span className="text-right font-mono font-semibold text-green-600">{formatPrice(potentialProfitAfn)} USD</span>
                            <span className="col-span-2 my-2 border-t" />
                            <span className="text-gray-500">Capital Recovered:</span>
                            <span className="text-right font-mono">{formatPrice(allocation.capital_recovered_afn)} USD</span>
                            <span className="font-bold text-gray-500">Profit Earned:</span>
                            <span className="text-right font-mono font-bold text-purple-600">{formatPrice(allocation.profit_earned_afn)} USD</span>
                        </div>
                    </div>
                </div>

                <div className="rounded-lg border bg-white p-4">
                    <p className="text-xs text-gray-500">Created At</p>
                    <p className="text-sm">{formatDate(allocation.created_at)}</p>
                </div>
            </div>
        </AppLayout>
    );
}
