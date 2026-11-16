import { BackButton } from '@/components/form-page';
import { MoneyField } from '@/components/money-field';
import { defaultCurrencyId, type CatalogCurrency } from '@/lib/currencies';
// resources/js/pages/investments/create.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, CircleDollarSign, Package } from 'lucide-react';
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
    defaultUserId?: number | null;
    sourceTypes?: Record<string, string>;
    currencies?: CatalogCurrency[];
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Investments', href: '/investments' },
    { title: 'Create Investment', href: '/investments/create' },
];

export default function InvestmentsCreate({
    users,
    nextNumber,
    defaultUserId = null,
    sourceTypes = { cash: 'Cash', goods: 'Goods' },
    currencies = [],
    errors: initialErrors,
}: Props) {
    const preselected =
        defaultUserId && users.some((u) => u.id === defaultUserId) ? String(defaultUserId) : '';

    const [form, setForm] = useState({
        user_id: preselected,
        source_type: 'cash' as 'cash' | 'goods',
        capital_amount_afn: '',
        currency_id: defaultCurrencyId(currencies),
        investment_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const isCash = form.source_type === 'cash';
    const capitalAfn = useMemo(() => parseFloat(form.capital_amount_afn) || 0, [form.capital_amount_afn]);

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

    const setSourceType = (type: 'cash' | 'goods') => {
        setForm((prev) => ({
            ...prev,
            source_type: type,
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        // ✅ اعتبارسنجی سمت کلاینت
        const clientErrors: Record<string, string> = {};

        if (!form.user_id) {
            clientErrors.user_id = 'Please select an investor.';
        }

        if (capitalAfn <= 0) {
            clientErrors.capital_amount_afn = isCash
                ? 'Capital amount is required for cash investments.'
                : 'Goods contributions require a valued amount.';
        }

        if (!form.currency_id) {
            clientErrors.currency_id = 'Currency is required.';
        }

        if (Object.keys(clientErrors).length > 0) {
            setErrors(clientErrors);
            setLoading(false);
            return;
        }

        const payload = {
            user_id: form.user_id,
            source_type: form.source_type,
            currency_id: form.currency_id,
            capital_amount: form.capital_amount_afn !== '' ? form.capital_amount_afn : '0',
            capital_amount_afn: form.capital_amount_afn !== '' ? form.capital_amount_afn : '0',
            investment_date: form.investment_date,
            notes: form.notes,
        };

        router.post('/investments', payload, {
            onSuccess: () => setLoading(false),
            onError: (err) => {
                setErrors(err);
                setLoading(false);
            },
        });
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
                            Pick the investor user. Cash is optional. If they give goods instead of money, leave the amount empty and link those
                            products when you buy them.
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
                                    className={`w-full rounded-md border px-3 py-2 text-sm ${errors.user_id ? 'border-red-500' : 'border-gray-300'}`}
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
                                <p className="text-muted-foreground text-xs">
                                    Only users with the Investor role appear here. Register them in Users first. The same
                                    person can receive unlimited investments.
                                </p>
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
                                {errors.investment_date && <p className="text-sm text-red-600">{errors.investment_date}</p>}
                            </div>
                        </div>

                        {/* ═══════════ Investment Source Type ═══════════ */}
                        <div className="rounded-lg border border-dashed p-4">
                            <h3 className="mb-3 text-sm font-semibold">Investment Source Type</h3>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={() => setSourceType('cash')}
                                    className={`rounded-xl border-2 p-4 text-left transition ${
                                        isCash ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-emerald-300'
                                    }`}
                                >
                                    <div className="mb-2 flex items-center gap-2 font-semibold text-emerald-700">
                                        <CircleDollarSign className="h-5 w-5" /> Cash
                                    </div>
                                    <p className="text-muted-foreground text-sm">Investor deposits cash. An investment pot will be created.</p>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setSourceType('goods')}
                                    className={`rounded-xl border-2 p-4 text-left transition ${
                                        !isCash ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300'
                                    }`}
                                >
                                    <div className="mb-2 flex items-center gap-2 font-semibold text-blue-700">
                                        <Package className="h-5 w-5" /> Goods
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        Investor gives goods instead of cash. Record them in Add Stock later.
                                    </p>
                                </button>
                            </div>

                            {errors.source_type && <p className="mt-2 text-sm text-red-600">{errors.source_type}</p>}
                        </div>

                        {/* ═══════════ Capital / Goods valuation ═══════════ */}
                        <div className="rounded-lg border border-dashed p-4">
                            <h3 className="mb-3 text-sm font-semibold">
                                {isCash ? 'Capital Details' : 'Goods Valuation'}
                            </h3>
                            <MoneyField
                                label={isCash ? 'Capital amount' : 'Goods value'}
                                required
                                amount={form.capital_amount_afn}
                                currencyId={form.currency_id}
                                currencies={currencies}
                                onAmountChange={(val) => handleChange('capital_amount_afn', val)}
                                onCurrencyChange={(val) => handleChange('currency_id', val)}
                                error={errors.capital_amount_afn || errors.currency_id || errors.capital_amount}
                            />

                            {!isCash && (
                                <p className="text-muted-foreground mt-2 text-sm">
                                    Enter the historical value of this delivery. Cash wallet will not increase. Link received stock to this
                                    investment in Products → Add Stock.
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea
                                value={form.notes}
                                onChange={(e) => handleChange('notes', e.target.value)}
                                rows={3}
                                placeholder="Any extra notes..."
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
