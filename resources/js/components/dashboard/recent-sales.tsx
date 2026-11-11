// resources/js/components/dashboard/recent-sales.tsx

import { formatMoney, type SharedCurrency } from '@/lib/money';
import { Link } from '@inertiajs/react';

interface Sale {
    id: number;
    invoice_number: string;
    total_afn: number;
    total_ledger?: number;
    ledger_currency_code?: string | null;
    payment_status: string;
    status: string;
    sale_date: string;
    branch: string | null;
    customer: string;
}

interface Props {
    sales: Sale[];
    embedded?: boolean;
    currency?: SharedCurrency | null;
}

const getStatusBadge = (status: string) => {
    const map: Record<string, string> = {
        completed: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
        paid: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        partial: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300',
        unpaid: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    };
    return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${map[status] || map.draft}`}>{status}</span>;
};

export default function RecentSales({ sales, embedded = false, currency }: Props) {
    const list = (
        <div className="divide-border divide-y">
            {sales.length === 0 ? (
                <div className="text-muted-foreground p-6 text-center text-sm">No recent sales.</div>
            ) : (
                sales.map((sale) => (
                    <Link
                        key={sale.id}
                        href={`/sales/${sale.id}`}
                        className="hover:bg-muted/50 flex items-center justify-between px-6 py-3 transition-colors"
                    >
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-xs font-semibold">{sale.invoice_number}</span>
                                {getStatusBadge(sale.payment_status)}
                            </div>
                            <div className="text-muted-foreground mt-1 text-xs">
                                {sale.customer} · {sale.branch}
                                {sale.sale_date ? ` · ${sale.sale_date}` : ''}
                            </div>
                        </div>
                        <div className="text-right">
                            <div className="font-mono text-sm font-semibold">{formatMoney(sale.total_afn, currency)}</div>
                            {sale.total_ledger !== undefined &&
                                currency &&
                                sale.ledger_currency_code &&
                                currency.code !== sale.ledger_currency_code && (
                                    <div className="text-muted-foreground text-xs">
                                        {formatMoney(sale.total_ledger, {
                                            code: sale.ledger_currency_code,
                                            symbol: null,
                                            decimal_places: 2,
                                        })}
                                    </div>
                                )}
                        </div>
                    </Link>
                ))
            )}
        </div>
    );

    if (embedded) return list;

    return (
        <div className="bg-card rounded-lg border shadow-sm">
            <div className="flex items-center justify-between border-b px-6 py-4">
                <h3 className="font-semibold">Recent Sales</h3>
                <Link href="/sales" className="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">
                    View all →
                </Link>
            </div>
            {list}
        </div>
    );
}
