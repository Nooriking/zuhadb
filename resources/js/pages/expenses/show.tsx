// resources/js/pages/expenses/show.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Expense {
    id: number;
    expense_number: string;
    title: string;
    category: string;
    amount_currency: string;
    amount_afn: string;
    exchange_rate: string;
    expense_date: string;
    payment_method: string;
    description: string | null;
    branch: { id: number; name: string; code: string } | null;
    currency: { id: number; code: string; name: string; symbol: string | null } | null;
    creator: { id: number; name: string; email: string } | null;
    created_at: string;
}

interface Props {
    expense: Expense;
}

const formatPrice = (v: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(typeof v === 'string' ? parseFloat(v) : v);

const formatDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '—');

export default function ExpensesShow({ expense }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Expenses', href: '/expenses' },
        { title: expense.expense_number, href: `/expenses/${expense.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Expense ${expense.expense_number}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-mono text-xl font-bold">{expense.expense_number}</h1>
                        <p className="mt-1 text-gray-500">{expense.title}</p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/expenses/${expense.id}/edit`}>
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Edit
                            </button>
                        </Link>
                        <Link href="/expenses">
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Back
                            </button>
                        </Link>
                    </div>
                </div>

                <div className="rounded-lg border-2 border-orange-200 bg-orange-50 p-6 text-center">
                    <p className="text-xs text-gray-600 uppercase">Amount</p>
                    <p className="mt-2 font-mono text-4xl font-bold text-orange-700">
                        {formatPrice(expense.amount_afn)} AFN
                    </p>
                </div>

                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs text-gray-500">Branch</p>
                            <p className="font-medium">{expense.branch?.name}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Category</p>
                            <p className="font-medium capitalize">{expense.category}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Date</p>
                            <p>{formatDate(expense.expense_date)}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Payment Method</p>
                            <p className="capitalize">{expense.payment_method}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Created By</p>
                            <p>{expense.creator?.name || 'System'}</p>
                        </div>
                    </div>
                    {expense.description && (
                        <>
                            <hr className="my-4" />
                            <p className="text-xs text-gray-500">Description</p>
                            <p className="mt-1 text-sm">{expense.description}</p>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
