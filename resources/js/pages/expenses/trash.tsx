// resources/js/pages/expenses/trash.tsx

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Expense {
    id: number;
    expense_number: string;
    title: string;
    amount_afn: string;
    expense_date: string;
    branch: { id: number; name: string } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
}

interface Props {
    expenses: {
        data: Expense[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
    { title: 'Trash', href: '/expenses/trash' },
];

const formatPrice = (v: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(typeof v === 'string' ? parseFloat(v) : v);

export default function ExpensesTrash({ expenses }: Props) {
    const handleRestore = (e: Expense) => {
        if (!confirm(`Restore "${e.expense_number}"?`)) return;
        router.post(`/expenses/${e.id}/restore`, {}, { preserveScroll: true });
    };

    const handleForceDelete = (e: Expense) => {
        if (!confirm(`Permanently delete "${e.expense_number}"?`)) return;
        router.delete(`/expenses/${e.id}/force-delete`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Expenses" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Trashed Expenses</h1>
                    <Link href="/expenses">
                        <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">Back</button>
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Expense #</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Title</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Amount</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {expenses.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="h-24 px-4 py-6 text-center text-gray-500">
                                        Trash is empty.
                                    </td>
                                </tr>
                            ) : (
                                expenses.data.map((e, i) => (
                                    <tr key={e.id} className="border-b">
                                        <td className="px-4 py-3">{expenses.from ? expenses.from + i : i + 1}</td>
                                        <td className="px-4 py-3 font-mono text-xs">{e.expense_number}</td>
                                        <td className="px-4 py-3">{e.title}</td>
                                        <td className="px-4 py-3 text-right font-mono">{formatPrice(e.amount_afn)} AFN</td>
                                        <td className="px-4 py-3 text-xs">{new Date(e.expense_date).toLocaleDateString('en-US')}</td>
                                        <td className="px-4 py-3 text-center">
                                            <div className="flex justify-center gap-3">
                                                <button
                                                    onClick={() => handleRestore(e)}
                                                    className="text-xs font-semibold text-green-600 hover:text-green-800"
                                                >
                                                    Restore
                                                </button>
                                                <button
                                                    onClick={() => handleForceDelete(e)}
                                                    className="text-xs font-semibold text-red-600 hover:text-red-800"
                                                >
                                                    Delete Forever
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
