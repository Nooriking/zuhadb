import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Entry {
    id: number;
    quantity: number;
    profit_amount_afn: string;
    entry_type: string;
    status: string;
    earned_at: string | null;
    investment: { id: number; investment_number: string } | null;
    sale: { id: number; invoice_number: string } | null;
}

interface Props {
    investor: { id: number; full_name: string; phone: string | null };
    entries: { data: Entry[] };
    stats: {
        total_entries: number;
        pending_profit: number;
        paid_profit: number;
        total_profit: number;
    };
}

const formatPrice = (value: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(typeof value === 'string' ? parseFloat(value) : value);

export default function ProfitEntriesByInvestor({ investor, entries, stats }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Investor Profit Entries', href: '/investor-profit-entries' },
        { title: investor.full_name, href: `/investor-profit-entries/by-investor/${investor.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${investor.full_name} profit`} />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{investor.full_name}</h1>
                        <p className="text-sm text-gray-500">Profit history</p>
                    </div>
                    <Link href={`/investors/${investor.id}`} className="rounded-md border px-4 py-2 text-sm">
                        Open account
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Total profit</p>
                        <p className="font-mono text-2xl font-bold">{formatPrice(stats.total_profit)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Pending</p>
                        <p className="font-mono text-2xl font-bold">{formatPrice(stats.pending_profit)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Paid</p>
                        <p className="font-mono text-2xl font-bold">{formatPrice(stats.paid_profit)} USD</p>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-md border bg-white">
                    <table className="w-full text-sm">
                        <thead className="border-b text-left text-xs text-gray-500">
                            <tr>
                                <th className="p-3">Date</th>
                                <th className="p-3">Investment</th>
                                <th className="p-3">Sale</th>
                                <th className="p-3">Type</th>
                                <th className="p-3">Status</th>
                                <th className="p-3 text-right">Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="p-6 text-center text-gray-500">
                                        No profit entries yet.
                                    </td>
                                </tr>
                            ) : (
                                entries.data.map((row) => (
                                    <tr key={row.id} className="border-b">
                                        <td className="p-3">{row.earned_at ? new Date(row.earned_at).toLocaleDateString('en-US') : '—'}</td>
                                        <td className="p-3">{row.investment?.investment_number || '—'}</td>
                                        <td className="p-3">{row.sale?.invoice_number || '—'}</td>
                                        <td className="p-3 capitalize">{row.entry_type}</td>
                                        <td className="p-3 capitalize">{row.status}</td>
                                        <td className="p-3 text-right font-mono">
                                            <Link href={`/investor-profit-entries/${row.id}`} className="text-blue-600 hover:underline">
                                                {formatPrice(row.profit_amount_afn)} USD
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
                <p className="text-xs text-gray-500">{stats.total_entries} entries</p>
            </div>
        </AppLayout>
    );
}
