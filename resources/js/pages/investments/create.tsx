import { BackButton } from '@/components/form-page';
// resources/js/pages/investments/create.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface InvestorUser {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
}

interface Props {
    users: InvestorUser[];
    nextNumber: string;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
    { title: 'Create Investment', href: '/investments/create' },
];

export default function InvestmentsCreate({ users, nextNumber, errors: initialErrors }: Props) {
    const [form, setForm] = useState({
        user_id: '',
        capital_amount_afn: '',
        investment_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const capitalAfn = useMemo(() => parseFloat(form.capital_amount_afn) || 0, [form.capital_amount_afn]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        router.post('/investments', form, {
            onSuccess: () => setLoading(false),
            onError: (err) => {
                setErrors(err);
                setLoading(false);
            },
        });
    };

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    const formatPrice = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Investment" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/investments" label="Back" />
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Create Investment</h1>
                        <span className="font-mono text-sm text-gray-500">{nextNumber}</span>
                    </div>
                </div>

                <div className="bg-card rounded-lg border shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Investment Information</h2>
                        <p className="text-sm text-gray-500">
                            Pick the investor user. Cash is optional. If they give goods instead of money, leave the amount empty and link those products when you buy them.
                        </p>
                    </div>
                    <div className="space-y-6 p-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Investor <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.user_id}
                                    onChange={(e) => handleChange('user_id', e.target.value)}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${
                                        errors.user_id ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                >
                                    <option value="">Select investor</option>
                                    {users.map((user) => (
                                        <option key={user.id} value={String(user.id)}>
                                            {user.name}
                                            {user.phone ? ` · ${user.phone}` : ''}
                                            {user.email ? ` · ${user.email}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.user_id && <p className="text-sm text-red-600">{errors.user_id}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Investment Date <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={form.investment_date}
                                    onChange={(e) => handleChange('investment_date', e.target.value)}
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${
                                        errors.investment_date ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                />
                            </div>
                        </div>

                        <div className="rounded-lg border border-dashed p-4">
                            <h3 className="mb-3 text-sm font-semibold">Capital Details</h3>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Capital Amount (USD)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.capital_amount_afn}
                                    onChange={(e) => handleChange('capital_amount_afn', e.target.value)}
                                    placeholder="Leave empty if they give goods instead of cash"
                                    className={`w-full rounded-md border px-3 py-2 font-mono text-sm ${
                                        errors.capital_amount_afn ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                />
                                {errors.capital_amount_afn && <p className="text-sm text-red-600">{errors.capital_amount_afn}</p>}
                            </div>

                            {capitalAfn > 0 && (
                                <div className="mt-4 rounded-lg border bg-blue-50 p-3">
                                    <div className="grid grid-cols-2 gap-2 text-sm">
                                        <span className="text-gray-600">Capital Amount (USD):</span>
                                        <span className="text-right font-mono font-bold text-blue-700">{formatPrice(capitalAfn)} USD</span>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea
                                value={form.notes}
                                onChange={(e) => handleChange('notes', e.target.value)}
                                rows={3}
                                placeholder="Any extra notes..."
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
                        {loading ? 'Saving...' : 'Create Investment'}
                    </button>
                    <BackButton href="/investments" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
