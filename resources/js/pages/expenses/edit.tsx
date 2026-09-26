// resources/js/pages/expenses/edit.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Expense {
    id: number;
    expense_number: string;
    branch_id: number;
    title: string;
    category: string;
    amount_afn: string;
    expense_date: string;
    payment_method: string;
    description: string | null;
}

interface Props {
    expense: Expense;
    branches: { id: number; name: string; code: string }[];
    categories: Record<string, string>;
    methods: Record<string, string>;
    errors?: Record<string, string>;
}

const breadcrumbs = (e: Expense): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
    { title: e.expense_number, href: `/expenses/${e.id}` },
    { title: 'Edit', href: `/expenses/${e.id}/edit` },
];

export default function ExpensesEdit({ expense, branches, categories, methods, errors: initialErrors }: Props) {
    const [form, setForm] = useState({
        branch_id: String(expense.branch_id),
        title: expense.title,
        category: expense.category,
        amount_afn: expense.amount_afn,
        expense_date: expense.expense_date,
        payment_method: expense.payment_method,
        description: expense.description || '',
    });
    const [errors, setErrors] = useState<Record<string, string>>(initialErrors || {});
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        router.put(`/expenses/${expense.id}`, form, {
            onSuccess: () => setLoading(false),
            onError: (err) => {
                setErrors(err);
                setLoading(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(expense)}>
            <Head title={`Edit ${expense.expense_number}`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold">Edit Expense</h1>
                        <span className="font-mono text-sm text-gray-500">{expense.expense_number}</span>
                    </div>
                    <Link href="/expenses">
                        <button type="button" className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Back
                        </button>
                    </Link>
                </div>

                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <div className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Branch *</label>
                                <select
                                    value={form.branch_id}
                                    onChange={(e) => setForm({ ...form, branch_id: e.target.value })}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                >
                                    {branches.map((b) => (
                                        <option key={b.id} value={String(b.id)}>
                                            {b.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Category *</label>
                                <select
                                    value={form.category}
                                    onChange={(e) => setForm({ ...form, category: e.target.value })}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                >
                                    {Object.entries(categories).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Date *</label>
                                <input
                                    type="date"
                                    value={form.expense_date}
                                    onChange={(e) => setForm({ ...form, expense_date: e.target.value })}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Title *</label>
                            <input
                                type="text"
                                value={form.title}
                                onChange={(e) => setForm({ ...form, title: e.target.value })}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Amount (AFN) *</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={form.amount_afn}
                                onChange={(e) => setForm({ ...form, amount_afn: e.target.value })}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
                                required
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Payment Method</label>
                            <select
                                value={form.payment_method}
                                onChange={(e) => setForm({ ...form, payment_method: e.target.value })}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            >
                                {Object.entries(methods).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Description</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                                rows={3}
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
                        {loading ? 'Saving...' : 'Update Expense'}
                    </button>
                    <Link href="/expenses">
                        <button type="button" className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
