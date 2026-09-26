// resources/js/pages/product-price-histories/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface History {
    id: number;
    change_type: 'purchase' | 'sale' | 'both';
    change_type_label: string;
    old_purchase_price_currency: string;
    new_purchase_price_currency: string;
    old_purchase_price_afn: string;
    new_purchase_price_afn: string;
    old_sale_price_currency: string;
    new_sale_price_currency: string;
    old_sale_price_afn: string;
    new_sale_price_afn: string;
    exchange_rate: string;
    reason: string | null;
    created_at: string;
    variant: {
        id: number;
        volume_ml: number | null;
        concentration: string | null;
        product: {
            id: number;
            name: string;
            brand: { id: number; name: string } | null;
        } | null;
    } | null;
    currency: { id: number; code: string; name: string; symbol: string | null } | null;
    changer: { id: number; name: string; email: string } | null;
}

interface Props {
    history: History;
}

const breadcrumbs = (history: History): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Price Histories', href: '/product-price-histories' },
    { title: `#${history.id}`, href: `/product-price-histories/${history.id}` },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const formatDateTime = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const DiffBox = ({ label, oldValue, newValue, currency }: { label: string; oldValue: string; newValue: string; currency: string }) => {
    const oldNum = parseFloat(oldValue);
    const newNum = parseFloat(newValue);
    const diff = newNum - oldNum;

    return (
        <div className="rounded-lg border bg-white p-6 shadow-sm">
            <h3 className="mb-4 font-semibold">{label}</h3>
            <div className="grid grid-cols-3 gap-4 text-center">
                <div>
                    <p className="text-xs text-gray-500">Before</p>
                    <p className="font-mono text-xl">{formatPrice(oldNum)}</p>
                    <p className="text-xs text-gray-500">{currency}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500">Change</p>
                    <p className={`font-mono text-xl font-bold ${diff > 0 ? 'text-red-600' : diff < 0 ? 'text-green-600' : 'text-gray-400'}`}>
                        {diff > 0 ? '+' : ''}
                        {formatPrice(diff)}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-gray-500">After</p>
                    <p className="font-mono text-xl font-bold">{formatPrice(newNum)}</p>
                    <p className="text-xs text-gray-500">{currency}</p>
                </div>
            </div>
        </div>
    );
};

export default function ProductPriceHistoryShow({ history }: Props) {
    const typeColors: Record<string, string> = {
        purchase: 'bg-blue-100 text-blue-800 border-blue-200',
        sale: 'bg-green-100 text-green-800 border-green-200',
        both: 'bg-purple-100 text-purple-800 border-purple-200',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(history)}>
            <Head title={`Price Change #${history.id}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-bold">Price Change #{history.id}</h1>
                            <span
                                className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${typeColors[history.change_type]}`}
                            >
                                {history.change_type_label}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">{formatDateTime(history.created_at)}</p>
                    </div>
                    <Link href="/product-price-histories">
                        <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">Back</button>
                    </Link>
                </div>

                {/* Product Info */}
                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold">Product</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs text-gray-500">Product Name</p>
                            <p className="font-medium">
                                {history.variant?.product?.brand?.name && `${history.variant.product.brand.name} — `}
                                {history.variant?.product?.name}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Variant</p>
                            <p className="font-medium">
                                {history.variant?.volume_ml ? `${history.variant.volume_ml}ml` : '—'}
                                {history.variant?.concentration && ` · ${history.variant.concentration.toUpperCase()}`}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Purchase Price Change */}
                {(history.change_type === 'purchase' || history.change_type === 'both') && (
                    <DiffBox
                        label="Purchase Price Change (AFN)"
                        oldValue={history.old_purchase_price_afn}
                        newValue={history.new_purchase_price_afn}
                        currency="AFN"
                    />
                )}

                {/* Sale Price Change */}
                {(history.change_type === 'sale' || history.change_type === 'both') && (
                    <DiffBox
                        label="Sale Price Change (AFN)"
                        oldValue={history.old_sale_price_afn}
                        newValue={history.new_sale_price_afn}
                        currency="AFN"
                    />
                )}

                {/* Metadata */}
                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold">Metadata</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs text-gray-500">Changed By</p>
                            <p className="font-medium">{history.changer?.name || 'System'}</p>
                            <p className="text-xs text-gray-500">{history.changer?.email}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Prices</p>
                            <p className="font-mono text-sm">Shown in AFN</p>
                        </div>
                    </div>

                    {history.reason && (
                        <>
                            <hr className="my-4 border-gray-200" />
                            <div>
                                <p className="text-xs text-gray-500">Reason</p>
                                <p className="mt-1 text-sm">{history.reason}</p>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
