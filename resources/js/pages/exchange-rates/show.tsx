// resources/js/pages/exchange-rates/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Edit, RefreshCw } from 'lucide-react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
}

interface ExchangeRate {
    id: number;
    rate: string;
    effective_at: string;
    from_currency: Currency | null;
    to_currency: Currency | null;
    creator: { id: number; name: string; email: string } | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    exchangeRate: ExchangeRate;
}

export default function ExchangeRatesShow({ exchangeRate }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Exchange Rates', href: '/exchange-rates' },
        { title: 'View', href: `/exchange-rates/${exchangeRate.id}` },
    ];

    const formatDate = (date: string | null) => {
        if (!date) return '—';
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="View Exchange Rate" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-black text-white">
                            <RefreshCw className="h-8 w-8" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Exchange Rate</h1>
                            <p className="text-muted-foreground mt-1 font-mono text-sm">
                                {exchangeRate.from_currency?.code} → {exchangeRate.to_currency?.code}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/exchange-rates/${exchangeRate.id}/edit`}>
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                <Edit className="h-4 w-4" /> Edit
                            </button>
                        </Link>
                        <Link href="/exchange-rates">
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Info */}
                    <div className="rounded-lg border bg-white shadow-sm lg:col-span-2">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Rate Information</h2>
                        </div>
                        <div className="space-y-4 p-6">
                            {/* Visual conversion */}
                            <div className="flex items-center justify-center gap-6 rounded-lg border bg-gray-50 p-6">
                                <div className="text-center">
                                    <p className="font-mono text-3xl font-bold">1</p>
                                    <p className="text-muted-foreground mt-1 text-sm">{exchangeRate.from_currency?.code}</p>
                                    <p className="text-xs text-gray-500">{exchangeRate.from_currency?.name}</p>
                                </div>
                                <ArrowRight className="text-muted-foreground h-8 w-8" />
                                <div className="text-center">
                                    <p className="text-primary font-mono text-3xl font-bold">{exchangeRate.rate}</p>
                                    <p className="text-muted-foreground mt-1 text-sm">{exchangeRate.to_currency?.code}</p>
                                    <p className="text-xs text-gray-500">{exchangeRate.to_currency?.name}</p>
                                </div>
                            </div>

                            <hr className="border-gray-200" />

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500">Effective Date</p>
                                    <p className="font-medium">{formatDate(exchangeRate.effective_at)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Rate Value</p>
                                    <p className="font-mono font-medium">{exchangeRate.rate}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Created By</p>
                                    <p className="font-medium">{exchangeRate.creator?.name || 'System'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Created At</p>
                                    <p className="text-sm">{formatDate(exchangeRate.created_at)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Quick Info */}
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Quick Info</h2>
                        </div>
                        <div className="space-y-4 p-6">
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-sm text-gray-500">From</span>
                                <span className="font-mono font-medium">{exchangeRate.from_currency?.code}</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-sm text-gray-500">To</span>
                                <span className="font-mono font-medium">{exchangeRate.to_currency?.code}</span>
                            </div>
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-sm text-gray-500">Rate</span>
                                <span className="font-mono font-bold">{exchangeRate.rate}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-500">Inverse</span>
                                <span className="font-mono font-medium">{(1 / parseFloat(exchangeRate.rate)).toFixed(6)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
