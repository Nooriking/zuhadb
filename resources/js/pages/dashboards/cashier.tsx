import CurrencyNote, { type DashboardReporting } from '@/components/dashboard/currency-note';
import KpiCard from '@/components/dashboard/kpi-card';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { Head, Link } from '@inertiajs/react';
import { Banknote, ShoppingBag, Users, Wallet } from 'lucide-react';

interface SaleRow {
    id: number;
    invoice_number: string;
    customer: string;
    total_afn: number;
    total_ledger?: number;
    remaining_due_afn: number;
    remaining_due_ledger?: number;
    ledger_currency_code?: string | null;
    sale_date: string | null;
}

interface Props {
    kpis: {
        today_sales: number;
        today_orders: number;
        today_collected: number;
        customer_due: number;
    };
    recentSales: SaleRow[];
    reporting: DashboardReporting;
}

const FALLBACK_REPORTING: DashboardReporting = {
    currency: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    ledger: { code: 'AFN', name: 'Afghan Afghani', symbol: '؋', decimal_places: 2 },
    conversion_available: true,
    converts_from_ledger: false,
};

export default function CashierDashboard({ kpis, recentSales, reporting = FALLBACK_REPORTING }: Props) {
    const money = (value: number) => formatMoney(value, reporting.currency);

    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title="Cashier Dashboard" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Cashier Dashboard</h1>
                        <CurrencyNote reporting={reporting} />
                    </div>
                    <Link href="/sales/create" className="rounded-md bg-gray-900 px-4 py-2 text-sm text-white">
                        New Sale
                    </Link>
                </div>
                <div className="grid gap-4 md:grid-cols-4">
                    <KpiCard title="Today's sales" value={money(kpis.today_sales)} icon={ShoppingBag} color="blue" />
                    <KpiCard title="Invoices" value={kpis.today_orders} icon={Wallet} color="purple" />
                    <KpiCard title="Cash collected" value={money(kpis.today_collected)} icon={Banknote} color="green" />
                    <KpiCard title="Customer dues" value={money(kpis.customer_due)} icon={Users} color="orange" />
                </div>
                <div className="rounded-lg border bg-white">
                    <div className="border-b px-4 py-3 font-semibold">Recent sales</div>
                    {recentSales.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-sm">No recent sales.</p>
                    ) : (
                        recentSales.map((sale) => (
                            <Link
                                key={sale.id}
                                href={`/sales/${sale.id}`}
                                className="flex items-center justify-between border-b px-4 py-3 text-sm last:border-b-0"
                            >
                                <span>
                                    {sale.invoice_number} — {sale.customer}
                                </span>
                                <span className="text-right font-mono">
                                    <div>{money(sale.total_afn)}</div>
                                    {sale.total_ledger !== undefined &&
                                        reporting.converts_from_ledger &&
                                        reporting.conversion_available && (
                                            <div className="text-muted-foreground text-xs">
                                                {formatMoney(sale.total_ledger, reporting.ledger)}
                                            </div>
                                        )}
                                </span>
                            </Link>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
