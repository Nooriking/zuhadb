// resources/js/pages/expenses/index.tsx

import { FilterField, IndexFilters, filterControlClass } from '@/components/index-filters';
import { DeleteAction, EditAction, RowActions, ViewAction } from '@/components/row-actions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarDays, TrendingDown, Wallet } from 'lucide-react';
import { useState } from 'react';

interface Expense {
    id: number;
    expense_number: string;
    title: string;
    category: string;
    amount_currency: string;
    amount_afn: string;
    expense_date: string;
    payment_method: string;
    branch: { id: number; name: string; code: string } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
    creator: { id: number; name: string } | null;
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
    branches: { id: number; name: string; code: string }[];
    categories: Record<string, string>;
    filters: any;
    stats: {
        total: number;
        this_month: string;
        total_afn: string;
        today_afn: string;
        today_count: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
];

const formatPrice = (v: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(typeof v === 'string' ? parseFloat(v) : v);

export default function ExpensesIndex({ expenses, branches, categories, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id || 'all');
    const [categoryFilter, setCategoryFilter] = useState(filters.category || 'all');

    const handleSearch = () => {
        router.get(
            '/expenses',
            {
                search: search || undefined,
                branch_id: branchFilter === 'all' ? undefined : branchFilter,
                category: categoryFilter === 'all' ? undefined : categoryFilter,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const confirmDelete = (e: Expense) => {
        if (!confirm(`Move "${e.expense_number}" to trash?`)) return;
        router.delete(`/expenses/${e.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expenses" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Expenses</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{expenses.total} Expenses</span>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/expenses/trash">
                            <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                                Trash
                            </button>
                        </Link>
                        <Link href="/expenses/create">
                            <button className="rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">+ New Expense</button>
                        </Link>
                    </div>
                </div>

                {/* ═══════════ 📊 Stats Cards ═══════════ */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Total */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium text-gray-500 uppercase">Total</p>
                            <Activity className="h-4 w-4 text-gray-400" />
                        </div>
                        <p className="mt-2 text-2xl font-bold">{stats.total}</p>
                        <p className="mt-1 text-xs text-gray-400">All expenses</p>
                    </div>

                    {/* ✅ Today — کارت جدید */}
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium text-blue-700 uppercase">Today (AFN)</p>
                            <CalendarDays className="h-4 w-4 text-blue-600" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-blue-700">{formatPrice(stats.today_afn)}</p>
                        <p className="mt-1 text-xs text-blue-600">
                            {stats.today_count} {stats.today_count === 1 ? 'expense' : 'expenses'} today
                        </p>
                    </div>

                    {/* This Month */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium text-gray-500 uppercase">This Month (AFN)</p>
                            <TrendingDown className="h-4 w-4 text-orange-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-orange-600">{formatPrice(stats.this_month)}</p>
                        <p className="mt-1 text-xs text-gray-400">Current month</p>
                    </div>

                    {/* Total AFN */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium text-gray-500 uppercase">Total (AFN)</p>
                            <Wallet className="h-4 w-4 text-red-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-red-600">{formatPrice(stats.total_afn)}</p>
                        <p className="mt-1 text-xs text-gray-400">Grand total</p>
                    </div>
                </div>

                <IndexFilters search={search} onSearchChange={setSearch} onSearch={handleSearch} placeholder="Search title...">
                    {branches.length > 1 && (
                        <FilterField label="Branch">
                            <select
                                value={branchFilter}
                                onChange={(e) => {
                                    setBranchFilter(e.target.value);
                                    setTimeout(handleSearch, 0);
                                }}
                                className={filterControlClass}
                            >
                                <option value="all">All Branches</option>
                                {branches.map((b) => (
                                    <option key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </option>
                                ))}
                            </select>
                        </FilterField>
                    )}
                    <FilterField label="Category">
                        <select
                            value={categoryFilter}
                            onChange={(e) => {
                                setCategoryFilter(e.target.value);
                                setTimeout(handleSearch, 0);
                            }}
                            className={filterControlClass}
                        >
                            <option value="all">All Categories</option>
                            {Object.entries(categories).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </FilterField>
                </IndexFilters>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Expense #</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Title</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Category</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Branch</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Amount</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Method</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {expenses.data.map((expense, i) => (
                                <tr key={expense.id} className="border-b hover:bg-gray-50">
                                    <td className="px-4 py-3">{expenses.from ? expenses.from + i : i + 1}</td>
                                    <td className="px-4 py-3 font-mono text-xs">{expense.expense_number}</td>
                                    <td className="px-4 py-3 font-medium">{expense.title}</td>
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">
                                            {categories[expense.category] || expense.category}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="rounded-full border px-2 py-0.5 text-xs">{expense.branch?.code}</span>
                                    </td>
                                    <td className="px-4 py-3 text-xs">{new Date(expense.expense_date).toLocaleDateString('en-US')}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="font-mono font-semibold">{formatPrice(expense.amount_afn)} AFN</div>
                                    </td>
                                    <td className="px-4 py-3 text-xs capitalize">{expense.payment_method}</td>
                                    <td className="px-4 py-3">
                                        <RowActions>
                                            <ViewAction href={`/expenses/${expense.id}`} />
                                            <EditAction href={`/expenses/${expense.id}/edit`} />
                                            <DeleteAction onClick={() => confirmDelete(expense)} />
                                        </RowActions>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
