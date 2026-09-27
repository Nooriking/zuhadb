// resources/js/pages/investments/trash.tsx

import AppLayout from '@/layouts/app-layout';
import { forceDeleteItem, restoreItem } from '@/lib/trash-actions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Investment {
    id: number;
    investment_number: string;
    capital_amount_afn: string;
    investment_date: string;
    status: string;
    investor: { id: number; full_name: string } | null;
    currency: { id: number; code: string; symbol: string | null } | null;
    creator: { id: number; name: string } | null;
}

interface Props {
    investments: {
        data: Investment[];
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
    { title: 'Investments', href: '/investments' },
    { title: 'Trash', href: '/investments/trash' },
];

const formatPrice = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const formatDate = (date: string | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US');
};

export default function InvestmentsTrash({ investments }: Props) {

    const handlePageChange = (url: string | null) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trashed Investments" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">Trashed Investments</h1>
                        <span className="rounded-full border px-2.5 py-0.5 text-xs font-semibold">{investments.total} Investments</span>
                    </div>
                    <Link href="/investments">
                        <button className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Back to Investments
                        </button>
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-black text-white">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">#</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investment</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Investor</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase">Capital</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase">Status</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {investments.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="h-24 px-4 py-6 text-center text-gray-500">
                                        Trash is empty.
                                    </td>
                                </tr>
                            ) : (
                                investments.data.map((investment, index) => (
                                    <tr key={investment.id} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3">{investments.from ? investments.from + index : index + 1}</td>
                                        <td className="px-4 py-3">
                                            <div className="font-mono text-xs font-semibold">{investment.investment_number}</div>
                                            {investment.creator && <div className="text-xs text-gray-500">By: {investment.creator.name}</div>}
                                        </td>
                                        <td className="px-4 py-3">{investment.investor?.full_name || '—'}</td>
                                        <td className="px-4 py-3 text-xs">{formatDate(investment.investment_date)}</td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            <div className="font-semibold">{formatPrice(investment.capital_amount_afn)} USD</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">{investment.status}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-center gap-3">
                                                <button
                                                    onClick={() => void restoreItem(investment.investment_number, `/investments/${investment.id}/restore`)}
                                                    className="text-xs font-semibold text-green-600 hover:text-green-800"
                                                >
                                                    Restore
                                                </button>
                                                <button
                                                    onClick={() => void forceDeleteItem(investment.investment_number, `/investments/${investment.id}/force-delete`)}
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

                {investments.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Showing {investments.from || 0} to {investments.to || 0} of {investments.total} results
                        </div>
                        <div className="flex gap-1">
                            {investments.links.map((link, i) => (
                                <button
                                    key={i}
                                    onClick={() => handlePageChange(link.url)}
                                    disabled={!link.url}
                                    className={`min-w-[40px] rounded-md border px-3 py-1 text-sm ${
                                        link.active
                                            ? 'bg-black text-white'
                                            : link.url
                                              ? 'bg-white hover:bg-gray-100'
                                              : 'cursor-not-allowed bg-gray-100 text-gray-400'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
