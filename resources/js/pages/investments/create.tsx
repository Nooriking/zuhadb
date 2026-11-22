import { BackButton } from '@/components/form-page';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface InvestorOption {
    id: number;
    name: string;
    email: string | null;
}

interface CurrencyOption {
    id: number;
    code: string;
    symbol: string | null;
    is_base?: boolean;
}

interface Props {
    investors: InvestorOption[];
    currencies: CurrencyOption[];
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
    { title: 'Create', href: '/investments/create' },
];

export default function InvestmentsCreate({ investors, currencies, errors: initialErrors }: Props) {
    const defaultCurrency =
        currencies.find((c) => c.is_base) ||
        currencies.find((c) => c.code === 'AFN') ||
        currencies[0];

    const [form, setForm] = useState({
        investor_id: '',
        currency_id: defaultCurrency ? String(defaultCurrency.id) : '',
        amount_currency: '',
        investment_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleChange = (field: string, value: string) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }
    };

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
            onFinish: () => setLoading(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Investment" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/investments" label="Back" />
                    <h1 className="text-2xl font-bold tracking-tight">Create Investment</h1>
                </div>

                <div className="bg-card rounded-lg border shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Capital deposit</h2>
                        <p className="text-sm text-gray-500">
                            Records an active investment and credits the investor wallet + ledger transaction.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Investor <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={form.investor_id}
                                onChange={(e) => handleChange('investor_id', e.target.value)}
                                className={`w-full rounded-md border px-3 py-2 text-sm ${errors.investor_id ? 'border-red-500' : 'border-gray-300'}`}
                                required
                            >
                                <option value="">Select investor</option>
                                {investors.map((investor) => (
                                    <option key={investor.id} value={String(investor.id)}>
                                        {investor.name}
                                        {investor.email ? ` · ${investor.email}` : ''}
                                    </option>
                                ))}
                            </select>
                            {errors.investor_id && <p className="text-sm text-red-600">{errors.investor_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Investment date <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                value={form.investment_date}
                                onChange={(e) => handleChange('investment_date', e.target.value)}
                                className={`w-full rounded-md border px-3 py-2 text-sm ${errors.investment_date ? 'border-red-500' : 'border-gray-300'}`}
                                required
                            />
                            {errors.investment_date && <p className="text-sm text-red-600">{errors.investment_date}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Currency <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={form.currency_id}
                                onChange={(e) => handleChange('currency_id', e.target.value)}
                                className={`w-full rounded-md border px-3 py-2 text-sm ${errors.currency_id ? 'border-red-500' : 'border-gray-300'}`}
                                required
                            >
                                {currencies.map((currency) => (
                                    <option key={currency.id} value={String(currency.id)}>
                                        {currency.code}
                                        {currency.symbol ? ` (${currency.symbol})` : ''}
                                    </option>
                                ))}
                            </select>
                            {errors.currency_id && <p className="text-sm text-red-600">{errors.currency_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Amount <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={form.amount_currency}
                                onChange={(e) => handleChange('amount_currency', e.target.value)}
                                className={`w-full rounded-md border px-3 py-2 font-mono text-sm ${errors.amount_currency ? 'border-red-500' : 'border-gray-300'}`}
                                required
                            />
                            {errors.amount_currency && <p className="text-sm text-red-600">{errors.amount_currency}</p>}
                        </div>

                        <div className="space-y-2 md:col-span-2">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea
                                value={form.notes}
                                onChange={(e) => handleChange('notes', e.target.value)}
                                rows={3}
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
                        {loading ? 'Saving...' : 'Create Investment'}
                    </button>
                    <BackButton href="/investments" label="Cancel" />
                </div>
            </form>
        </AppLayout>
    );
}
