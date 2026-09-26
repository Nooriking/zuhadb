import KpiCard from '@/components/dashboard/kpi-card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Banknote, ShoppingBag, Users, Wallet } from 'lucide-react';

interface SaleRow {
    id: number;
    invoice_number: string;
    customer: string;
    total_afn: number;
    remaining_due_afn: number;
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
}

const money = (value: number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value) + ' AFN';

export default function CashierDashboard({ kpis, recentSales }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title="Cashier Dashboard" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Cashier Dashboard</h1>
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
                    {recentSales.map((sale) => (
                        <Link key={sale.id} href={`/sales/${sale.id}`} className="flex items-center justify-between border-b px-4 py-3 text-sm last:border-b-0">
                            <span>
                                {sale.invoice_number} — {sale.customer}
                            </span>
                            <span className="font-mono">{money(sale.total_afn)}</span>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
