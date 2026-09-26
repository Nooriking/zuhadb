import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Transaction {
    id: number;
    type: string;
    type_label?: string;
    amount_afn: string;
    balance_type: string;
    balance_after_afn: string;
    transaction_date: string | null;
    description: string | null;
    investment: { id: number; investment_number: string } | null;
}

interface Props {
    investor: {
        id: number;
        full_name: string;
        phone: string | null;
        wallet?: { capital_balance_afn: string; profit_balance_afn: string } | null;
    };
    transactions: {
        data: Transaction[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const formatPrice = (value: string | number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(typeof value === 'string' ? parseFloat(value) : value);

export default function InvestorTransactionsByInvestor({ investor, transactions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Investor Transactions', href: '/investor-transactions' },
        { title: investor.full_name, href: `/investor-transactions/by-investor/${investor.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${investor.full_name} transactions`} />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{investor.full_name}</h1>
                        <p className="text-sm text-gray-500">{investor.phone || 'No phone'}</p>
                    </div>
                    <Link href={`/investors/${investor.id}`} className="rounded-md border px-4 py-2 text-sm">
                        Open account
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Capital balance</p>
                        <p className="font-mono text-2xl font-bold">{formatPrice(investor.wallet?.capital_balance_afn || 0)} USD</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-xs text-gray-500">Profit balance</p>
                        <p className="font-mono text-2xl font-bold">{formatPrice(investor.wallet?.profit_balance_afn || 0)} USD</p>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-md border bg-white">
                    <table className="w-full text-sm">
                        <thead className="border-b text-left text-xs text-gray-500">
                            <tr>
                                <th className="p-3">Date</th>
                                <th className="p-3">Type</th>
                                <th className="p-3">Balance</th>
                                <th className="p-3 text-right">Amount</th>
                                <th className="p-3 text-right">After</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="p-6 text-center text-gray-500">
                                        No transactions yet.
                                    </td>
                                </tr>
                            ) : (
                                transactions.data.map((row) => (
                                    <tr key={row.id} className="border-b">
                                        <td className="p-3">{row.transaction_date ? new Date(row.transaction_date).toLocaleDateString('en-US') : '—'}</td>
                                        <td className="p-3">
                                            <Link href={`/investor-transactions/${row.id}`} className="text-blue-600 hover:underline">
                                                {row.type_label || row.type}
                                            </Link>
                                        </td>
                                        <td className="p-3 capitalize">{row.balance_type}</td>
                                        <td className="p-3 text-right font-mono">{formatPrice(row.amount_afn)} USD</td>
                                        <td className="p-3 text-right font-mono">{formatPrice(row.balance_after_afn)} USD</td>
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
