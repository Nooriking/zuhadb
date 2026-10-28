import AppLayout from '@/layouts/app-layout';
import { formatRate } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit, Star } from 'lucide-react';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    decimal_places: number;
    rate_to_default: string | number;
    is_base: boolean;
    status: 'active' | 'inactive';
    creator: { id: number; name: string; email: string } | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    currency: Currency;
    rateDefinition: string;
}

export default function CurrenciesShow({ currency, rateDefinition }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Currencies', href: '/currencies' },
        { title: currency.code, href: `/currencies/${currency.id}` },
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

    const statusColors = {
        active: 'bg-green-100 text-green-800 border-green-200',
        inactive: 'bg-gray-100 text-gray-800 border-gray-200',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`View ${currency.code}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-black text-2xl font-bold text-white">
                            {currency.symbol || currency.code.substring(0, 2)}
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="font-mono text-2xl font-bold tracking-tight">{currency.code}</h1>
                                <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusColors[currency.status]}`}>
                                    {currency.status.charAt(0).toUpperCase() + currency.status.slice(1)}
                                </span>
                                {currency.is_base && (
                                    <span className="inline-flex items-center gap-1 rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">
                                        <Star className="h-3 w-3" />
                                        Default currency
                                    </span>
                                )}
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">{currency.name}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/currencies/${currency.id}/edit`}>
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                <Edit className="h-4 w-4" /> Edit
                            </button>
                        </Link>
                        <Link href="/currencies">
                            <button className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                <ArrowLeft className="h-4 w-4" /> Back
                            </button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="rounded-lg border bg-white shadow-sm lg:col-span-2">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Currency information</h2>
                        </div>
                        <div className="space-y-4 p-6">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500">Code</p>
                                    <p className="font-mono text-lg font-semibold">{currency.code}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Name</p>
                                    <p className="text-lg font-medium">{currency.name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Symbol</p>
                                    <p className="text-2xl">{currency.symbol || '—'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Decimal places</p>
                                    <p className="font-medium">{currency.decimal_places}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Rate to default</p>
                                    <p className="font-mono font-medium">{formatRate(currency.rate_to_default)}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">{rateDefinition}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Default currency</p>
                                    <p className="font-medium">{currency.is_base ? 'Yes' : 'No'}</p>
                                </div>
                            </div>

                            <hr className="border-gray-200" />

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500">Created by</p>
                                    <p className="font-medium">{currency.creator?.name || 'System'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Created at</p>
                                    <p className="text-sm">{formatDate(currency.created_at)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Last updated</p>
                                    <p className="text-sm">{formatDate(currency.updated_at)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-6 py-4">
                            <h2 className="text-lg font-semibold">Quick info</h2>
                        </div>
                        <div className="space-y-4 p-6">
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-sm text-gray-500">Status</span>
                                <span className="font-medium">{currency.status}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-gray-500">Is default</span>
                                <span className="font-medium">{currency.is_base ? 'Yes' : 'No'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
