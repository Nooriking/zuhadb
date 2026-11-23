import { BackButton, FormActions } from '@/components/form-page';
import { MoneyField } from '@/components/money-field';
import AppLayout from '@/layouts/app-layout';
import { defaultCurrencyId, type CatalogCurrency } from '@/lib/currencies';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    branches: { id: number; name: string; code: string }[];
    categories: Record<string, string>;
    methods: Record<string, string>;
    currencies: CatalogCurrency[];
    nextNumber: string;
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
    { title: 'Create', href: '/expenses/create' },
];

export default function ExpensesCreate({
    branches,
    categories,
    methods,
    currencies = [],
    nextNumber,
    errors: initialErrors,
}: Props) {
    const [form, setForm] = useState({
        branch_id: branches[0] ? String(branches[0].id) : '',
        title: '',
        category: 'rent',
        amount: '',
        currency_id: defaultCurrencyId(currencies),
        expense_date: new Date().toISOString().slice(0, 10),
        payment_method: 'cash',
        description: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (loading) return;
        setLoading(true);
        setErrors({});
        router.post(
            '/expenses',
            {
                ...form,
                amount: parseFloat(form.amount) || 0,
                currency_id: form.currency_id,
            },
            {
                onSuccess: () => setLoading(false),
                onError: (err) => {
                    setErrors(err);
                    setLoading(false);
                },
                onFinish: () => setLoading(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Expense" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackButton href="/expenses" label="Back" />
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Create Expense</h1>
                        <span className="font-mono text-sm text-gray-500">{nextNumber}</span>
                    </div>
                </div>

                <div className="bg-card rounded-lg border shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Expense</h2>
                        <p className="text-muted-foreground text-sm">Record the original currency; AFN ledger amount is calculated at save time.</p>
                    </div>
                    <div className="space-y-6 p-6">
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold">What was spent</h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2 md:col-span-2">
                                    <label className="text-sm font-medium">Title *</label>
                                    <input
                                        type="text"
                                        value={form.title}
                                        onChange={(e) => setForm({ ...form, title: e.target.value })}
                                        placeholder="e.g. Monthly rent"
                                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.title && <p className="text-destructive text-xs">{errors.title}</p>}
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Category *</label>
                                    <select
                                        value={form.category}
                                        onChange={(e) => setForm({ ...form, category: e.target.value })}
                                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                    >
                                        {Object.entries(categories).map(([k, v]) => (
                                            <option key={k} value={k}>
                                                {v}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <label className="text-sm font-medium">Description</label>
                                    <textarea
                                        value={form.description}
                                        onChange={(e) => setForm({ ...form, description: e.target.value })}
                                        rows={3}
                                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                        <div className="space-y-4 border-t pt-6">
                            <h3 className="text-sm font-semibold">Payment</h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                {branches.length > 1 && (
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Branch *</label>
                                        <select
                                            value={form.branch_id}
                                            onChange={(e) => setForm({ ...form, branch_id: e.target.value })}
                                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                        >
                                            {branches.map((b) => (
                                                <option key={b.id} value={String(b.id)}>
                                                    {b.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Date *</label>
                                    <input
                                        type="date"
                                        value={form.expense_date}
                                        onChange={(e) => setForm({ ...form, expense_date: e.target.value })}
                                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <MoneyField
                                        label="Amount"
                                        required
                                        amount={form.amount}
                                        currencyId={form.currency_id}
                                        currencies={currencies}
                                        onAmountChange={(val) => setForm({ ...form, amount: val })}
                                        onCurrencyChange={(val) => setForm({ ...form, currency_id: val })}
                                        error={errors.amount || errors.currency_id}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Payment method</label>
                                    <select
                                        value={form.payment_method}
                                        onChange={(e) => setForm({ ...form, payment_method: e.target.value })}
                                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                    >
                                        {Object.entries(methods).map(([k, v]) => (
                                            <option key={k} value={k}>
                                                {v}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <FormActions backHref="/expenses" submitLabel="Save expense" loading={loading} />
            </form>
        </AppLayout>
    );
}
