import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Investment {
    id: number;
    amount_currency: string | number;
    amount_afn: string | number;
    investment_date: string;
    status: 'active' | 'closed';
    notes: string | null;
    investor: { id: number; name: string; email: string | null } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
}

interface Props {
    investment: Investment;
    errors?: Record<string, string>;
}

const breadcrumbs = (investment: Investment): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
    { title: `Investment #${investment.id}`, href: `/investments/${investment.id}` },
    { title: 'Edit', href: `/investments/${investment.id}/edit` },
];

const formatMoney = (value: string | number | null | undefined) => {
    const num = typeof value === 'string' ? parseFloat(value) : Number(value ?? 0);
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number.isFinite(num) ? num : 0);
};

export default function InvestmentsEdit({ investment, errors: initialErrors }: Props) {
    const [form, setForm] = useState({
        status: investment.status,
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
            onFinish: () => setLoading(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(investment)}>
            <Head title={`Edit Investment #${investment.id}`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight">Edit Investment #{investment.id}</h1>
                    <Link href={`/investments/${investment.id}`}>
                        <button type="button" className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Back
                        </button>
                    </Link>
                </div>

                <div className="rounded-lg border border-dashed bg-gray-50 p-4 text-sm text-gray-600">
                    Capital amount is fixed after creation. Amount:{' '}
                    <span className="font-mono font-semibold">
                        {formatMoney(investment.amount_currency)} {investment.currency?.code || 'AFN'}
                    </span>{' '}
                    ({formatMoney(investment.amount_afn)} AFN) · Investor: {investment.investor?.name || '—'}
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="space-y-6 p-6">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Status <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={form.status}
                                onChange={(e) => setForm({ ...form, status: e.target.value as 'active' | 'closed' })}
                                className={`w-full max-w-md rounded-md border px-3 py-2 text-sm ${errors.status ? 'border-red-500' : 'border-gray-300'}`}
                                required
                            >
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                            </select>
                            {errors.status && <p className="text-sm text-red-600">{errors.status}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea
                                value={form.notes}
                                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                rows={4}
                                className={`w-full rounded-md border px-3 py-2 text-sm ${errors.notes ? 'border-red-500' : 'border-gray-300'}`}
                            />
                            {errors.notes && <p className="text-sm text-red-600">{errors.notes}</p>}
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
