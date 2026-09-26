// resources/js/pages/investments/edit.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Investment {
    id: number;
    investment_number: string;
    investment_date: string;
    notes: string | null;
    capital_amount_currency: string;
    capital_amount_afn: string;
    investor: { id: number; full_name: string } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
}

interface Props {
    investment: Investment;
    errors?: Record<string, string>;
}

const breadcrumbs = (investment: Investment): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
    { title: investment.investment_number, href: `/investments/${investment.id}` },
    { title: 'Edit', href: `/investments/${investment.id}/edit` },
];

export default function InvestmentsEdit({ investment, errors: initialErrors }: Props) {
    const [form, setForm] = useState({
        investment_date: investment.investment_date,
        notes: investment.notes || '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.put(`/investments/${investment.id}`, form, {
            onSuccess: () => setLoading(false),
            onError: (err) => {
                setErrors(err);
                setLoading(false);
            },
        });
    };

    const formatPrice = (value: string | number) => {
        const num = typeof value === 'string' ? parseFloat(value) : value;
        return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(investment)}>
            <Head title={`Edit ${investment.investment_number}`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Edit Investment</h1>
                        <span className="font-mono text-sm text-gray-500">{investment.investment_number}</span>
                    </div>
                    <Link href={`/investments/${investment.id}`}>
                        <button type="button" className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Back
                        </button>
                    </Link>
                </div>

                {/* Info Box */}
                <div className="rounded-lg border border-dashed bg-gray-50 p-4">
                    <p className="text-sm text-gray-600">
                        <strong>Note:</strong> The capital amount cannot be edited because it has already been allocated to purchases. To add more
                        capital, create a new investment for the same investor.
                    </p>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Investment Details</h2>
                    </div>
                    <div className="space-y-6 p-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <p className="text-xs text-gray-500">Investor</p>
                                <p className="font-medium">{investment.investor?.full_name || '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Capital</p>
                                <p className="font-mono font-medium">
                                    {formatPrice(investment.capital_amount_afn)} USD
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Number</p>
                                <p className="font-mono font-medium">{investment.investment_number}</p>
                            </div>
                        </div>

                        <hr className="border-gray-200" />

                        <div className="grid grid-cols-1 gap-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Investment Date <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={form.investment_date}
                                    onChange={(e) => setForm({ ...form, investment_date: e.target.value })}
                                    className={`w-full max-w-md rounded-md border px-3 py-2 text-sm ${
                                        errors.investment_date ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                />
                                {errors.investment_date && <p className="text-sm text-red-600">{errors.investment_date}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Notes</label>
                                <textarea
                                    value={form.notes}
                                    onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                    rows={4}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex gap-2">
                    <button
                        type="submit"
                        disabled={loading}
                        className="rounded-md bg-black px-6 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                    >
                        {loading ? 'Saving...' : 'Update Investment'}
                    </button>
                    <Link href={`/investments/${investment.id}`}>
                        <button type="button" className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
