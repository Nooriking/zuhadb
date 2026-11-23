import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Expense {
    id: number;
    expense_number: string;
    title: string;
    category: string;
    currency_code: string | null;
    currency_symbol: string | null;
    amount_currency: string | null;
    amount_afn: string;
    exchange_rate: string | null;
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

const formatPrice = (v: string | number, digits = 2) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(typeof v === 'string' ? parseFloat(v) : v);

const formatDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';

export default function ExpensesShow({ expense }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Expenses', href: '/expenses' },
        { title: expense.expense_number, href: `/expenses/${expense.id}` },
    ];

    const code = expense.currency?.code || expense.currency_code || 'AFN';
    const original = expense.amount_currency ?? expense.amount_afn;

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
                    <p className="text-xs text-gray-600 uppercase">Original amount</p>
                    <p className="mt-2 font-mono text-4xl font-bold text-orange-700">
                        {formatPrice(original)} {code}
                    </p>
                    <p className="mt-2 font-mono text-sm text-orange-800">
                        Ledger: {formatPrice(expense.amount_afn)} AFN
                        {expense.exchange_rate && Number(expense.exchange_rate) !== 1
                            ? ` · Rate ${formatPrice(expense.exchange_rate, 4)}`
                            : ''}
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
                            <p className="text-xs text-gray-500">Currency</p>
                            <p className="font-medium">{code}</p>
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
